<?php
/**
 * メンテナンスモード。
 *
 * フロント側の表示だけを専用画面へ差し替える。wp-admin と wp-login.php は
 * 常に通常どおり動作させる（ここを塞ぐと自分で解除できなくなるため）。
 *
 * Node 1.3 Connect の構想にあった機能の先行実装。1.2.3 の同梱化不具合で
 * サイトが表示不能になった際、計画的な作業中である旨を示す手段が無かったため
 * 1.2.4 で前倒しした。
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NODE_MAINTENANCE_OPTION_ENABLED = 'node_maintenance_enabled';
const NODE_MAINTENANCE_OPTION_MESSAGE = 'node_maintenance_message';
const NODE_MAINTENANCE_OPTION_STARTED = 'node_maintenance_started_at';
const NODE_MAINTENANCE_OPTION_ETA     = 'node_maintenance_eta';
const NODE_MAINTENANCE_DEFAULT_MESSAGE = 'ただいまサイトのメンテナンスを行っています。ご不便をおかけしますが、しばらくお待ちください。';

/**
 * メンテナンスモードが有効か。
 */
function node_maintenance_is_enabled(): bool {
	return (bool) get_option( NODE_MAINTENANCE_OPTION_ENABLED, false );
}

/**
 * 画面に表示するメッセージ。未設定なら既定文。
 */
function node_maintenance_get_message(): string {
	$message = trim( (string) get_option( NODE_MAINTENANCE_OPTION_MESSAGE, '' ) );

	return '' === $message ? NODE_MAINTENANCE_DEFAULT_MESSAGE : $message;
}

/**
 * 復旧予定時刻のUNIXタイムスタンプ。未設定・過去日時なら null。
 *
 * 設定値はサイトのタイムゾーンでの「Y-m-d\TH:i」（datetime-local の値）で保存する。
 */
function node_maintenance_get_eta(): ?int {
	$raw = trim( (string) get_option( NODE_MAINTENANCE_OPTION_ETA, '' ) );

	if ( '' === $raw ) {
		return null;
	}

	try {
		$eta = new DateTimeImmutable( $raw, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}

	return $eta->getTimestamp();
}

/**
 * メンテナンス開始時刻のUNIXタイムスタンプ。未記録なら null。
 */
function node_maintenance_get_started_at(): ?int {
	$started = (int) get_option( NODE_MAINTENANCE_OPTION_STARTED, 0 );

	return $started > 0 ? $started : null;
}

/**
 * 復旧予定までの進捗（0〜100）。開始時刻か復旧予定が無い場合、
 * および予定時刻を過ぎている場合は null（＝ゲージを出さない）。
 */
function node_maintenance_get_progress(): ?int {
	$started = node_maintenance_get_started_at();
	$eta     = node_maintenance_get_eta();
	$now     = time();

	if ( null === $started || null === $eta || $eta <= $started || $now >= $eta ) {
		return null;
	}

	$progress = ( $now - $started ) / ( $eta - $started ) * 100;

	return (int) max( 0, min( 100, round( $progress ) ) );
}

/**
 * 設定項目を Luminous Settings（node_settings_group）へ登録する。
 */
function node_maintenance_register_settings(): void {
	register_setting(
		'node_settings_group',
		NODE_MAINTENANCE_OPTION_ENABLED,
		[ 'sanitize_callback' => static fn( $value ) => '1' === (string) $value ? '1' : '' ]
	);
	register_setting(
		'node_settings_group',
		NODE_MAINTENANCE_OPTION_MESSAGE,
		[ 'sanitize_callback' => static fn( $value ) => sanitize_textarea_field( (string) $value ) ]
	);
	register_setting(
		'node_settings_group',
		NODE_MAINTENANCE_OPTION_ETA,
		[ 'sanitize_callback' => 'node_maintenance_sanitize_eta' ]
	);
}
add_action( 'admin_init', 'node_maintenance_register_settings' );

/**
 * 復旧予定時刻の検証。datetime-local の形式（Y-m-d\TH:i）以外は空にする。
 *
 * @param mixed $value
 */
function node_maintenance_sanitize_eta( $value ): string {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );

	return ( false !== $parsed && $parsed->format( 'Y-m-d\TH:i' ) === $value ) ? $value : '';
}

/**
 * 有効・無効が切り替わったときの副作用（開始時刻の記録・通知）。
 *
 * Settings API 経由の保存とプログラムからの update_option の双方を拾うため、
 * option の変更フックで受ける。通知の送出は shutdown まで遅らせる:
 * 同一リクエストで復旧予定時刻も保存される場合、オプションの保存順に依存せず
 * 確定後の値を通知に載せるため。
 *
 * @param mixed $old_value
 * @param mixed $value
 */
function node_maintenance_on_toggle( $old_value, $value ): void {
	$was_enabled = ! empty( $old_value );
	$is_enabled  = ! empty( $value );

	if ( $was_enabled === $is_enabled ) {
		return;
	}

	if ( $is_enabled ) {
		update_option( NODE_MAINTENANCE_OPTION_STARTED, time() );
	} else {
		delete_option( NODE_MAINTENANCE_OPTION_STARTED );
	}

	node_maintenance_event_queue( $is_enabled );
	// 名前付き関数のため、複数回 add_action しても登録は1つに保たれる。
	add_action( 'shutdown', 'node_maintenance_flush_events' );
}

/**
 * shutdown で送出する通知のキュー。$flush で中身を取り出して空にする。
 *
 * @param bool|null $enabled 積む値。null なら積まない。
 * @param bool      $flush   true でキューを空にして中身を返す。
 * @return bool[]
 */
function node_maintenance_event_queue( ?bool $enabled = null, bool $flush = false ): array {
	static $queue = [];

	if ( $flush ) {
		$drained = $queue;
		$queue   = [];

		return $drained;
	}

	if ( null !== $enabled ) {
		$queue[] = $enabled;
	}

	return $queue;
}

/**
 * 積まれた開始・終了通知を node-connect へ送出する（未導入なら何も起きない）。
 *
 * ペイロードは cron 引数として直列化されるためスカラー値のみ。
 */
function node_maintenance_flush_events(): void {
	$eta = node_maintenance_get_eta();

	foreach ( node_maintenance_event_queue( null, true ) as $is_enabled ) {
		do_action(
			'node_connect_event',
			$is_enabled ? 'maintenance_start' : 'maintenance_end',
			[
				'message'   => node_maintenance_get_message(),
				'eta'       => null !== $eta ? wp_date( 'Y-m-d H:i', $eta ) : '',
				'site_name' => get_bloginfo( 'name' ),
				'site_url'  => home_url( '/' ),
			]
		);
	}
}
add_action( 'update_option_' . NODE_MAINTENANCE_OPTION_ENABLED, 'node_maintenance_on_toggle', 10, 2 );
add_action(
	'add_option_' . NODE_MAINTENANCE_OPTION_ENABLED,
	static function ( $option, $value ): void {
		node_maintenance_on_toggle( '', $value );
	},
	10,
	2
);

/**
 * このリクエストでメンテナンス画面を表示すべきか。
 *
 * 管理画面・ログイン画面・REST・Ajax・cron・WP-CLI は対象外。
 * 管理者も画面自体は見る（ユーザー決定）が、画面上に管理画面へのリンクを出す。
 */
function node_maintenance_should_display(): bool {
	if ( ! node_maintenance_is_enabled() ) {
		return false;
	}

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}

	// ログイン・登録・パスワード再発行は塞がない（解除手段を残すため）。
	if ( isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'wp-login.php', 'wp-register.php' ], true ) ) {
		return false;
	}

	return true;
}

/**
 * フロントの表示をメンテナンス画面へ差し替える。
 */
function node_maintenance_maybe_render(): void {
	if ( ! node_maintenance_should_display() ) {
		return;
	}

	$eta = node_maintenance_get_eta();

	nocache_headers();
	status_header( 503 );
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	// 復旧予定が分かる場合はクローラーに再訪の目安を伝える（最大24時間）。
	header( 'Retry-After: ' . ( null !== $eta ? (string) max( 60, min( DAY_IN_SECONDS, $eta - time() ) ) : '3600' ) );

	get_template_part( 'template-parts/maintenance' );
	exit;
}
add_action( 'template_redirect', 'node_maintenance_maybe_render', 0 );

/**
 * メンテナンス中は管理バーに状態を出し、解除画面への導線を作る。
 */
function node_maintenance_admin_bar( WP_Admin_Bar $admin_bar ): void {
	if ( ! node_maintenance_is_enabled() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$admin_bar->add_node(
		[
			'id'    => 'node-maintenance',
			'title' => '🚧 メンテナンス中',
			'href'  => admin_url( 'options-general.php?page=luminous-settings#node-maintenance' ),
			'meta'  => [ 'title' => 'メンテナンスモードが有効です。クリックで設定へ移動します。' ],
		]
	);
}
add_action( 'admin_bar_menu', 'node_maintenance_admin_bar', 100 );

/**
 * メンテナンス中である旨を管理画面上部で常に知らせる（解除忘れ防止）。
 */
function node_maintenance_admin_notice(): void {
	if ( ! node_maintenance_is_enabled() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$eta      = node_maintenance_get_eta();
	$eta_text = null !== $eta ? sprintf( '（復旧予定: %s）', wp_date( 'Y年n月j日 H:i', $eta ) ) : '';

	printf(
		'<div class="notice notice-warning"><p><strong>メンテナンスモードが有効です。</strong>%s 訪問者にはメンテナンス画面が表示されています。<a href="%s">設定を開く</a></p></div>',
		esc_html( $eta_text ),
		esc_url( admin_url( 'options-general.php?page=luminous-settings#node-maintenance' ) )
	);
}
add_action( 'admin_notices', 'node_maintenance_admin_notice' );
