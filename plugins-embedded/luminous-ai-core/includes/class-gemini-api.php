<?php
/**
 * Gemini API 通信クラス
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Luminous_Gemini_API
 */
class Luminous_Gemini_API {

    /**
     * API Key
     */
    private string $api_key;

    /**
     * API URL
     */
    private string $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent';

    public function __construct() {
        $this->api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    }

    /**
     * 汎用的なテキスト生成
     */
    public function generate_content(string $prompt, int $max_tokens = 200, float $temperature = 0.7): string|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'GEMINI_API_KEYが設定されていません。');
        }

        $url = $this->api_url . '?key=' . $this->api_key;

        $body = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
                'temperature' => $temperature
            ]
        ]);

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }

        return new WP_Error('api_error', 'APIから正しいレスポンスが返されませんでした。');
    }

    /**
     * 記事本文の要約を生成する
     */
    public function generate_summary(string $content): string|WP_Error {
        $prompt = "以下の記事本文を100文字程度で簡潔に要約してください。\n\n" . mb_substr($content, 0, 3000);
        $result = $this->generate_content($prompt, 150, 0.3);
        
        if (is_wp_error($result)) {
            return $result;
        }

        return str_replace(["\r\n", "\r", "\n"], ' ', $result);
    }
}
