<?php
/**
 * Plugin Name:  Luminous Interactivity
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  スポイラー（目隠し）、しおり機能、CERO Z 年齢確認ダイアログ。Luminous Core テーマと連携。
 * Version:      1.0.0
 * Author:       Luminous Core Teams
 * Author URI:   https://github.com/wingzone94
 * License:      MIT
 * Text Domain:  luminous-interactivity
 * Requires PHP: 8.0
 *
 * @package Luminous_Interactivity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUMINOUS_INTER_VERSION', '1.0.0' );
define( 'LUMINOUS_INTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMINOUS_INTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * プラグイン初期化
 */
final class Luminous_Interactivity {

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
		require_once LUMINOUS_INTER_DIR . 'includes/spoiler.php';
		require_once LUMINOUS_INTER_DIR . 'includes/bookmark.php';
		require_once LUMINOUS_INTER_DIR . 'includes/cero-z.php';

		if ( is_admin() ) {
			require_once LUMINOUS_INTER_DIR . 'admin/meta-box-cero-z.php';
		}
	}

	private function register_hooks(): void {
		// テーマの Hook に応答: CERO Z 判定
		add_filter( 'luminous_content_requires_age_gate', [ $this, 'check_age_gate' ], 10, 2 );

		// テーマの Hook に応答: 年齢確認ダイアログの出力
		add_action( 'luminous_render_age_gate', [ $this, 'render_age_gate_dialog' ] );

		// フロントエンドアセットの読み込み
		add_action( 'luminous_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// ショートコード
		add_shortcode( 'spoiler', 'luminous_spoiler_shortcode' );
	}

	public function check_age_gate( bool $requires, int $post_id ): bool {
		if ( get_post_meta( $post_id, '_node_is_cero_z', true ) === '1' ) {
			return true;
		}
		return $requires;
	}

	public function render_age_gate_dialog( int $post_id ): void {
		luminous_cero_z_render_dialog( $post_id );
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'luminous-interactivity',
			LUMINOUS_INTER_URL . 'assets/css/interactivity.css',
			[],
			LUMINOUS_INTER_VERSION
		);
		wp_enqueue_script(
			'luminous-interactivity',
			LUMINOUS_INTER_URL . 'assets/js/interactivity.js',
			[],
			LUMINOUS_INTER_VERSION,
			true
		);
	}
}

/**
 * プラグイン起動
 */
function luminous_interactivity_init(): void {
	Luminous_Interactivity::instance();
}
add_action( 'plugins_loaded', 'luminous_interactivity_init' );
