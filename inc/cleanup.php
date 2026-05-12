<?php
/**
 * Cleanup Unnecessary WordPress Outputs
 * 
 * 初期表示のペイロードを最小化するため、不要なスクリプトやスタイル、リンクを削除します。
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function luminous_core_cleanup_head() {
    // 1. 絵文字関連のスクリプトとスタイルを削除
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    // TinyMCE 絵文字プラグインの無効化
    add_filter( 'tiny_mce_plugins', function( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        } else {
            return array();
        }
    });

    // 絵文字用の DNS プリフェッチを削除
    add_filter( 'emoji_svg_url', '__return_false' );

    // 2. 不要な REST API リンクなどを削除
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
    remove_action( 'rest_api_init', 'wp_oembed_register_route' );
    remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
}
add_action( 'init', 'luminous_core_cleanup_head' );

// 3. wp-embed.min.js の削除
function luminous_core_deregister_scripts() {
    wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_footer', 'luminous_core_deregister_scripts' );

// 4. グローバルスタイル (theme.json) のフロントエンド出力削除
function luminous_core_remove_global_styles() {
    wp_dequeue_style( 'global-styles' );
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-blocks-style' );
}
add_action( 'wp_enqueue_scripts', 'luminous_core_remove_global_styles', 100 );
