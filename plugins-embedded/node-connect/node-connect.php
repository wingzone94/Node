<?php
/**
 * Plugin Name:  Node Connect
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  外部サービス連携基盤（ベータ版）。記事の公開・更新などのイベントを Webhook（Discord）へ通知する。Node テーマと連携。
 * Version:      1.3.3
 * Author:       Luminous Core Teams
 * Author URI:   https://github.com/wingzone94
 * License:      MIT
 * Text Domain:  node-connect
 * Requires PHP: 8.0
 *
 * @package Node_Connect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NODE_CONNECT_VERSION', '1.3.3' );
define( 'NODE_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'NODE_CONNECT_MAX_WEBHOOKS', 3 );

/**
 * プラグイン初期化
 */
final class Node_Connect {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		require_once NODE_CONNECT_DIR . 'includes/class-delivery-log.php';
		require_once NODE_CONNECT_DIR . 'includes/class-discord-formatter.php';
		require_once NODE_CONNECT_DIR . 'includes/class-webhook-sender.php';
		require_once NODE_CONNECT_DIR . 'includes/class-event-bus.php';
		require_once NODE_CONNECT_DIR . 'includes/class-x-poster.php';

		if ( is_admin() ) {
			require_once NODE_CONNECT_DIR . 'admin/settings-page.php';
			require_once NODE_CONNECT_DIR . 'admin/meta-box-x-post.php';
		}
	}

	private function register_hooks(): void {
		Node_Connect_Event_Bus::instance()->register();
		Node_Connect_X_Poster::instance()->register();

		// Webhook通知・X自動投稿とも無効なら投稿イベントの監視自体を登録しない（§1.2）。
		if ( ! Node_Connect_Event_Bus::is_enabled() && ! Node_Connect_X_Poster::is_enabled() ) {
			return;
		}

		add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 10, 3 );
		add_action( 'post_updated', [ $this, 'on_post_updated' ], 10, 3 );
		add_action( 'wp_trash_post', [ $this, 'on_trash_post' ] );
		add_action( 'before_delete_post', [ $this, 'on_before_delete_post' ], 10, 2 );
	}

	/**
	 * 新規公開（予約公開含む）・非公開化を検知する。
	 */
	public function on_transition_post_status( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$event = Node_Connect_Event_Bus::classify_transition( $new_status, $old_status );
		if ( null === $event ) {
			return;
		}

		$payload              = Node_Connect_Event_Bus::build_post_payload( $post );
		$payload['scheduled'] = ( 'future' === $old_status );

		Node_Connect_Event_Bus::dispatch( $event, $payload );
	}

	/**
	 * 公開済み記事の更新（publish→publish）。リビジョン・自動保存は除外。
	 */
	public function on_post_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		if ( 'post' !== $post_after->post_type ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) {
			return;
		}

		Node_Connect_Event_Bus::dispatch(
			Node_Connect_Event_Bus::EVENT_POST_UPDATED,
			Node_Connect_Event_Bus::build_post_payload( $post_after )
		);
	}

	/**
	 * 公開済み記事のゴミ箱移動を「記事の削除」として通知する。
	 */
	public function on_trash_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		Node_Connect_Event_Bus::dispatch(
			Node_Connect_Event_Bus::EVENT_POST_DELETED,
			Node_Connect_Event_Bus::build_post_payload( $post )
		);
	}

	/**
	 * ゴミ箱を経由しない完全削除。公開状態から直接削除された場合のみ通知する
	 * （ゴミ箱経由は on_trash_post で通知済みのため二重通知しない）。
	 */
	public function on_before_delete_post( int $post_id, ?WP_Post $post = null ): void {
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		Node_Connect_Event_Bus::dispatch(
			Node_Connect_Event_Bus::EVENT_POST_DELETED,
			Node_Connect_Event_Bus::build_post_payload( $post )
		);
	}
}

add_action( 'plugins_loaded', [ 'Node_Connect', 'instance' ] );
