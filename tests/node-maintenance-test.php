<?php
/**
 * メンテナンスモード（inc/maintenance.php）の自動テスト。
 *
 * @package Luminous_Core
 */

class Node_Maintenance_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// 送出キューはPHPの静的変数のためテスト間で持ち越される。各テストの前に空にする。
		node_maintenance_event_queue( null, true );
	}

	public function tear_down() {
		delete_option( NODE_MAINTENANCE_OPTION_ENABLED );
		delete_option( NODE_MAINTENANCE_OPTION_MESSAGE );
		delete_option( NODE_MAINTENANCE_OPTION_ETA );
		delete_option( NODE_MAINTENANCE_OPTION_STARTED );
		node_maintenance_event_queue( null, true );
		parent::tear_down();
	}

	// --- 有効・無効の判定 -----------------------------------------------------

	public function test_disabled_by_default(): void {
		$this->assertFalse( node_maintenance_is_enabled() );
		$this->assertFalse( node_maintenance_should_display() );
	}

	public function test_enabled_displays_on_front(): void {
		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );

		$this->assertTrue( node_maintenance_is_enabled() );
		$this->assertTrue( node_maintenance_should_display() );
	}

	public function test_admin_screen_is_never_blocked(): void {
		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		set_current_screen( 'dashboard' );

		$this->assertFalse( node_maintenance_should_display(), '管理画面は塞がない（解除手段を残すため）' );

		set_current_screen( 'front' );
	}

	/**
	 * 管理者もメンテナンス画面を見る（ユーザー決定の仕様）。
	 */
	public function test_administrator_also_sees_maintenance_screen(): void {
		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertTrue( node_maintenance_should_display() );

		wp_set_current_user( 0 );
	}

	// --- メッセージ -----------------------------------------------------------

	public function test_message_falls_back_to_default(): void {
		$this->assertSame( NODE_MAINTENANCE_DEFAULT_MESSAGE, node_maintenance_get_message() );

		update_option( NODE_MAINTENANCE_OPTION_MESSAGE, '  ' );
		$this->assertSame( NODE_MAINTENANCE_DEFAULT_MESSAGE, node_maintenance_get_message(), '空白のみも既定文へ' );

		update_option( NODE_MAINTENANCE_OPTION_MESSAGE, 'サーバー移設中です' );
		$this->assertSame( 'サーバー移設中です', node_maintenance_get_message() );
	}

	// --- 復旧予定時刻 ---------------------------------------------------------

	public function test_eta_returns_null_when_unset_or_invalid(): void {
		$this->assertNull( node_maintenance_get_eta() );

		update_option( NODE_MAINTENANCE_OPTION_ETA, 'not-a-date' );
		$this->assertNull( node_maintenance_get_eta() );
	}

	public function test_eta_is_parsed_in_site_timezone(): void {
		$expected = new DateTimeImmutable( '2026-08-01 03:30', wp_timezone() );
		update_option( NODE_MAINTENANCE_OPTION_ETA, '2026-08-01T03:30' );

		$this->assertSame( $expected->getTimestamp(), node_maintenance_get_eta() );
	}

	public function test_sanitize_eta_rejects_malformed_values(): void {
		$this->assertSame( '2026-08-01T03:30', node_maintenance_sanitize_eta( '2026-08-01T03:30' ) );
		$this->assertSame( '', node_maintenance_sanitize_eta( '2026-08-01 03:30' ), 'Tなし形式は不可' );
		$this->assertSame( '', node_maintenance_sanitize_eta( '2026-13-45T99:99' ), '存在しない日時は不可' );
		$this->assertSame( '', node_maintenance_sanitize_eta( '' ) );
	}

	// --- 進捗ゲージ -----------------------------------------------------------

	public function test_progress_is_null_without_start_or_eta(): void {
		$this->assertNull( node_maintenance_get_progress() );

		update_option( NODE_MAINTENANCE_OPTION_STARTED, time() - HOUR_IN_SECONDS );
		$this->assertNull( node_maintenance_get_progress(), '復旧予定が無ければゲージを出さない' );
	}

	public function test_progress_reflects_elapsed_ratio(): void {
		// 開始2時間前・復旧予定2時間後 ＝ ちょうど半分。
		update_option( NODE_MAINTENANCE_OPTION_STARTED, time() - 2 * HOUR_IN_SECONDS );
		update_option(
			NODE_MAINTENANCE_OPTION_ETA,
			( new DateTimeImmutable( '@' . ( time() + 2 * HOUR_IN_SECONDS ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' )
		);

		$progress = node_maintenance_get_progress();

		$this->assertNotNull( $progress );
		$this->assertGreaterThanOrEqual( 49, $progress );
		$this->assertLessThanOrEqual( 51, $progress );
	}

	public function test_progress_is_null_after_eta_passed(): void {
		update_option( NODE_MAINTENANCE_OPTION_STARTED, time() - 2 * HOUR_IN_SECONDS );
		update_option(
			NODE_MAINTENANCE_OPTION_ETA,
			( new DateTimeImmutable( '@' . ( time() - HOUR_IN_SECONDS ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' )
		);

		$this->assertNull( node_maintenance_get_progress(), '予定時刻を過ぎたらゲージは出さない' );
	}

	// --- 切り替え時の副作用 ---------------------------------------------------

	public function test_enabling_records_start_time_and_disabling_clears_it(): void {
		$this->assertNull( node_maintenance_get_started_at() );

		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		$started = node_maintenance_get_started_at();
		$this->assertNotNull( $started );
		$this->assertEqualsWithDelta( time(), $started, 5 );

		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '' );
		$this->assertNull( node_maintenance_get_started_at() );
	}

	/**
	 * 開始・終了で node-connect のイベントが発火する（購読側の有無に依存しない）。
	 * 発火は shutdown へ遅延させているため、明示的にフラッシュして確認する。
	 */
	public function test_toggle_dispatches_connect_events(): void {
		$events = [];
		add_action(
			'node_connect_event',
			static function ( $event, $payload ) use ( &$events ): void {
				$events[] = [ $event, $payload ];
			},
			10,
			2
		);

		update_option( NODE_MAINTENANCE_OPTION_ETA, '2026-08-01T03:30' );
		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		node_maintenance_flush_events();

		$this->assertCount( 1, $events );
		$this->assertSame( 'maintenance_start', $events[0][0] );
		$this->assertNotEmpty( $events[0][1]['eta'], '復旧予定が通知ペイロードに載る' );
		$this->assertSame( home_url( '/' ), $events[0][1]['site_url'] );

		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '' );
		node_maintenance_flush_events();

		$this->assertCount( 2, $events );
		$this->assertSame( 'maintenance_end', $events[1][0] );
	}

	public function test_no_event_when_value_unchanged(): void {
		$events = [];
		add_action(
			'node_connect_event',
			static function ( $event ) use ( &$events ): void {
				$events[] = $event;
			},
			10,
			2
		);

		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		node_maintenance_flush_events();
		$count_after_enable = count( $events );

		// 同じ値で再保存しても再通知しない。
		update_option( NODE_MAINTENANCE_OPTION_ENABLED, '1' );
		node_maintenance_flush_events();

		$this->assertCount( $count_after_enable, $events );
	}
}
