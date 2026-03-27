<?php
/**
 * Node theme functions and definitions
 */

function node_setup() {
    // 基本機能の有効化
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));

    // Google Fonts (Inter & Noto Sans JP) のバリアブルフォント版を読み込み
    wp_enqueue_style(
        'google-fonts', 
        'https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Noto+Sans+JP:wght@100..900&display=swap', 
        array(), 
        null
    );

    // メインスタイルの読み込み
    wp_enqueue_style('node-style', get_stylesheet_uri(), array(), '0.1');
}
add_action('wp_enqueue_scripts', 'node_setup');