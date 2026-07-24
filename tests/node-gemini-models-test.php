<?php
/**
 * Gemini モデル一覧・思考量（thinkingLevel）仮想IDの自動テスト
 *
 * @package Node
 */

class Node_Gemini_Models_Test extends WP_UnitTestCase {

	private $user_id;

	private $captured_request = null;

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'Node_Gemini_API' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/class-gemini-api.php';
		}

		$this->user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $this->user_id, 'node_gemini_api_key', 'dummy_key' );
		wp_set_current_user( $this->user_id );

		delete_transient( node_gemini_models_cache_key() );
		$this->captured_request = null;
	}

	public function tear_down() {
		delete_transient( node_gemini_models_cache_key() );
		parent::tear_down();
	}

	// --- モデルIDバリデーション ---

	public function test_model_id_validation_accepts_thinking_suffix() {
		$this->assertTrue( node_is_valid_gemini_model_id( 'gemini-3.5-flash' ) );
		$this->assertTrue( node_is_valid_gemini_model_id( 'gemini-3.5-flash@high' ) );
		$this->assertTrue( node_is_valid_gemini_model_id( 'gemini-3.1-pro-preview@low' ) );
		$this->assertFalse( node_is_valid_gemini_model_id( 'gemini-3.5-flash@medium' ) );
		$this->assertFalse( node_is_valid_gemini_model_id( 'gpt-4o' ) );
		$this->assertFalse( node_is_valid_gemini_model_id( '@high' ) );
	}

	// --- ListModels エントリのフィルタ ---

	public function test_parse_entry_accepts_text_models_and_rejects_image_models() {
		$make = static function ( string $id ): array {
			return array(
				'name'                       => 'models/' . $id,
				'displayName'                => $id,
				'supportedGenerationMethods' => array( 'generateContent' ),
			);
		};

		$this->assertNotNull( node_parse_gemini_model_entry( $make( 'gemini-3.1-pro-preview' ) ) );
		$this->assertNotNull( node_parse_gemini_model_entry( $make( 'gemini-3.5-flash' ) ) );
		// 画像系（Nano Banana）・TTS・ロボティクスは除外
		$this->assertNull( node_parse_gemini_model_entry( $make( 'gemini-3.1-flash-image' ) ) );
		$this->assertNull( node_parse_gemini_model_entry( $make( 'gemini-3.1-flash-image-preview' ) ) );
		$this->assertNull( node_parse_gemini_model_entry( $make( 'gemini-3.1-flash-tts-preview' ) ) );
		$this->assertNull( node_parse_gemini_model_entry( $make( 'gemini-robotics-er-1.6-preview' ) ) );
	}

	// --- 一覧取得時の思考量つき仮想ID付与 ---

	public function test_fetch_adds_thinking_variants_for_gemini3_pro_and_flash() {
		add_filter( 'pre_http_request', array( $this, 'mock_list_models' ), 10, 3 );
		$result = node_fetch_gemini_models_from_api( 'dummy_key', true );
		remove_filter( 'pre_http_request', array( $this, 'mock_list_models' ), 10 );

		$this->assertIsArray( $result );
		$models = $result['models'];

		$this->assertArrayHasKey( 'gemini-3.5-flash', $models );
		$this->assertArrayHasKey( 'gemini-3.5-flash@high', $models );
		$this->assertArrayHasKey( 'gemini-3.5-flash@low', $models );
		$this->assertArrayHasKey( 'gemini-3.1-pro-preview@high', $models );
		// Lite・2.x系には思考量バリアントを付けない
		$this->assertArrayNotHasKey( 'gemini-3.1-flash-lite@high', $models );
		$this->assertArrayNotHasKey( 'gemini-2.5-flash@high', $models );
	}

	public function test_default_model_never_returns_thinking_variant() {
		add_filter( 'pre_http_request', array( $this, 'mock_list_models' ), 10, 3 );
		update_user_meta( $this->user_id, 'node_gemini_api_key', 'dummy_key' );
		$default = node_get_default_gemini_model();
		remove_filter( 'pre_http_request', array( $this, 'mock_list_models' ), 10 );

		$this->assertStringNotContainsString( '@', $default );
		$this->assertStringContainsStringIgnoringCase( 'flash', $default );
	}

	// --- generate_content の思考量変換 ---

	public function test_generate_content_strips_suffix_and_sends_thinking_level() {
		update_user_meta( $this->user_id, 'node_gemini_model', 'gemini-3.5-flash@low' );

		add_filter( 'pre_http_request', array( $this, 'mock_generate_capture' ), 10, 3 );
		$api    = new Node_Gemini_API();
		$result = $api->generate_content( 'テスト' );
		remove_filter( 'pre_http_request', array( $this, 'mock_generate_capture' ), 10 );

		$this->assertSame( 'ok', $result );
		$this->assertNotNull( $this->captured_request );

		// URL には @low を含まない実IDが使われる
		$this->assertStringContainsString( '/models/gemini-3.5-flash:generateContent', $this->captured_request['url'] );
		$this->assertStringNotContainsString( '@low', $this->captured_request['url'] );

		// generationConfig.thinkingConfig.thinkingLevel = low が送信される
		$body = json_decode( $this->captured_request['body'], true );
		$this->assertSame( 'low', $body['generationConfig']['thinkingConfig']['thinkingLevel'] ?? null );
	}

	public function test_generate_content_without_suffix_sends_no_thinking_config() {
		update_user_meta( $this->user_id, 'node_gemini_model', 'gemini-2.5-flash' );

		add_filter( 'pre_http_request', array( $this, 'mock_generate_capture' ), 10, 3 );
		$api = new Node_Gemini_API();
		$api->generate_content( 'テスト' );
		remove_filter( 'pre_http_request', array( $this, 'mock_generate_capture' ), 10 );

		$body = json_decode( $this->captured_request['body'], true );
		$this->assertArrayNotHasKey( 'thinkingConfig', $body['generationConfig'] );
	}

	// --- Mock Handlers ---

	public function mock_list_models( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, 'generativelanguage.googleapis.com/v1beta/models' ) === false || strpos( $url, ':generateContent' ) !== false ) {
			return $preempt;
		}
		$make = static function ( string $id, string $label ): array {
			return array(
				'name'                       => 'models/' . $id,
				'displayName'                => $label,
				'supportedGenerationMethods' => array( 'generateContent' ),
			);
		};
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'models' => array(
					$make( 'gemini-3.5-flash', 'Gemini 3.5 Flash' ),
					$make( 'gemini-3.1-pro-preview', 'Gemini 3.1 Pro Preview' ),
					$make( 'gemini-3.1-flash-lite', 'Gemini 3.1 Flash Lite' ),
					$make( 'gemini-2.5-flash', 'Gemini 2.5 Flash' ),
					$make( 'gemini-3.1-flash-image', 'Nano Banana 2' ),
				),
			) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function mock_generate_capture( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, ':generateContent' ) === false ) {
			return $preempt;
		}
		$this->captured_request = array(
			'url'  => $url,
			'body' => (string) ( $parsed_args['body'] ?? '' ),
		);
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array(
				'candidates' => array(
					array( 'content' => array( 'parts' => array( array( 'text' => 'ok' ) ) ) ),
				),
			) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
