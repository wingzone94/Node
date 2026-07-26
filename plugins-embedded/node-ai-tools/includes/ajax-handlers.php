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
            $attempts = (int) get_post_meta( $post_id, '_node_ai_summary_attempts', true );
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

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        // text/plain 応答ではJSONの前後に説明文が付くことがあるため、最初の { から最後の } までを抽出して再試行
        $start = strpos( $clean, '{' );
        $end   = strrpos( $clean, '}' );
        if ( false !== $start && false !== $end && $end > $start ) {
            $data = json_decode( substr( $clean, $start, $end - $start + 1 ), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }
        }

        return null;
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

        // 整形・保存は自動実行（cron）と共通の処理を使う（includes/auto-check.php）
        $payload = node_ai_store_fact_check_result( $post_id, $result );
        if ( is_wp_error( $payload ) ) {
            wp_send_json_error( array( 'message' => $payload->get_error_message() ) );
        }

        wp_send_json_success( $payload );
    }
}

if ( ! function_exists( 'node_ai_get_proofread_data' ) ) {
    /**
     * 保存済みの校正結果を返す
     *
     * @param int $post_id 投稿ID。
     * @return array<string, mixed>|null
     */
    function node_ai_get_proofread_data( int $post_id ): ?array {
        $stored = get_post_meta( $post_id, '_node_ai_proofread', true );
        if ( ! is_string( $stored ) || '' === $stored ) {
            return null;
        }

        $data = json_decode( $stored, true );

        return is_array( $data ) && isset( $data['issues'] ) ? $data : null;
    }
}

if ( ! function_exists( 'node_ai_ajax_proofread' ) ) {
    /**
     * 誤字脱字・校正チェック AJAX ハンドラ（AI Core 経由）
     *
     * 読者からの要望で 1.3 に追加。ファクトチェックと違い公開ゲートには絡めず、
     * 執筆者向けの指摘表示のみを行う（修正の適用は人間が判断する）。
     */
    function node_ai_ajax_proofread(): void {
        check_ajax_referer( 'node_ai_proofread_action', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post    = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => '記事が見つかりません。' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => 'この記事を編集する権限がありません。' ) );
        }

        // エディタ上の未保存本文を優先し、無ければ保存済み本文を使う
        $content = isset( $_POST['content'] ) && '' !== trim( (string) wp_unslash( $_POST['content'] ) )
            ? (string) wp_unslash( $_POST['content'] )
            : (string) $post->post_content;

        $content = trim( strip_shortcodes( wp_strip_all_tags( $content ) ) );
        if ( '' === $content ) {
            wp_send_json_error( array( 'message' => '記事本文が空です。' ) );
        }

        if ( ! function_exists( 'node_ai_core' ) ) {
            wp_send_json_error( array( 'message' => 'AI Core が読み込まれていません。' ) );
        }

        $core = node_ai_core();
        if ( ! $core->is_enabled() ) {
            wp_send_json_error( array( 'message' => 'AI機能が無効です。設定 → Node AI から有効化してください。' ) );
        }

        $result = $core->proofread( $content, get_current_user_id(), $post_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $raw = is_array( $result ) ? (string) ( $result['text'] ?? '' ) : (string) $result;

        $data = node_ai_parse_json_response( $raw );
        if ( null === $data || ! isset( $data['issues'] ) || ! is_array( $data['issues'] ) ) {
            wp_send_json_error( array( 'message' => '校正結果の解析に失敗しました。' ) );
        }

        $payload = array(
            'summary'    => sanitize_text_field( (string) ( $data['summary'] ?? '' ) ),
            'issues'     => array(),
            'checked_at' => current_time( 'mysql' ),
        );

        foreach ( $data['issues'] as $issue ) {
            if ( ! is_array( $issue ) ) {
                continue;
            }

            $original = sanitize_text_field( (string) ( $issue['original'] ?? '' ) );
            if ( '' === $original ) {
                continue;
            }

            // 種別はアイコン表示に使う。想定外の値は typo 扱いにフォールバック
            $type = sanitize_key( (string) ( $issue['type'] ?? '' ) );
            if ( ! in_array( $type, array( 'typo', 'grammar', 'usage', 'style', 'readability', 'meaning' ), true ) ) {
                $type = 'typo';
            }

            $payload['issues'][] = array(
                'type'       => $type,
                'original'   => $original,
                'suggestion' => sanitize_text_field( (string) ( $issue['suggestion'] ?? '' ) ),
                'reason'     => sanitize_textarea_field( (string) ( $issue['reason'] ?? '' ) ),
                'severity'   => sanitize_key( (string) ( $issue['severity'] ?? 'low' ) ),
            );
        }

        update_post_meta( $post_id, '_node_ai_proofread', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );

        wp_send_json_success( $payload );
    }
}

// 登録はプラグインのメインファイルで行う
