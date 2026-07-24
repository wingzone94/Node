<?php
/**
 * ファクトチェック自動実行・公開ゲートの自動テスト
 *
 * @package Node_AI_Tools
 */

class Node_AI_Auto_Check_Test extends WP_UnitTestCase {

	private $author_id;

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'Node_Gemini_API' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/class-gemini-api.php';
		}
		require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/ajax-handlers.php';
		require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/fact-check-render.php';
		require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/auto-check.php';

		$this->author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $this->author_id, 'node_gemini_api_key', 'dummy_key' );
		wp_set_current_user( $this->author_id );
	}

	private function make_draft( array $overrides = array() ): int {
		$post_id = $this->factory->post->create( array_merge( array(
			'post_status'  => 'draft',
			'post_author'  => $this->author_id,
			'post_title'   => 'Auto Check Draft',
			'post_content' => 'ファクトチェック対象の本文です。',
		), $overrides ) );

		// プラグインが有効な環境では作成時の wp_after_insert_post で予約が入るため、
		// 各テストが node_ai_maybe_schedule_fact_check の挙動を単独で検証できるようクリアする
		wp_clear_scheduled_hook( 'node_ai_auto_fact_check', array( $post_id ) );

		return $post_id;
	}

	private function scheduled( int $post_id ) {
		return wp_next_scheduled( 'node_ai_auto_fact_check', array( $post_id ) );
	}

	// --- 自動実行の予約 ---

	public function test_draft_save_schedules_auto_check() {
		$post_id = $this->make_draft();
		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertNotFalse( $this->scheduled( $post_id ) );
	}

	public function test_author_without_key_does_not_schedule() {
		$no_key_author = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id       = $this->make_draft( array( 'post_author' => $no_key_author ) );
		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertFalse( $this->scheduled( $post_id ) );
	}

	public function test_empty_content_does_not_schedule() {
		$post_id = $this->make_draft( array( 'post_content' => '   ' ) );
		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertFalse( $this->scheduled( $post_id ) );
	}

	public function test_unchanged_content_does_not_reschedule() {
		$post_id = $this->make_draft();
		update_post_meta( $post_id, '_node_ai_fact_check', '{"claims":[{"claim":"c"}]}' );
		update_post_meta( $post_id, '_node_ai_fact_check_hash', node_ai_fact_check_content_hash( get_post( $post_id ) ) );

		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertFalse( $this->scheduled( $post_id ) );
	}

	public function test_changed_content_schedules_recheck() {
		$post_id = $this->make_draft();
		update_post_meta( $post_id, '_node_ai_fact_check', '{"claims":[{"claim":"c"}]}' );
		update_post_meta( $post_id, '_node_ai_fact_check_hash', 'stale-hash' );

		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertNotFalse( $this->scheduled( $post_id ) );
	}

	// --- cron 実行 ---

	public function test_cron_runner_saves_result_unapproved() {
		$post_id = $this->make_draft();
		update_post_meta( $post_id, '_node_ai_fact_check_approved', '1' );
		update_post_meta( $post_id, '_node_ai_fact_check_error', '以前のエラー' );

		add_filter( 'pre_http_request', array( $this, 'mock_gemini_success' ), 10, 3 );
		node_ai_run_auto_fact_check( $post_id );
		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_success' ), 10 );

		$saved = json_decode( (string) get_post_meta( $post_id, '_node_ai_fact_check', true ), true );
		$this->assertIsArray( $saved );
		$this->assertNotEmpty( $saved['claims'] );
		$this->assertSame( '', (string) get_post_meta( $post_id, '_node_ai_fact_check_approved', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, '_node_ai_fact_check_error', true ) );
		$this->assertSame(
			node_ai_fact_check_content_hash( get_post( $post_id ) ),
			(string) get_post_meta( $post_id, '_node_ai_fact_check_hash', true )
		);
	}

	public function test_cron_runner_records_error_on_api_failure() {
		$post_id = $this->make_draft();

		add_filter( 'pre_http_request', array( $this, 'mock_gemini_429' ), 10, 3 );
		node_ai_run_auto_fact_check( $post_id );
		remove_filter( 'pre_http_request', array( $this, 'mock_gemini_429' ), 10 );

		$this->assertSame( '', (string) get_post_meta( $post_id, '_node_ai_fact_check', true ) );
		$this->assertNotSame( '', (string) get_post_meta( $post_id, '_node_ai_fact_check_error', true ) );
	}

	// --- 公開ゲート ---

	private function prepared( int $post_id, string $status ): stdClass {
		$prepared              = new stdClass();
		$prepared->ID          = $post_id;
		$prepared->post_status = $status;
		$prepared->post_author = $this->author_id;
		return $prepared;
	}

	public function test_gate_blocks_publish_without_fact_check() {
		$post_id = $this->make_draft();
		$result  = node_ai_gate_publish_without_fact_check( $this->prepared( $post_id, 'publish' ), null );
		$this->assertWPError( $result );
		$this->assertSame( 'node_ai_fact_check_required', $result->get_error_code() );
	}

	public function test_gate_blocks_scheduling_without_fact_check() {
		$post_id = $this->make_draft();
		$result  = node_ai_gate_publish_without_fact_check( $this->prepared( $post_id, 'future' ), null );
		$this->assertWPError( $result );
	}

	public function test_gate_allows_publish_with_fact_check() {
		$post_id = $this->make_draft();
		update_post_meta( $post_id, '_node_ai_fact_check', '{"claims":[{"claim":"c"}]}' );
		$result = node_ai_gate_publish_without_fact_check( $this->prepared( $post_id, 'publish' ), null );
		$this->assertNotWPError( $result );
	}

	public function test_gate_skips_author_without_key() {
		$no_key_author = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id       = $this->make_draft( array( 'post_author' => $no_key_author ) );

		$prepared              = $this->prepared( $post_id, 'publish' );
		$prepared->post_author = $no_key_author;
		$result = node_ai_gate_publish_without_fact_check( $prepared, null );
		$this->assertNotWPError( $result );
	}

	public function test_gate_skips_already_published_post() {
		$post_id = $this->make_draft( array( 'post_status' => 'publish' ) );
		$result  = node_ai_gate_publish_without_fact_check( $this->prepared( $post_id, 'publish' ), null );
		$this->assertNotWPError( $result );
	}

	public function test_gate_skips_draft_save() {
		$post_id = $this->make_draft();
		$result  = node_ai_gate_publish_without_fact_check( $this->prepared( $post_id, 'draft' ), null );
		$this->assertNotWPError( $result );
	}

	// --- Mock Handlers ---

	public function mock_gemini_success( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, ':generateContent' ) === false ) {
			return $preempt;
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'candidates' => array(
					array(
						'content'           => array(
							'parts' => array(
								array( 'text' => '{"summary":"s","overall_risk":"low","claims":[{"claim":"c","status":"uncertain","confidence":"low","note":"n"}]}' ),
							),
						),
						'groundingMetadata' => array( 'webSearchQueries' => array( 'q' ) ),
					),
				),
			) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function mock_gemini_429( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, ':generateContent' ) === false ) {
			return $preempt;
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'error' => array( 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded' ) ) ),
			'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
