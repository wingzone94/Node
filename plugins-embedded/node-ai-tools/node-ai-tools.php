<?php
/**
 * Plugin Name:  Node AI Tools
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  Gemini API 連携による AI 要約生成・読了時間自動計算。Luminous Core テーマと連携。
 * Version:      1.0.0
 * Author:       Luminous Core Teams
 * Author URI:   https://github.com/wingzone94
 * License:      MIT
 * Text Domain:  node-ai-tools
 * Requires PHP: 8.0
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NODE_AI_VERSION', '1.0.0' );
define( 'NODE_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'NODE_AI_URL', plugin_dir_url( __FILE__ ) );

/**
 * プラグイン初期化
 */
final class Node_AI_Tools {

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
		require_once NODE_AI_DIR . 'includes/class-gemini-api.php';
		require_once NODE_AI_DIR . 'includes/ajax-handlers.php';
		require_once NODE_AI_DIR . 'includes/meta-handlers.php';

		if ( is_admin() ) {
			require_once NODE_AI_DIR . 'admin/meta-box-ai-summary.php';
		}
	}

	private function register_hooks(): void {
		// テーマの Hook に応答: AI 要約テキストを提供
		add_filter( 'luminous_get_ai_summary', [ $this, 'provide_ai_summary' ], 10, 2 );

		// テーマの Hook に応答: 読了時間を提供
		add_filter( 'luminous_get_reading_time', [ $this, 'provide_reading_time' ], 10, 2 );

		// 投稿保存時に読了時間を自動計算
		add_action( 'save_post', [ $this, 'auto_calculate_reading_time' ], 20, 3 );

        // 投稿保存時にAI要約（キャッチコピー含む）を自動生成（未生成の場合のみ）
        add_action( 'save_post', [ $this, 'auto_generate_ai_summary' ], 25, 3 );

        // AJAX ハンドラの登録
        add_action( 'wp_ajax_node_generate_ai_summary', 'node_ai_ajax_generate_summary' );

        // メタボックス登録
        if ( is_admin() ) {
            add_action( 'add_meta_boxes', [ $this, 'add_ai_meta_boxes' ] );
        }
	}

    public function add_ai_meta_boxes(): void {
        add_meta_box(
            'node_ai_summary',
            'Intelligence Summary (AI要約)',
            'node_ai_render_summary_meta_box',
            'post',
            'side',
            'high'
        );
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
		if ( function_exists( 'node_ai_calculate_reading_time' ) ) {
            node_ai_calculate_reading_time( $post_id, $post, $update );
        }
	}

    public function auto_generate_ai_summary( int $post_id, \WP_Post $post, bool $update ): void {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) return;

        // すでに要約が存在するかチェック
        $existing_summary = get_post_meta($post_id, '_node_ai_summary', true);
        if ( ! empty($existing_summary) ) return;

        // 本文の取得（ショートコードとタグを除去）
        $content = strip_shortcodes(strip_tags($post->post_content));
        if ( empty(trim($content)) ) return;

        if ( class_exists( 'Node_Gemini_API' ) ) {
            $api = new Node_Gemini_API();
            $result = $api->generate_summary($content);

            if ( ! is_wp_error($result) ) {
                $clean_result = preg_replace('/^```(?:json)?\s*/i', '', $result);
                $clean_result = preg_replace('/```\s*$/', '', $clean_result);
                $clean_result = trim($clean_result);

                $data = json_decode($clean_result, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['summary'])) {
                    update_post_meta($post_id, '_node_ai_summary', sanitize_textarea_field($data['summary']));
                    
                    if (isset($data['tone_color'])) {
                        update_post_meta($post_id, '_node_ai_tone_color', sanitize_hex_color($data['tone_color']));
                    }
                    if (isset($data['vibe_keywords'])) {
                        update_post_meta($post_id, '_node_ai_keywords', (array) $data['vibe_keywords']);
                    }
                }
            }
        }
    }
}

/**
 * プラグイン起動
 */
function node_ai_core_init(): void {
	Node_AI_Tools::instance();
}
add_action( 'plugins_loaded', 'node_ai_core_init' );
