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
        $max_lines     = isset($_POST['max_lines']) ? intval($_POST['max_lines']) : 3;
        $max_chars     = isset($_POST['max_chars']) ? intval($_POST['max_chars']) : 120;

        // 保存（将来の再生成時のデフォルト用）
        update_post_meta($post_id, '_node_ai_custom_prompt', $custom_prompt);
        update_post_meta($post_id, '_node_ai_max_lines', $max_lines);
        update_post_meta($post_id, '_node_ai_max_chars', $max_chars);

        // もし使用モデルが指定されていれば、ユーザーのデフォルト設定として保存する
        if ( isset( $_POST['gemini_model'] ) && ! empty( $_POST['gemini_model'] ) ) {
            $gemini_model = sanitize_text_field( $_POST['gemini_model'] );
            if ( function_exists('node_is_valid_gemini_model_id') && node_is_valid_gemini_model_id( $gemini_model ) ) {
                update_user_meta( get_current_user_id(), 'node_gemini_model', $gemini_model );
            }
        }

        // APIクラスを使用して要約を生成 (JSONレスポンスを期待)
        if ( class_exists( 'Node_Gemini_API' ) ) {
            $api = new Node_Gemini_API();
            $result = $api->generate_summary( $content, $custom_prompt, [
                'max_lines' => $max_lines,
                'max_chars' => $max_chars
            ] );
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

if ( ! function_exists( 'node_ai_parse_json_response' ) ) {
    /**
     * Gemini レスポンスから JSON を抽出する
     *
     * @param string $raw API 生レスポンス。
     * @return array<string, mixed>|null
     */
    function node_ai_parse_json_response( string $raw ): ?array {
        $clean = preg_replace( '/^```(?:json)?\s*/i', '', $raw );
        $clean = preg_replace( '/```\s*$/', '', (string) $clean );
        $clean = trim( (string) $clean );
        $data  = json_decode( $clean, true );

        return ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) ? $data : null;
    }
}

if ( ! function_exists( 'node_ai_ajax_fact_check' ) ) {
    /**
     * ファクトチェック AJAX ハンドラ
     */
    function node_ai_ajax_fact_check(): void {
        check_ajax_referer( 'node_ai_fact_check_action', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => '不正な投稿IDです。' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => '記事が見つかりません。' ) );
        }

        $content = strip_shortcodes( strip_tags( $post->post_content ) );
        if ( empty( trim( $content ) ) ) {
            wp_send_json_error( array( 'message' => '記事本文が空です。' ) );
        }

        // もし使用モデルが指定されていれば、ユーザーのデフォルト設定として保存する
        if ( isset( $_POST['gemini_model'] ) && ! empty( $_POST['gemini_model'] ) ) {
            $gemini_model = sanitize_text_field( $_POST['gemini_model'] );
            if ( function_exists('node_is_valid_gemini_model_id') && node_is_valid_gemini_model_id( $gemini_model ) ) {
                update_user_meta( get_current_user_id(), 'node_gemini_model', $gemini_model );
            }
        }

        if ( ! class_exists( 'Node_Gemini_API' ) ) {
            wp_send_json_error( array( 'message' => 'APIクラスが見つかりません。' ) );
        }

        $api    = new Node_Gemini_API();
        $result = $api->fact_check( $content, $post->post_title );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $data = node_ai_parse_json_response( (string) ( $result['text'] ?? '' ) );
        if ( null === $data || empty( $data['claims'] ) || ! is_array( $data['claims'] ) ) {
            wp_send_json_error( array( 'message' => 'ファクトチェック結果の解析に失敗しました。' ) );
        }

        $grounding = is_array( $result['grounding'] ?? null ) ? $result['grounding'] : array();
        $sources   = function_exists( 'node_ai_extract_grounding_sources' )
            ? node_ai_extract_grounding_sources( $grounding )
            : array();

        $payload = array(
            'summary'         => sanitize_text_field( (string) ( $data['summary'] ?? '' ) ),
            'overall_risk'    => sanitize_key( (string) ( $data['overall_risk'] ?? 'medium' ) ),
            'claims'          => array(),
            'sources'         => $sources,
            'search_queries'  => array_map( 'sanitize_text_field', (array) ( $grounding['webSearchQueries'] ?? array() ) ),
            'grounded'        => ! empty( $sources ) || ! empty( $grounding['webSearchQueries'] ),
            'guidelines_used' => ! empty( $result['guidelines_used'] ),
            'checked_at'      => current_time( 'mysql' ),
        );

        foreach ( $data['claims'] as $claim ) {
            if ( ! is_array( $claim ) ) {
                continue;
            }
            $payload['claims'][] = array(
                'claim'      => sanitize_text_field( (string) ( $claim['claim'] ?? '' ) ),
                'status'     => sanitize_key( (string) ( $claim['status'] ?? 'uncertain' ) ),
                'confidence' => sanitize_key( (string) ( $claim['confidence'] ?? 'low' ) ),
                'note'       => sanitize_textarea_field( (string) ( $claim['note'] ?? '' ) ),
            );
        }

        if ( empty( $payload['claims'] ) ) {
            wp_send_json_error( array( 'message' => '検証対象の主張が見つかりませんでした。' ) );
        }

        update_post_meta( $post_id, '_node_ai_fact_check', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_node_ai_fact_check_approved', '' );

        wp_send_json_success( $payload );
    }
}

// 登録はプラグインのメインファイルで行う
