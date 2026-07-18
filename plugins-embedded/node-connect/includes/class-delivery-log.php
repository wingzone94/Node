<?php
/**
 * Node Connect 送信履歴。
 *
 * 直近 MAX_ENTRIES 件を option に保存し、設定画面に一覧表示する。
 * Webhook URL そのものは記録しない（宛先ラベルのみ）。
 *
 * @package Node_Connect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Node_Connect_Delivery_Log {

	public const OPTION_KEY  = 'node_connect_delivery_log';
	public const MAX_ENTRIES = 50;

	/**
	 * 履歴を先頭に追記する（新しい順）。
	 *
	 * @param array{event: string, label: string, status: string, ok: bool, attempt: int} $entry
	 */
	public static function add( array $entry ): void {
		$log = self::get();

		array_unshift(
			$log,
			[
				'time'    => current_time( 'mysql' ),
				'event'   => (string) ( $entry['event'] ?? '' ),
				'label'   => (string) ( $entry['label'] ?? '' ),
				'status'  => (string) ( $entry['status'] ?? '' ),
				'ok'      => (bool) ( $entry['ok'] ?? false ),
				'attempt' => (int) ( $entry['attempt'] ?? 1 ),
			]
		);

		update_option( self::OPTION_KEY, array_slice( $log, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get(): array {
		$log = get_option( self::OPTION_KEY, [] );
		return is_array( $log ) ? $log : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
