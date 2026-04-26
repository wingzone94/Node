<?php
/**
 * Plugin Name:  Luminous AI Core
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  Gemini API 連携による AI 要約生成・読了時間自動計算。Luminous Core テーマと連携。
 * Version:      1.0.0
 * Author:       Luminous Core Teams
 * Author URI:   https://github.com/wingzone94
 * License:      MIT
 * Text Domain:  luminous-ai-core
 * Requires PHP: 8.0
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUMINOUS_AI_VERSION', '1.0.0' );
define( 'LUMINOUS_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMINOUS_AI_URL', plugin_dir_url( __FILE__ ) );

/**
 * プラグイン初期化
 */
final class Luminous_AI_Core {

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
		require_once LUMINOUS_AI_DIR . 'includes/class-gemini-api.php';
		require_once LUMINOUS_AI_DIR . 'includes/ajax-handlers.php';
		require_once LUMINOUS_AI_DIR . 'includes/meta-handlers.php';

		if ( is_admin() ) {
			require_once LUMINOUS_AI_DIR . 'admin/meta-box-ai-summary.php';
		}
	}

	private function register_hooks(): void {
		// テーマの Hook に応答: AI 要約テキストを提供
		add_filter( 'luminous_get_ai_summary', [ $this, 'provide_ai_summary' ], 10, 2 );

		// テーマの Hook に応答: 読了時間を提供
		add_filter( 'luminous_get_reading_time', [ $this, 'provide_reading_time' ], 10, 2 );

		// 投稿保存時に読了時間を自動計算
		add_action( 'save_post', [ $this, 'auto_calculate_reading_time' ], 20, 3 );

		// AJAX: AI 要約生成
		add_action( 'wp_ajax_node_generate_ai_summary', 'luminous_ai_ajax_generate_summary' );
	}

	public function provide_ai_summary( string $summary, int $post_id ): string {
		$stored = get_post_meta( $post_id, '_node_ai_summary', true );
		return ! empty( $stored ) ? $stored : $summary;
	}

	public function provide_reading_time( string $time, int $post_id ): string {
		$stored = get_post_meta( $post_id, '_node_reading_time', true );
		return ! empty( $stored ) ? $stored : $time;
	}

	public function auto_calculate_reading_time( int $post_id, \WP_Post $post, bool $update ): void {
		luminous_ai_calculate_reading_time( $post_id, $post, $update );
	}
}

/**
 * プラグイン起動
 */
function luminous_ai_core_init(): void {
	Luminous_AI_Core::instance();
}
add_action( 'plugins_loaded', 'luminous_ai_core_init' );
