<?php
/**
 * Plugin Name:  Node AI Tools
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  Gemini API 連携による AI 要約生成・ファクトチェック補助・読了時間自動計算。Node テーマと連携。
 * Version:      1.2.0
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

define( 'NODE_AI_VERSION', '1.2.0' );
define( 'NODE_AI_DIR', plugin_dir_path( __FILE__ ) );

$node_ai_embedded_dir = get_template_directory() . '/plugins-embedded/node-ai-tools/';
define(
	'NODE_AI_URL',
	is_dir( $node_ai_embedded_dir )
		? get_template_directory_uri() . '/plugins-embedded/node-ai-tools/'
		: content_url( '/plugins/node-ai-tools/' )
);

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
		require_once NODE_AI_DIR . 'includes/guidelines-fetcher.php';
		require_once NODE_AI_DIR . 'includes/class-gemini-api.php';
		require_once NODE_AI_DIR . 'includes/fact-check-render.php';
		require_once NODE_AI_DIR . 'includes/ajax-handlers.php';
		require_once NODE_AI_DIR . 'includes/meta-handlers.php';

		if ( is_admin() ) {
			require_once NODE_AI_DIR . 'admin/meta-box-ai-summary.php';
			require_once NODE_AI_DIR . 'admin/meta-box-fact-check.php';
			require_once NODE_AI_DIR . 'admin/meta-box-featured-image-ai.php';
		}
	}

	private function register_hooks(): void {
		// テーマの Hook に応答: AI 要約テキストを提供
		add_filter( 'luminous_get_ai_summary', [ $this, 'provide_ai_summary' ], 10, 2 );

		// テーマの Hook に応答: 読了時間を提供
		add_filter( 'luminous_get_reading_time', [ $this, 'provide_reading_time' ], 10, 2 );

		// 投稿保存時に読了時間を自動計算
		add_action( 'save_post', [ $this, 'auto_calculate_reading_time' ], 20, 3 );

        // 投稿保存時にAI要約（キャッチコピー含む）を自動生成（手動トリガーに変更）
        // add_action( 'save_post', [ $this, 'auto_generate_ai_summary' ], 25, 3 );

        // AJAX ハンドラの登録
        add_action( 'wp_ajax_node_generate_ai_summary', 'node_ai_ajax_generate_summary' );
        add_action( 'wp_ajax_node_ai_fact_check', 'node_ai_ajax_fact_check' );

        // フロント: 編集者確認済みファクトチェックを記事ヘッダー直後に表示
        add_action( 'luminous_after_article_header', [ $this, 'render_front_fact_check' ], 12 );

        // メタボックス・エディタ拡張
        if ( is_admin() ) {
            add_action( 'add_meta_boxes', [ $this, 'add_ai_meta_boxes' ] );
            add_action( 'save_post', 'node_ai_save_meta' );
            add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
        }
	}

    /**
     * ブロックエディタ用アセット
     */
    public function enqueue_block_editor_assets(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_script(
            'node-ai-editor-featured-image',
            NODE_AI_URL . 'assets/js/editor-featured-image.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
            NODE_AI_VERSION,
            true
        );
    }

    /**
     * 単一記事ページにファクトチェックを表示
     *
     * @param int $post_id 投稿ID。
     */
    public function render_front_fact_check( int $post_id ): void {
        if ( ! is_singular( 'post' ) || ! $post_id ) {
            return;
        }

        if ( max( 1, (int) get_query_var( 'page' ) ) !== 1 ) {
            return;
        }

        node_ai_render_fact_check_front( $post_id );
    }

    /**
     * 投稿タイプでブロックエディタが有効か
     */
    private function uses_block_editor_for_posts(): bool {
        return function_exists( 'use_block_editor_for_post_type' )
            && use_block_editor_for_post_type( 'post' );
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
        add_meta_box(
            'node_ai_fact_check',
            'Fact Check (ファクトチェック)',
            'node_ai_render_fact_check_meta_box',
            'post',
            'normal',
            'default'
        );

        // ブロックエディタでは PluginDocumentSettingPanel を使うためメタボックスは出さない
        if ( ! $this->uses_block_editor_for_posts() ) {
            add_meta_box(
                'node_ai_featured_image',
                'AI アイキャッチ',
                'node_ai_render_featured_image_meta_box',
                'post',
                'side',
                'low'
            );
        }
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
        
        // REST API 経由（エディタ保存時）は、同期処理によるタイムアウトを避けるためスキップ
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

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
