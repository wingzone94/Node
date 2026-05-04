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

    public function __construct() {
        $this->api_key = get_option( 'node_gemini_api_key', defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '' );
    }

    /**
     * 高度なコンテンツ生成 (システム指示対応)
     */
    public function generate_content(string $prompt, array $options = []): string|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'GEMINI_API_KEYが設定されていません。Luminous Settingsから設定してください。');
        }

        $options = wp_parse_args($options, [
            'system_instruction' => 'あなたはプロのテクニカルライターであり、デザイナーです。ユーザーに驚きと知覚的な喜びを与える文章を作成してください。',
            'max_tokens'  => 400,
            'temperature' => 0.7,
            'response_mime_type' => 'text/plain',
        ]);

        $model_name = get_option( 'node_gemini_model', 'gemini-3.1-pro-preview' );
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

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => 20
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }

        return new WP_Error('api_error', 'APIから有効なレスポンスが得られませんでした。: ' . wp_remote_retrieve_body($response));
    }

    /**
     * デザイナー品質の要約を生成する
     */
    public function generate_summary(string $content, string $custom_prompt = ''): string|WP_Error {
        $system_prompt = "あなたは先進的な技術ブログ 'Node' の編集長です。
        提供された記事を解析し、以下の JSON フォーマットでレスポンスしてください。
        {
          \"summary\": \"読者の好奇心を刺激する、情緒的で洗練された100文字程度の要約。\",
          \"tone_color\": \"記事のトーンを表す色（hexコード）。技術的なら青系、情熱的なら赤系など。\",
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
            'max_tokens' => 1000
        ]);
        
        return $result;
    }

    /**
     * AIによる読了目安時間の推定
     */
    public function generate_reading_time_estimate(string $content): string|WP_Error {
        $prompt = "以下の記事の読了目安時間を推定し、「〇分〇秒」という形式で回答してください。日本語として自然な読書速度（分速400〜600文字程度）を基準にしつつ、内容の難易度や構成（コード、リスト、画像等）も加味して人間が読み終えるのにかかる時間を算出してください。解説は不要です。結果の数値のみを返してください。\n\n" . mb_substr($content, 0, 4000);
        return $this->generate_content($prompt, 20, 0.1);
    }
}
