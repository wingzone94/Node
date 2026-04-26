<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ブロック一括登録 — スタブ
function node_register_m3_blocks() {
    // 1. Smart Sort Table
    register_block_type('node/sort-table', [
        'render_callback' => 'node_render_sort_table_block',
        'attributes' => [
            'enable_sort' => ['type' => 'boolean', 'default' => true],
            'headers'     => ['type' => 'array', 'default' => ['項目', '値', '備考']],
            'rows'        => ['type' => 'array', 'default' => [['Apple', '100', 'Red'], ['Banana', '50', 'Yellow']]]
        ]
    ]);

    // 2. Apple Music
    register_block_type('node/apple-music', [
        'render_callback' => function($attr) { return node_apple_music_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'url'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '175']
        ]
    ]);

    // 3. Google Map
    register_block_type('node/google-map', [
        'render_callback' => function($attr) { return node_google_map_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'src'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '450']
        ]
    ]);

    // 4. Spotify
    register_block_type('node/spotify', [
        'render_callback' => function($attr) { return node_spotify_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'url'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '352']
        ]
    ]);

    // 5. Product Card
    register_block_type('node/product-card', [
        'render_callback' => function($attr) { return node_product_card_shortcode($attr); },
        'attributes' => [
            'title'       => ['type' => 'string', 'default' => ''],
            'price'       => ['type' => 'string', 'default' => ''],
            'image_url'   => ['type' => 'string', 'default' => ''],
            'amazon_url'  => ['type' => 'string', 'default' => ''],
            'rakuten_url' => ['type' => 'string', 'default' => '']
        ]
    ]);
}
add_action('init', 'node_register_m3_blocks');
function luminous_blocks_register_all(): void {}

// oEmbed ハンドラ登録 — スタブ
function node_register_oembed_handlers() {
    // 1. Apple Music (music.apple.com または embed.music.apple.com)
    wp_embed_register_handler(
        'node_apple_music',
        '#^https?://(embed\.)?music\.apple\.com/.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_apple_music_shortcode(['url' => $url]);
        }
    );

    // 2. Spotify (open.spotify.com)
    wp_embed_register_handler(
        'node_spotify',
        '#^https?://open\.spotify\.com/.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_spotify_shortcode(['url' => $url]);
        }
    );

    // 3. Google Maps (/maps/embed パスが含まれる場合のみ)
    wp_embed_register_handler(
        'node_google_map',
        '#^https?://(www\.)?google\.(com|co\.jp)/maps/embed.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_google_map_shortcode(['src' => $url]);
        }
    );
}
add_action('init', 'node_register_oembed_handlers');
function luminous_blocks_register_oembed_handlers(): void {}
