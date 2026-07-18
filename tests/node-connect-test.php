<?php
/**
 * plugins-embedded/node-connect/ の自動テスト。
 *
 * イベント判定（status遷移分岐）・重複送信抑止・Webhookごとのイベント購読フィルタ・
 * 送信履歴上限・再送スケジュール・設定サニタイズをカバーする。
 *
 * @package Node_Connect
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/node-connect/node-connect.php';
require_once dirname( __DIR__ ) . '/plugins-embedded/node-connect/admin/settings-page.php';

class Node_Connect_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// プラグイン本体は plugins_loaded 時に有効化オプション未設定だと投稿フックを
		// 登録しない。またWPテストは各テスト後に $wp_filter を巻き戻すため、
		// 毎テストの set_up で明示的に登録し直す。
		$plugin = Node_Connect::instance();
		Node_Connect_Event_Bus::instance()->register();
		add_action( 'transition_post_status', [ $plugin, 'on_transition_post_status' ], 10, 3 );
		add_action( 'post_updated', [ $plugin, 'on_post_updated' ], 10, 3 );
		add_action( 'wp_trash_post', [ $plugin, 'on_trash_post' ] );
		add_action( 'before_delete_post', [ $plugin, 'on_before_delete_post' ], 10, 2 );

		update_option( Node_Connect_Event_Bus::OPTION_ENABLED, '1' );
		update_option( Node_Connect_Event_Bus::OPTION_PAUSED, '' );
		update_option(
			Node_Connect_Event_Bus::OPTION_WEBHOOKS,
			[
				[
					'label'   => 'テスト用',
					'url'     => 'https://discord.com/api/webhooks/123/abcdef',
					'events'  => array_keys( Node_Connect_Event_Bus::get_event_catalog() ),
					'enabled' => true,
				],
			]
		);
	}

	/**
	 * @return array<int, array> node_connect_deliver キューの引数リスト。
	 */
	private function get_queued_deliveries(): array {
		$queued = [];
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( (array) $events as $hook => $entries ) {
				if ( Node_Connect_Event_Bus::CRON_HOOK !== $hook ) {
					continue;
				}
				foreach ( $entries as $entry ) {
					$queued[] = $entry['args'];
				}
			}
		}
		return $queued;
	}

	private function clear_cron(): void {
		_set_cron_array( [] );
	}

	// ---- status遷移の分類 ----

	public function test_classify_transition(): void {
		$this->assertSame( 'post_published', Node_Connect_Event_Bus::classify_transition( 'publish', 'draft' ) );
		$this->assertSame( 'post_published', Node_Connect_Event_Bus::classify_transition( 'publish', 'future' ) );
		$this->assertSame( 'post_published', Node_Connect_Event_Bus::classify_transition( 'publish', 'new' ) );
		$this->assertSame( 'post_unpublished', Node_Connect_Event_Bus::classify_transition( 'draft', 'publish' ) );
		$this->assertSame( 'post_unpublished', Node_Connect_Event_Bus::classify_transition( 'private', 'publish' ) );
		$this->assertNull( Node_Connect_Event_Bus::classify_transition( 'publish', 'publish' ) );
		$this->assertNull( Node_Connect_Event_Bus::classify_transition( 'trash', 'publish' ), 'ゴミ箱移動は wp_trash_post 側で扱うため遷移分類では null' );
		$this->assertNull( Node_Connect_Event_Bus::classify_transition( 'draft', 'auto-draft' ) );
	}

	// ---- 投稿ライフサイクル → イベント ----

	public function test_new_publish_queues_post_published(): void {
		$this->clear_cron();
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 'post_published', $queued[0][0] );
		$this->assertFalse( $queued[0][1]['scheduled'] );
	}

	public function test_scheduled_publish_marks_scheduled_flag(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			]
		);
		$this->clear_cron();

		wp_publish_post( $post_id );

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 'post_published', $queued[0][0] );
		$this->assertTrue( $queued[0][1]['scheduled'] );
	}

	public function test_publish_to_publish_update_queues_post_updated(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->clear_cron();

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => '更新後タイトル',
			]
		);

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 'post_updated', $queued[0][0] );
		$this->assertSame( '更新後タイトル', $queued[0][1]['title'] );
	}

	public function test_unpublish_queues_post_unpublished(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->clear_cron();

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 'post_unpublished', $queued[0][0] );
	}

	public function test_trash_published_post_queues_post_deleted(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->clear_cron();

		wp_trash_post( $post_id );

		$events = array_column( $this->get_queued_deliveries(), 0 );
		$this->assertSame( [ 'post_deleted' ], $events, 'ゴミ箱移動で post_deleted のみ（post_unpublished と二重にならない）' );
	}

	public function test_trash_draft_post_queues_nothing(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$this->clear_cron();

		wp_trash_post( $post_id );

		$this->assertCount( 0, $this->get_queued_deliveries() );
	}

	public function test_revision_does_not_queue_update(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->clear_cron();

		wp_save_post_revision( $post_id );

		$this->assertCount( 0, $this->get_queued_deliveries() );
	}

	// ---- 有効化・一時停止・購読フィルタ ----

	public function test_disabled_queues_nothing(): void {
		update_option( Node_Connect_Event_Bus::OPTION_ENABLED, '' );
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 1 ] );

		$this->assertCount( 0, $this->get_queued_deliveries() );
	}

	public function test_paused_queues_nothing(): void {
		update_option( Node_Connect_Event_Bus::OPTION_PAUSED, '1' );
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 1 ] );

		$this->assertCount( 0, $this->get_queued_deliveries() );
	}

	public function test_unknown_event_is_ignored(): void {
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'unknown_event', [ 'post_id' => 1 ] );

		$this->assertCount( 0, $this->get_queued_deliveries() );
	}

	public function test_webhook_receives_only_subscribed_events(): void {
		update_option(
			Node_Connect_Event_Bus::OPTION_WEBHOOKS,
			[
				[
					'label'   => '新着のみ',
					'url'     => 'https://discord.com/api/webhooks/1/a',
					'events'  => [ 'post_published' ],
					'enabled' => true,
				],
				[
					'label'   => '更新のみ',
					'url'     => 'https://discord.com/api/webhooks/2/b',
					'events'  => [ 'post_updated' ],
					'enabled' => true,
				],
				[
					'label'   => '停止中',
					'url'     => 'https://discord.com/api/webhooks/3/c',
					'events'  => [ 'post_published' ],
					'enabled' => false,
				],
			]
		);
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 10 ] );

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued, '購読中かつ有効な Webhook 1件だけに届く' );
		$this->assertSame( 0, $queued[0][2], 'Webhook index 0（新着のみ）が対象' );
	}

	public function test_duplicate_dispatch_is_suppressed(): void {
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 42 ] );
		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 42 ] );

		$this->assertCount( 1, $this->get_queued_deliveries(), '同一 post_id + イベント + URL は10分間抑止' );
	}

	public function test_different_events_are_not_suppressed(): void {
		$this->clear_cron();

		Node_Connect_Event_Bus::dispatch( 'post_published', [ 'post_id' => 42 ] );
		Node_Connect_Event_Bus::dispatch( 'post_updated', [ 'post_id' => 42 ] );

		$this->assertCount( 2, $this->get_queued_deliveries() );
	}

	// ---- 送信・再送・履歴 ----

	public function test_deliver_success_logs_and_does_not_retry(): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 204, 'message' => 'No Content' ],
				'headers'  => [],
				'body'     => '',
			]
		);
		$this->clear_cron();

		Node_Connect_Webhook_Sender::deliver( 'post_published', [ 'post_id' => 1, 'title' => 'テスト' ], 0, 1 );

		$log = Node_Connect_Delivery_Log::get();
		$this->assertCount( 1, $log );
		$this->assertTrue( $log[0]['ok'] );
		$this->assertSame( 'HTTP 204', $log[0]['status'] );
		$this->assertCount( 0, $this->get_queued_deliveries(), '成功時は再送しない' );
	}

	public function test_deliver_failure_schedules_retry_up_to_three_attempts(): void {
		add_filter( 'pre_http_request', static fn() => new WP_Error( 'timeout', '接続タイムアウト' ) );
		$this->clear_cron();

		Node_Connect_Webhook_Sender::deliver( 'post_published', [ 'post_id' => 1 ], 0, 1 );

		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued, '1回目失敗 → 2回目を予約' );
		$this->assertSame( 2, $queued[0][3] );

		$this->clear_cron();
		Node_Connect_Webhook_Sender::deliver( 'post_published', [ 'post_id' => 1 ], 0, 2 );
		$queued = $this->get_queued_deliveries();
		$this->assertCount( 1, $queued, '2回目失敗 → 3回目を予約' );
		$this->assertSame( 3, $queued[0][3] );

		$this->clear_cron();
		Node_Connect_Webhook_Sender::deliver( 'post_published', [ 'post_id' => 1 ], 0, 3 );
		$this->assertCount( 0, $this->get_queued_deliveries(), '3回目（最終）失敗 → 再送しない' );

		$log = Node_Connect_Delivery_Log::get();
		$this->assertCount( 3, $log );
		$this->assertFalse( $log[0]['ok'] );
	}

	public function test_deliver_http_error_status_counts_as_failure(): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 404, 'message' => 'Not Found' ],
				'headers'  => [],
				'body'     => '',
			]
		);
		$this->clear_cron();

		Node_Connect_Webhook_Sender::deliver( 'post_published', [ 'post_id' => 1 ], 0, 1 );

		$log = Node_Connect_Delivery_Log::get();
		$this->assertFalse( $log[0]['ok'] );
		$this->assertSame( 'HTTP 404', $log[0]['status'] );
		$this->assertCount( 1, $this->get_queued_deliveries() );
	}

	public function test_delivery_log_is_capped_and_newest_first(): void {
		for ( $i = 1; $i <= 55; $i++ ) {
			Node_Connect_Delivery_Log::add(
				[
					'event'   => 'post_published',
					'label'   => 'entry-' . $i,
					'status'  => 'HTTP 204',
					'ok'      => true,
					'attempt' => 1,
				]
			);
		}

		$log = Node_Connect_Delivery_Log::get();
		$this->assertCount( Node_Connect_Delivery_Log::MAX_ENTRIES, $log );
		$this->assertSame( 'entry-55', $log[0]['label'], '新しい順に保存' );
	}

	// ---- Discord フォーマッタ ----

	public function test_formatter_uses_series_color_and_falls_back_to_brand(): void {
		$with_series = Node_Connect_Discord_Formatter::format(
			'post_published',
			[
				'title'        => 'T',
				'series_color' => '#3366CC',
			]
		);
		$this->assertSame( 0x3366CC, $with_series['embeds'][0]['color'] );

		$without = Node_Connect_Discord_Formatter::format( 'post_published', [ 'title' => 'T' ] );
		$this->assertSame( 0xFF9900, $without['embeds'][0]['color'] );
	}

	public function test_formatter_scheduled_publish_has_distinct_heading(): void {
		$normal    = Node_Connect_Discord_Formatter::format( 'post_published', [ 'title' => 'T' ] );
		$scheduled = Node_Connect_Discord_Formatter::format(
			'post_published',
			[
				'title'     => 'T',
				'scheduled' => true,
			]
		);
		$this->assertNotSame( $normal['content'], $scheduled['content'], '予約公開は新規公開と区別する' );
	}

	public function test_formatter_omits_link_for_deleted_post(): void {
		$deleted = Node_Connect_Discord_Formatter::format(
			'post_deleted',
			[
				'title'     => 'T',
				'permalink' => 'https://example.org/deleted-post/',
			]
		);
		$this->assertArrayNotHasKey( 'url', $deleted['embeds'][0] );
	}

	// ---- 設定サニタイズ ----

	public function test_sanitize_keeps_existing_url_when_blank(): void {
		$result = node_connect_sanitize_webhooks(
			[
				0 => [
					'label'   => '改名した',
					'url'     => '',
					'events'  => [ 'post_published' ],
					'enabled' => '1',
				],
			]
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'https://discord.com/api/webhooks/123/abcdef', $result[0]['url'], '空欄なら保存済みURLを維持' );
		$this->assertSame( '改名した', $result[0]['label'] );
	}

	public function test_sanitize_remove_flag_deletes_row(): void {
		$result = node_connect_sanitize_webhooks(
			[
				0 => [
					'label'  => 'x',
					'url'    => '',
					'remove' => '1',
				],
			]
		);

		$this->assertSame( [], $result );
	}

	public function test_sanitize_rejects_non_https_url(): void {
		update_option( Node_Connect_Event_Bus::OPTION_WEBHOOKS, [] );

		$result = node_connect_sanitize_webhooks(
			[
				0 => [
					'label' => 'x',
					'url'   => 'http://insecure.example.com/hook',
				],
			]
		);

		$this->assertSame( [], $result, 'https以外のURLは保存しない' );
	}

	public function test_sanitize_filters_unknown_events(): void {
		$result = node_connect_sanitize_webhooks(
			[
				0 => [
					'label'  => 'x',
					'url'    => 'https://discord.com/api/webhooks/9/z',
					'events' => [ 'post_published', 'not_an_event' ],
				],
			]
		);

		$this->assertSame( [ 'post_published' ], $result[0]['events'] );
	}

	// ---- ペイロード ----

	public function test_build_post_payload_contains_expected_fields(): void {
		$user_id = self::factory()->user->create( [ 'display_name' => '斎藤テスト' ] );
		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_title'   => 'ペイロード確認',
				'post_content' => str_repeat( '本文。', 100 ),
				'post_author'  => $user_id,
			]
		);
		update_post_meta( $post_id, '_node_ai_summary', 'AI要約テキスト' );

		$payload = Node_Connect_Event_Bus::build_post_payload( get_post( $post_id ) );

		$this->assertSame( $post_id, $payload['post_id'] );
		$this->assertSame( 'ペイロード確認', $payload['title'] );
		$this->assertSame( '斎藤テスト', $payload['author'] );
		$this->assertSame( get_permalink( $post_id ), $payload['permalink'] );
		$this->assertTrue( $payload['has_ai_summary'] );
		$this->assertNotSame( '', $payload['excerpt'] );
	}
}
