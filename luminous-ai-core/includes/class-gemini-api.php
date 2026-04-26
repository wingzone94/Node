<?php
/**
 * Gemini API 通信クラス（スタブ）
 *
 * Gemini CLI による移行時に functions.php L93-122 のコードをここに移動する。
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('wp_ajax_node_generate_ai_summary', 'node_ajax_generate_ai_summary');
function node_ajax_generate_ai_summary() {
    check_ajax_referer('node_ai_generate_action', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => '権限がありません。']);
    }
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_send_json_error(['message' => '不正な投稿IDです。']);
    $post = get_post($post_id);
    if (!$post) wp_send_json_error(['message' => '記事が見つかりません。']);
    $content = strip_shortcodes(strip_tags($post->post_content));
    if (empty(trim($content))) wp_send_json_error(['message' => '記事本文が空です。']);
    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($api_key)) wp_send_json_error(['message' => 'GEMINI_API_KEYが設定されていません。']);

    $prompt = "以下の記事本文を100文字程度で簡潔に要約してください。\n\n" . mb_substr($content, 0, 3000);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key=' . $api_key;
    $body = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 150, 'temperature' => 0.3]
    ]);
    $response = wp_remote_post($url, ['headers' => ['Content-Type' => 'application/json'], 'body' => $body, 'timeout' => 15]);
    if (is_wp_error($response)) wp_send_json_error(['message' => 'APIリクエスト失敗: ' . $response->get_error_message()]);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        wp_send_json_success(['summary' => str_replace(["\r\n", "\r", "\n"], ' ', trim($data['candidates'][0]['content']['parts'][0]['text']))]);
    } else {
        wp_send_json_error(['message' => 'APIから正しいレスポンスが返されませんでした。']);
    }
}
