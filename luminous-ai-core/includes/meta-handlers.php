<?php
/**
 * メタデータハンドラ（スタブ）
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function node_generate_ai_metadata($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || $post->post_type !== 'post' || $post->post_status !== 'publish') return;
    $content = strip_shortcodes(strip_tags($post->post_content));
    $hash = md5($content);
    if ($hash !== get_post_meta($post_id, '_node_content_hash', true)) {
        $char_count = mb_strlen(preg_replace('/\s+/', '', $content));
        $total_seconds = ceil(($char_count / 800) * 60);
        update_post_meta($post_id, '_node_reading_time', floor($total_seconds / 60) . '分' . sprintf('%02d', $total_seconds % 60) . '秒');
        update_post_meta($post_id, '_node_content_hash', $hash);
    }
}
add_action('save_post', 'node_generate_ai_metadata', 20, 3);
function luminous_ai_calculate_reading_time( int $post_id, \WP_Post $post, bool $update ): void {
	// スタブ: 移行後にロジックが入る
}
