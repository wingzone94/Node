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


	// --- 1.3: ファクトチェックは「必須」から「推奨」へ（公開ゲートは撤去済み） ---

	public function test_auto_check_is_still_scheduled_even_though_publish_is_open() {
		// 「推奨」に変えても自動実行そのものは止めない
		$post_id = $this->make_draft();
		wp_clear_scheduled_hook( 'node_ai_auto_fact_check', array( $post_id ) );

		node_ai_maybe_schedule_fact_check( $post_id, get_post( $post_id ) );
		$this->assertNotFalse( wp_next_scheduled( 'node_ai_auto_fact_check', array( $post_id ) ) );
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
