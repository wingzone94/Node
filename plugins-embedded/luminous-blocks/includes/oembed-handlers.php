<?php
/**
 * Luminous Blocks oEmbed ハンドラ
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'node_register_m3_blocks' ) ) {
    function node_register_m3_blocks() {
        register_block_type('node/media-label', [
            'editor_script' => 'node-editor-js',
            'editor_style'  => 'node-editor-css',
            'render_callback' => function($attributes) {
                return node_render_media_label($attributes['imageUrl'] ?? '', $attributes['labelText'] ?? 'AI生成');
            }
        ]);
        register_block_type('node/voting', [
            'editor_script' => 'node-editor-js',
            'render_callback' => 'node_render_voting_refined'
        ]);
        register_block_type('node/sort-table', [
            'editor_script' => 'node-editor-js',
            'render_callback' => 'node_render_sort_table_block'
        ]);
    }
}

if ( ! function_exists( 'node_register_oembed_handlers' ) ) {
    function node_register_oembed_handlers() {
        wp_embed_register_handler('spotify', '#https?://open\.spotify\.com/(track|album|playlist|artist)/[a-zA-Z0-9]+#', function($matches) {
            return node_spotify_shortcode(['url' => $matches[0]]);
        });
        wp_embed_register_handler('apple-music', '#https?://music\.apple\.com/[a-z]{2}/(album|song|playlist)/[a-zA-Z0-9.-]+/[0-9]+#', function($matches) {
            return node_apple_music_shortcode(['url' => $matches[0]]);
        });
    }
}
