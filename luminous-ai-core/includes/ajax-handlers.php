<?php
/**
 * AJAX ハンドラ
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'luminous_ai_ajax_generate_summary' ) ) {
    /**
     * AI 要約生成 AJAX ハンドラ
     */
    function luminous_ai_ajax_generate_summary() {
        check_ajax_referer('node_ai_generate_action', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません。']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => '不正な投稿IDです。']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => '記事が見つかりません。']);
        }

        $content = strip_shortcodes(strip_tags($post->post_content));
        if (empty(trim($content))) {
            wp_send_json_error(['message' => '記事本文が空です。']);
        }

        // APIクラスを使用して要約を生成
        if ( class_exists( 'Luminous_Gemini_API' ) ) {
            $api = new Luminous_Gemini_API();
            $result = $api->generate_summary($content);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            } else {
                wp_send_json_success(['summary' => $result]);
            }
        } else {
            wp_send_json_error(['message' => 'APIクラスが見つかりません。']);
        }
    }
}

// 以前の名称（node_ajax_generate_ai_summary）が残っている場合はここから削除
// 登録はプラグインのメインファイルで行う
