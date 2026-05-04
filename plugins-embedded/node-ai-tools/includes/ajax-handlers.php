<?php
/**
 * AJAX ハンドラ
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_ai_ajax_generate_summary' ) ) {
    /**
     * AI 要約生成 AJAX ハンドラ
     */
    function node_ai_ajax_generate_summary() {
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

        $custom_prompt = isset($_POST['custom_prompt']) ? sanitize_text_field($_POST['custom_prompt']) : '';

        // 生成回数上限チェック（1記事あたり3回まで）
        $attempts = (int) get_post_meta($post_id, '_node_ai_summary_attempts', true);
        if ($attempts >= 3) {
            wp_send_json_error(['message' => '本記事のAI要約生成は上限に達しました（3回）'] );
            return;
        }

        // APIクラスを使用して要約を生成 (JSONレスポンスを期待)
        if ( class_exists( 'Node_Gemini_API' ) ) {
            $api = new Node_Gemini_API();
            $result = $api->generate_summary( $content, $custom_prompt );
        } else {
            wp_send_json_error( [ 'message' => 'APIクラスが見つかりません。' ] );
            return;
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }

        // 余計なマークダウン記号（```json ... ```）が混入した場合は除去
        $clean_result = preg_replace( '/^```(?:json)?\s*/i', '', $result );
        $clean_result = preg_replace( '/```\s*$/', '', $clean_result );
        $clean_result = trim( $clean_result );

        $data = json_decode( $clean_result, true );

        if ( json_last_error() === JSON_ERROR_NONE && isset( $data['summary'] ) ) {
            // 保存
            update_post_meta( $post_id, '_node_ai_summary', sanitize_textarea_field( $data['summary'] ) );
            // カウンタ更新
            update_post_meta( $post_id, '_node_ai_summary_attempts', $attempts + 1 );
            update_post_meta( $post_id, '_node_ai_summary_last_attempt', current_time( 'mysql' ) );

            if ( isset( $data['tone_color'] ) ) {
                update_post_meta( $post_id, '_node_ai_tone_color', sanitize_hex_color( $data['tone_color'] ) );
            }
            if ( isset( $data['vibe_keywords'] ) ) {
                update_post_meta( $post_id, '_node_ai_keywords', (array) $data['vibe_keywords'] );
            }

            wp_send_json_success( [
                'summary'    => $data['summary'],
                'tone_color' => $data['tone_color'] ?? '',
                'keywords'   => $data['vibe_keywords'] ?? [],
            ] );
            return;
        }

                // JSON パース失敗時のフォールバック (生データを要約として扱う)
        update_post_meta( $post_id, '_node_ai_summary', sanitize_textarea_field( $result ) );
        wp_send_json_success( [ 'summary' => $result ] );
    }
}

// 以前の名称（node_ajax_generate_ai_summary）が残っている場合はここから削除
// 登録はプラグインのメインファイルで行う
