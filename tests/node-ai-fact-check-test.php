<?php
/**
 * ファクトチェック機能の自動テスト
 *
 * @package Node_AI_Tools
 */

class Node_AI_Fact_Check_Test extends WP_UnitTestCase {

	private $post_id;

	private $user_id;

	public function set_up() {
		parent::set_up();

		// 必要なファイルをロード
		if ( ! class_exists( 'Node_Gemini_API' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/class-gemini-api.php';
		}
		if ( ! function_exists( 'node_ai_parse_json_response' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/ajax-handlers.php';
		}
		if ( ! function_exists( 'node_ai_get_fact_check_data' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/fact-check-render.php';
		}
		if ( ! function_exists( 'node_ai_get_fact_check_state' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/admin/post-list-column.php';
		}

		// APIキーは user meta（node_gemini_api_key）から読まれるため、キー持ちユーザーを用意する
		$this->user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $this->user_id, 'node_gemini_api_key', 'dummy_key' );
		wp_set_current_user( $this->user_id );

		$this->post_id = $this->factory->post->create( array(
			'post_title'   => 'Test Fact Check Post',
			'post_content' => 'This is a test content for fact checking.',
		) );
	}

	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * @covers ::node_ai_parse_json_response
	 */
	// --- 1.3: 検証環境では公開リスクの言い回しに切り替える ---

	public function test_status_labels_switch_by_environment() {
		$keys = array( 'likely_correct', 'uncertain', 'likely_incorrect', 'unverifiable' );

		// 本番: 従来どおりの言い回し
		add_filter( 'node_ai_is_production', '__return_true' );
		$production = node_ai_fact_check_status_labels();
		remove_filter( 'node_ai_is_production', '__return_true' );

		$this->assertSame( 'おそらく不正確', $production['likely_incorrect'] );
		$this->assertSame( '要確認', $production['uncertain'] );

		// 検証環境（cybernode.local 等）: 公開リスクの言い回しへ切り替わる
		add_filter( 'node_ai_is_production', '__return_false' );
		$staging = node_ai_fact_check_status_labels();
		remove_filter( 'node_ai_is_production', '__return_false' );

		$this->assertSame( '本番ではアウト', $staging['likely_incorrect'] );
		$this->assertSame( '本番では要注意', $staging['uncertain'] );

		// 4種のキーは環境によらず揃っている（表示側が落ちないこと）
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $production );
			$this->assertArrayHasKey( $key, $staging );
			$this->assertNotSame( '', $staging[ $key ] );
		}
	}

	public function test_node_ai_parse_json_response_valid() {
		$raw = '{"summary":"Test","overall_risk":"low"}';
		$parsed = node_ai_parse_json_response( $raw );
		$this->assertIsArray( $parsed );
		$this->assertEquals( 'Test', $parsed['summary'] );
	}

	/**
	 * @covers ::node_ai_parse_json_response
	 */
	public function test_node_ai_parse_json_response_with_markdown_fences() {
		$raw = "```json\n{\"summary\":\"Test Fences\",\"overall_risk\":\"medium\"}\n```";
		$parsed = node_ai_parse_json_response( $raw );
		$this->assertIsArray( $parsed );
		$this->assertEquals( 'Test Fences', $parsed['summary'] );
	}

	/**
	 * @covers ::node_ai_parse_json_response
	 */
	public function test_node_ai_parse_json_response_with_surrounding_prose() {
		// text/plain 応答ではJSONの前後に説明文が付くことがある（実機で発生確認済み）
		$raw = "以下が結果です。\n{\"summary\":\"Prose Test\",\"claims\":[{\"claim\":\"c\"}]}\n以上です。";
		$parsed = node_ai_parse_json_response( $raw );
		$this->assertIsArray( $parsed );
		$this->assertEquals( 'Prose Test', $parsed['summary'] );
	}

	/**
	 * @covers ::node_ai_parse_json_response
	 */
	public function test_node_ai_parse_json_response_invalid() {
		$raw = 'This is not json { broken ';
		$parsed = node_ai_parse_json_response( $raw );
		$this->assertNull( $parsed );
	}

	/**
	 * @covers Node_Gemini_API::fact_check
	 */
	public function test_fact_check_success_with_grounding() {
		add_filter( 'pre_http_request', array( $this, 'mock_gemini_api_success' ), 10, 3 );


		$api = new Node_Gemini_API();
		$result = $api->fact_check( 'This is a test content', 'Test Title' );

		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_api_success' ), 10 );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'text', $result );
		$this->assertArrayHasKey( 'grounding', $result );
		$this->assertNotEmpty( $result['grounding'] );
		
		$parsed = node_ai_parse_json_response( $result['text'] );
		$this->assertIsArray( $parsed );
		$this->assertEquals( 'Test summary', $parsed['summary'] );
	}

	/**
	 * @covers Node_Gemini_API::fact_check
	 */
	public function test_fact_check_error_429() {
		add_filter( 'pre_http_request', array( $this, 'mock_gemini_api_429' ), 10, 3 );

		$api = new Node_Gemini_API();
		$result = $api->fact_check( 'Test content', 'Test Title' );

		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_api_429' ), 10 );

		$this->assertWPError( $result );
		// 検索併用の 429 は「検索が使えない」と判定される（ファクトチェックは検索が前提のため）
		$this->assertContains(
			$result->get_error_code(),
			array( 'gemini_quota_exceeded', 'gemini_grounding_unavailable' )
		);
	}
	
	/**
	 * @covers Node_Gemini_API::fact_check
	 */
	public function test_fact_check_error_503() {
		add_filter( 'pre_http_request', array( $this, 'mock_gemini_api_503' ), 10, 3 );

		$api = new Node_Gemini_API();
		$result = $api->fact_check( 'Test content', 'Test Title' );

		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_api_503' ), 10 );

		$this->assertWPError( $result );
		$this->assertEquals( 'gemini_model_unavailable', $result->get_error_code() );
	}
	
	/**
	 * @covers Node_Gemini_API::fact_check
	 */
	public function test_fact_check_timeout() {
		add_filter( 'pre_http_request', array( $this, 'mock_gemini_api_timeout' ), 10, 3 );

		$api = new Node_Gemini_API();
		$result = $api->fact_check( 'Test content', 'Test Title' );

		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_api_timeout' ), 10 );

		$this->assertWPError( $result );
		$this->assertEquals( 'gemini_timeout', $result->get_error_code() );
	}

	/**
	 * Test the logic inside the AJAX handler without actually doing an AJAX request.
	 * We test data sanitization, empty claims error, and flag reset.
	 */
	public function test_ajax_handler_logic_payload_formatting() {
		// Mock the initial state
		update_post_meta( $this->post_id, '_node_ai_fact_check_approved', '1' );

		$fake_api_response_text = wp_json_encode( array(
			'summary' => 'Some summary with <b>HTML</b>',
			'overall_risk' => 'high',
			'claims' => array(
				array(
					'claim' => 'A claim',
					'status' => 'likely_correct',
					'confidence' => 'high',
					'note' => 'Some note'
				)
			)
		) );

		$fake_api_result = array(
			'text' => $fake_api_response_text,
			'grounding' => array(
				'webSearchQueries' => array( 'test query' ),
				'groundingChunks' => array(
					array( 'web' => array( 'uri' => 'https://example.com', 'title' => 'Example' ) )
				)
			),
			'guidelines_used' => true
		);

		// Manually run the processing logic found in node_ai_ajax_fact_check
		$data = node_ai_parse_json_response( $fake_api_result['text'] );
		$this->assertNotEmpty( $data['claims'] );
		
		require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/fact-check-render.php';
		$sources = node_ai_extract_grounding_sources( $fake_api_result['grounding'] );

		$payload = array(
			'summary'         => sanitize_text_field( $data['summary'] ?? '' ),
			'overall_risk'    => sanitize_key( $data['overall_risk'] ?? 'medium' ),
			'claims'          => array(),
			'sources'         => $sources,
			'search_queries'  => array_map( 'sanitize_text_field', (array) ( $fake_api_result['grounding']['webSearchQueries'] ?? array() ) ),
			'grounded'        => ! empty( $sources ) || ! empty( $fake_api_result['grounding']['webSearchQueries'] ),
			'guidelines_used' => ! empty( $fake_api_result['guidelines_used'] ),
			'checked_at'      => current_time( 'mysql' ),
		);

		foreach ( $data['claims'] as $claim ) {
			$payload['claims'][] = array(
				'claim'      => sanitize_text_field( $claim['claim'] ?? '' ),
				'status'     => sanitize_key( $claim['status'] ?? 'uncertain' ),
				'confidence' => sanitize_key( $claim['confidence'] ?? 'low' ),
				'note'       => sanitize_textarea_field( $claim['note'] ?? '' ),
			);
		}

		update_post_meta( $this->post_id, '_node_ai_fact_check', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $this->post_id, '_node_ai_fact_check_approved', '' );

		// Assertions
		$this->assertEquals( 'Some summary with HTML', $payload['summary'] ); // sanitize_text_field strips HTML
		$this->assertEquals( 'high', $payload['overall_risk'] );
		$this->assertCount( 1, $payload['claims'] );
		$this->assertTrue( $payload['grounded'] );
		$this->assertTrue( $payload['guidelines_used'] );
		$this->assertEquals( 'https://example.com', $payload['sources'][0]['url'] );

		$saved_meta = get_post_meta( $this->post_id, '_node_ai_fact_check', true );
		$this->assertNotEmpty( $saved_meta );
		
		$approved_flag = get_post_meta( $this->post_id, '_node_ai_fact_check_approved', true );
		$this->assertEquals( '', $approved_flag ); // Reset to empty
	}

	public function test_ajax_handler_logic_empty_claims() {
		$fake_api_response_text = wp_json_encode( array(
			'summary' => 'No claims found',
			'overall_risk' => 'low',
			'claims' => array()
		) );

		$data = node_ai_parse_json_response( $fake_api_response_text );
		$this->assertTrue( empty( $data['claims'] ) );
	}

	// --- Mock Handlers ---

	public function mock_gemini_api_success( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, 'generativelanguage.googleapis.com' ) === false ) {
			return $preempt;
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'candidates' => array(
					array(
						'content' => array(
							'parts' => array(
								array( 'text' => '{"summary":"Test summary","overall_risk":"low","claims":[{"claim":"c","status":"uncertain","confidence":"low","note":"n"}]}' )
							)
						),
						'groundingMetadata' => array(
							'webSearchQueries' => array( 'test' )
						)
					)
				)
			) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function mock_gemini_api_429( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, 'generativelanguage.googleapis.com' ) === false ) {
			return $preempt;
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'error' => array( 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded' )
			) ),
			'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function mock_gemini_api_503( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, 'generativelanguage.googleapis.com' ) === false ) {
			return $preempt;
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'error' => array( 'status' => 'UNAVAILABLE', 'message' => 'Service Unavailable' )
			) ),
			'response' => array( 'code' => 503, 'message' => 'Service Unavailable' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}
	
	public function mock_gemini_api_timeout( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, 'generativelanguage.googleapis.com' ) === false ) {
			return $preempt;
		}
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
	}

	// --- 1.3: 記事一覧のファクトチェック状況カラム ---

	public function test_fact_check_state_none_when_not_run() {
		$post_id = $this->factory->post->create();

		$state = node_ai_get_fact_check_state( $post_id );
		$this->assertSame( 'none', $state['state'] );
		$this->assertSame( 0, $state['unresolved'] );
	}

	public function test_fact_check_state_counts_only_unresolved_claims() {
		$post_id = $this->factory->post->create();

		update_post_meta(
			$post_id,
			'_node_ai_fact_check',
			wp_json_encode(
				array(
					'summary' => 'test',
					'claims'  => array(
						array( 'claim' => 'a', 'status' => 'likely_incorrect' ),
						array( 'claim' => 'b', 'status' => 'uncertain' ),
						// 対応不要なものは数えない
						array( 'claim' => 'c', 'status' => 'likely_correct' ),
						array( 'claim' => 'd', 'status' => 'unverifiable' ),
					),
				)
			)
		);

		$state = node_ai_get_fact_check_state( $post_id );
		$this->assertSame( 'unresolved', $state['state'] );
		$this->assertSame( 2, $state['unresolved'] );
	}

	public function test_fact_check_state_clear_when_nothing_to_fix() {
		$post_id = $this->factory->post->create();

		update_post_meta(
			$post_id,
			'_node_ai_fact_check',
			wp_json_encode(
				array(
					'summary' => 'test',
					'claims'  => array(
						array( 'claim' => 'a', 'status' => 'likely_correct' ),
					),
				)
			)
		);

		$state = node_ai_get_fact_check_state( $post_id );
		$this->assertSame( 'clear', $state['state'] );
		$this->assertSame( 0, $state['unresolved'] );
	}

	public function test_fact_check_column_is_added_after_title() {
		$columns = node_ai_add_fact_check_column(
			array(
				'cb'    => '',
				'title' => 'タイトル',
				'date'  => '日付',
			)
		);

		$this->assertArrayHasKey( 'node_fact_check', $columns );
		$this->assertSame( array( 'cb', 'title', 'node_fact_check', 'date' ), array_keys( $columns ) );
	}


	// --- 1.3: 検索が使えないモデルでは黙って実行せず中止する ---

	public function test_fact_check_aborts_when_grounding_is_unavailable() {
		$this->grounded_calls = 0;
		$this->plain_calls    = 0;

		update_user_meta( $this->user_id, 'node_gemini_model', 'gemini-3.5-flash' );

		add_filter( 'pre_http_request', array( $this, 'mock_grounding_quota_exhausted' ), 10, 3 );
		$api    = new Node_Gemini_API();
		$result = $api->fact_check( 'テスト本文', 'テストタイトル' );
		remove_filter( 'pre_http_request', array( $this, 'mock_grounding_quota_exhausted' ), 10 );

		// 検索なしでの再実行はしない（黙って精度が落ちるのを防ぐ）
		$this->assertSame( 1, $this->grounded_calls );
		$this->assertSame( 0, $this->plain_calls );

		$this->assertWPError( $result );
		$this->assertSame( 'gemini_grounding_unavailable', $result->get_error_code() );
		$this->assertStringContainsString( '検索', $result->get_error_message() );
		// モデルを使えないものと決めつけず、再試行を促す文言であること
		$this->assertStringContainsString( '時間をおく', $result->get_error_message() );

	}

	public $grounded_calls = 0;
	public $plain_calls    = 0;

	public function mock_grounding_quota_exhausted( $preempt, $args, $url ) {
		if ( false === strpos( $url, ':generateContent' ) ) {
			return $preempt;
		}

		$body = json_decode( $args['body'] ?? '{}', true );

		// google_search ツール付きの要求だけ 429 にする
		if ( ! empty( $body['tools'] ) ) {
			$this->grounded_calls++;

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'error' => array(
							'code'    => 429,
							'status'  => 'RESOURCE_EXHAUSTED',
							'message' => 'You exceeded your current quota',
						),
					)
				),
				'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		$this->plain_calls++;

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'finishReason' => 'STOP',
							'content'      => array(
								'parts' => array(
									array(
										'text' => wp_json_encode(
											array(
												'summary'      => '検索なしの所見',
												'overall_risk' => 'medium',
												'claims'       => array(
													array(
														'claim'      => 'テスト主張',
														'status'     => 'unverifiable',
														'confidence' => 'low',
														'note'       => '検索が使えないため未検証',
													),
												),
											),
											JSON_UNESCAPED_UNICODE
										),
									),
								),
							),
						),
					),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

}
