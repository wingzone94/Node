<?php
/**
 * Luminous Blocks ユーティリティ
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'node_validate_embed_url' ) ) {
    function node_validate_embed_url(string $url, array $allowed): bool {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        foreach ($allowed as $domain) {
            if (str_ends_with($host, $domain)) return true;
        }
        return false;
    }
}

if ( ! function_exists( 'node_extract_src_from_input' ) ) {
    function node_extract_src_from_input($input) {
        if (str_contains($input, '<iframe')) {
            preg_match('/src="([^"]+)"/', $input, $match);
            return $match[1] ?? '';
        }
        return $input;
    }
}
