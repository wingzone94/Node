<?php
// Blocks: ユーティリティ関数 — スタブ

/**
 * 指定されたURLが許可ドメインに属するか検証する
 */
function node_validate_embed_url(string $url, array $allowed): bool {
    $url = trim($url);
    if (empty($url)) return false;

    // iframeタグが丸ごと貼り付けられた場合に src を抽出する試み
    if (str_starts_with($url, '<iframe') || str_contains($url, ' src=')) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $url, $match)) {
            $url = $match[1];
        }
    }

    // プロトコルがない場合は補完 (wp_parse_url対策)
    if (!str_contains($url, '://') && !str_starts_with($url, '//')) {
        $url = 'https://' . $url;
    }

    $parsed = wp_parse_url($url); 
    if (empty($parsed['host'])) return false;

    $host = strtolower($parsed['host']);

    foreach ($allowed as $allowed_host) {
        $allowed_host = strtolower($allowed_host);
        if ($host === $allowed_host || str_ends_with($host, '.' . $allowed_host)) {
            return true;
        }
    }
    return false;
}

/**
 * 入力から src URL を安全に抽出する
 */
function node_extract_src_from_input($input) {
    $input = trim($input);
    if (empty($input)) return '';
    
    // タグ形式なら中身を抽出
    if (str_starts_with($input, '<iframe') || str_contains($input, ' src=')) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $input, $match)) {
            return $match[1];
        }
    }
    
    // プロトコル補完
    if (!str_contains($input, '://') && !str_starts_with($input, '//')) {
        return 'https://' . $input;
    }
    
    return $input;
}
if ( ! defined( 'ABSPATH' ) ) exit;
