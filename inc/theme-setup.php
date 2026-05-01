<?php
/**
 * Theme Setup for Luminous Core (Node)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -------------------------------------------------------
 * 1. テーマの基本設定
 * -------------------------------------------------------
 */
function node_theme_setup() {

	// HTML <title> を WP に任せる
	add_theme_support( 'title-tag' );

	// アイキャッチ画像
	add_theme_support( 'post-thumbnails' );

	// メニュー
	register_nav_menus(
		array(
			'primary'   => __( 'Primary Menu', 'luminous-core' ),
			'footer'    => __( 'Footer Menu', 'luminous-core' ),
		)
	);

	// HTML5 マークアップ
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'script',
			'style',
			'navigation-widgets',
		)
	);

	// サイトロゴ
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 64,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// 埋め込みのレスポンシブ対応
	add_theme_support( 'responsive-embeds' );

	// 幅広ブロック
	add_theme_support( 'align-wide' );

	// ブロックエディタのスタイル
	add_theme_support( 'editor-styles' );

	// ブロックエディタに Vite の CSS を適用
	add_editor_style( 'assets/css/style.css' );

	// フィードリンク
	add_theme_support( 'automatic-feed-links' );

	// ブロックスタイル
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'node_theme_setup' );

/**
 * -------------------------------------------------------
 * 2. Dynamic Color Seed の初期化（FOUC 対策）
 * -------------------------------------------------------
 */
add_action( 'after_setup_theme', 'node_initialize_seed_color' );
/**
 * Dynamic Color の seed color を取得する
 * - まだ seed color が決まっていない場合はデフォルト値を返す
 * - 将来的に JS 側と同期させる場合はここを拡張
 */
function node_extract_seed_color() {
    // デフォルトの seed color（あなたのテーマは #FF9900）
    $default_seed = '#FF9900';

    // 投稿メタに保存されている場合はそれを優先
    $seed = get_option('_node_seed_color');

    if ( ! empty( $seed ) ) {
        return $seed;
    }

    return $default_seed;
}
/**
 * Dynamic Color の seed color を初期化
 */
function node_initialize_seed_color() {
    if ( ! function_exists( 'node_extract_seed_color' ) ) {
        return;
    }

    if ( ! get_option( '_node_seed_color' ) ) {
        $seed = node_extract_seed_color();
        update_option( '_node_seed_color', $seed );
    }
}
