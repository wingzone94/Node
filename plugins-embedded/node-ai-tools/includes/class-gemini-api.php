<?php
/**
 * Gemini API 通信クラス
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Node_Gemini_API
 */
class Node_Gemini_API {

    /**
     * API Key
     */
    private string $api_key;

    /**
     * API URL Base
     */
    private string $api_url_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct( ?int $user_id = null ) {
        $user_id = $user_id ?? get_current_user_id();

        if ( function_exists( 'node_get_user_gemini_api_key' ) && $user_id > 0 ) {
            $this->api_key = node_get_user_gemini_api_key( $user_id );
        } else {
            $this->api_key = $user_id > 0
                ? (string) get_user_meta( $user_id, 'node_gemini_api_key', true )
                : '';
        }

        // 開発環境のみ: wp-config.php の GEMINI_API_KEY 定数をフォールバック
        if ( empty( $this->api_key ) && defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
            $this->api_key = (string) GEMINI_API_KEY;
        }
    }

    /**
     * 高度なコンテンツ生成 (システム指示対応)
     */
    public function generate_content(string $prompt, array $options = []): string|array|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'Gemini API キーが設定されていません。ユーザー → プロフィール の「Gemini API（個人設定）」から、あなた専用のキーを登録してください。'
            );
        }

        $options = wp_parse_args($options, [
            'system_instruction' => 'あなたはプロのテクニカルライターであり、デザイナーです。ユーザーに驚きと知覚的な喜びを与える文章を作成してください。',
            'max_tokens'  => 400,
            'temperature' => 0.7,
            'response_mime_type' => 'text/plain',
            'google_search_grounding' => false,
            'return_metadata' => false,
            'timeout' => 45,
        ]);

        $user_id = get_current_user_id();
        if ( function_exists( 'node_get_user_gemini_model' ) && $user_id > 0 ) {
            $model_name = node_get_user_gemini_model( $user_id );
        } else {
            $model_name = function_exists( 'node_get_default_gemini_model' )
                ? node_get_default_gemini_model()
                : 'gemini-2.0-flash';
        }
        
        $url = $this->api_url_base . $model_name . ':generateContent?key=' . $this->api_key;

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'system_instruction' => [
                'parts' => [['text' => $options['system_instruction']]]
            ],
            'generationConfig' => [
                'maxOutputTokens'  => $options['max_tokens'],
                'temperature'      => $options['temperature'],
                'responseMimeType' => $options['response_mime_type'],
            ]
        ];

        if ( ! empty( $options['google_search_grounding'] ) ) {
            $payload['tools'] = array(
                array( 'google_search' => new \stdClass() ),
            );
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => (int) $options['timeout'],
        ]);

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            if (strpos($err_msg, 'timed out') !== false) {
                return new WP_Error('gemini_timeout', 'Gemini APIからの応答がタイムアウトしました。しばらく待ってから再度お試しください。', ['status' => 504]);
            }
            return new WP_Error('gemini_request_failed', 'Gemini APIへの接続に失敗しました。詳細: ' . $err_msg, ['status' => 502]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        // HTTP エラー / API エラー応答（429 の利用上限超過を含む）を分かりやすく返す。
        if (200 !== $status || (is_array($data) && isset($data['error']))) {
            if ( function_exists( 'node_gemini_record_quota_error' ) ) {
                node_gemini_record_quota_error( $user_id, $model_name, $status, $data );
            }

            $message = function_exists('node_gemini_format_api_error')
                ? node_gemini_format_api_error($status, $data, $body)
                : ('Gemini API エラー (HTTP ' . $status . ')');
            $code = (429 === $status) ? 'gemini_quota_exceeded' : ((503 === $status) ? 'gemini_model_unavailable' : 'gemini_api_error');
            return new WP_Error($code, $message, ['status' => $status]);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($data['candidates'][0]['content']['parts'][0]['text']);

            if ( function_exists( 'node_gemini_record_usage' ) ) {
                $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);
                node_gemini_record_usage( $user_id, $model_name, $tokens, 1 );
            }

            if ( ! empty( $options['return_metadata'] ) ) {
                return array(
                    'text'      => $text,
                    'grounding' => $data['candidates'][0]['groundingMetadata'] ?? array(),
                );
            }

            return $text;
        }

        return new WP_Error(
            'api_error',
            function_exists('node_gemini_format_api_error')
                ? node_gemini_format_api_error($status, $data, $body)
                : 'Gemini API から有効なレスポンスが得られませんでした。'
        );
    }

    /**
     * デザイナー品質の要約を生成する
     */
    public function generate_summary(string $content, string $custom_prompt = '', array $options = []): string|WP_Error {
        $options = wp_parse_args($options, [
            'max_lines' => 3,
            'max_chars' => 120
        ]);

        $system_prompt = "あなたは先進的な技術ブログ 'Luminous Core' の編集長です。
提供された記事を解析し、以下の JSON フォーマットでレスポンスしてください。
・必ず、要約は {$options['max_lines']} 行以内、かつ {$options['max_chars']} 文字以内厳守で作成してください。
・Markdownのコードブロック（```json ... ```）は絶対に使わず、生の中括弧 { } から始まる純粋なJSON文字列のみを出力してください。
・要約内に改行を含めないでください。
{
  \"summary\": \"読者の好奇心を刺激する、情緒的で洗練された要約。\",
  \"tone_color\": \"記事のトーンを表す色（hexコード）。\",
  \"vibe_keywords\": [\"キーワード1\", \"キーワード2\"]
}";

        $prompt = "以下の記事を解析し、最高の要約を生成してください：\n\n" . mb_substr($content, 0, 5000);

        if ( ! empty( $custom_prompt ) ) {
            $prompt .= "\n\n【追加の指示（プロンプト）】\n" . $custom_prompt;
        }

        $result = $this->generate_content($prompt, [
            'system_instruction' => $system_prompt,
            'response_mime_type' => 'application/json',
            'temperature' => 0.4,
            'max_tokens' => 2048
        ]);
        
        return $result;
    }

    /**
     * 記事本文のファクトチェック（編集者向け・要手動確認）
     */
    public function fact_check( string $content, string $title = '' ): array|WP_Error {
        $guidelines_block = '';
        $guidelines_used  = false;

        if ( function_exists( 'node_ai_fetch_guidelines' ) ) {
            $guidelines = node_ai_fetch_guidelines();
            if ( is_string( $guidelines ) && '' !== $guidelines ) {
                $guidelines_used  = true;
                $guidelines_block = "\n\n【Luminous Core 運営ガイドライン（Google ドキュメント）】\n" . $guidelines;
            }
        }

        $system_prompt = 'あなたはテクニカルブログ「Luminous Core」のファクトチェック補助アシスタントです。
Google Search の検索結果を参照し、記事内の事実関係に関わる主張を抽出して検証してください。
あわせて、提供される Luminous Core 運営ガイドラインに照らし、コンプライアンス違反の可能性がある記述も指摘してください。
ガイドライン違反は status を uncertain または likely_incorrect とし、note に該当ルールを簡潔に記載してください。
以下の JSON 形式のみで回答してください。
・Markdown のコードブロック（```json ... ```）は絶対に使わず、生の中括弧 { } から始まる純粋な JSON のみを出力してください。
・推測で断定せず、不確実な場合は status を uncertain または unverifiable にしてください。
・最大 8 件の主張に絞ってください。
・note には検索結果・ガイドラインに基づく根拠・確認方法・注意点を簡潔に書いてください。

{
  "summary": "全体所見（2〜3文、日本語）",
  "overall_risk": "low または medium または high",
  "claims": [
    {
      "claim": "記事中の主張（原文に近い形）",
      "status": "likely_correct / uncertain / likely_incorrect / unverifiable のいずれか",
      "confidence": "high / medium / low のいずれか",
      "note": "根拠・補足（日本語）"
    }
  ]
}' . $guidelines_block;

        $prompt = '以下の記事をファクトチェックしてください。可能な限り Google Search の情報を参照してください。';
        if ( ! empty( $title ) ) {
            $prompt .= "\n\n【タイトル】\n" . $title;
        }
        $prompt .= "\n\n【本文】\n" . mb_substr( $content, 0, 8000 );

        $result = $this->generate_content(
            $prompt,
            array(
                'system_instruction'        => $system_prompt,
                'response_mime_type'      => 'application/json',
                'temperature'             => 0.2,
                'max_tokens'              => 4096,
                'google_search_grounding' => true,
                'return_metadata'         => true,
                'timeout'                 => 60,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'text'             => (string) ( $result['text'] ?? '' ),
            'grounding'        => is_array( $result['grounding'] ?? null ) ? $result['grounding'] : array(),
            'guidelines_used'  => $guidelines_used,
        );
    }

    /**
     * AIによる読了目安時間の推定
     */
    public function generate_reading_time_estimate(string $content): string|WP_Error {
        $prompt = "以下の記事の読了目安時間を推定し、「〇分〇秒」という形式で回答してください。日本語として自然な読書速度（分速400〜600文字程度）を基準にしつつ、内容の難易度や構成（コード、リスト、画像等）も加味して人間が読み終えるのにかかる時間を算出してください。解説は不要です。結果の数値のみを返してください。\n\n" . mb_substr($content, 0, 4000);
        return $this->generate_content($prompt, [
            'max_tokens'  => 10,
            'temperature' => 0.1
        ]);
    }
}
