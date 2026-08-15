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

	// Node の既定言語は日本語。翻訳ファイルが追加された場合も node ドメインで読む。
	load_theme_textdomain( 'node', get_template_directory() . '/languages' );

	// HTML <title> を WP に任せる
	add_theme_support( 'title-tag' );

	// アイキャッチ画像
	add_theme_support( 'post-thumbnails' );

	// メニュー
	register_nav_menus(
		array(
			'primary'   => __( 'メインメニュー', 'node' ),
			'footer'    => __( 'フッターメニュー', 'node' ),
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
	// add_editor_style( 'assets/css/style.css' );

	// フィードリンク
	add_theme_support( 'automatic-feed-links' );

	// ブロックスタイル
	add_theme_support( 'wp-block-styles' );

	// テーブルの「並べ替え可能」スタイルを登録
	register_block_style(
		'core/table',
		array(
			'name'  => 'sortable',
			'label' => '並べ替え可能',
		)
	);
}
add_action( 'after_setup_theme', 'node_theme_setup' );

/**
 * フロントエンドの既定 locale を日本語に固定する。
 *
 * 管理画面はユーザー個別設定を尊重し、テーマの表示面だけを日本語優先にする。
 */
function node_force_frontend_locale_to_japanese( string $locale ): string {
	if ( is_admin() ) {
		return $locale;
	}

	return 'ja';
}
add_filter( 'locale', 'node_force_frontend_locale_to_japanese' );

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

/**
 * Node テーマのバージョン（親テーマ style.css の Version ヘッダー）
 */
function node_get_theme_version(): string {
	$theme   = wp_get_theme( get_template() );
	$version = $theme->get( 'Version' );

	return ( is_string( $version ) && '' !== $version ) ? $version : '0.0.0';
}

/**
 * iPad / Android タブレット等の UA 判定（表示モード切替ボタン出力用）
 */
function node_is_tablet_ua(): bool {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( '' === $ua ) {
		return false;
	}

	// iPad（クラシック UA）
	if ( false !== stripos( $ua, 'iPad' ) ) {
		return true;
	}

	// Android タブレット（Mobile なし）
	if ( false !== stripos( $ua, 'Android' ) && false === stripos( $ua, 'Mobile' ) ) {
		return true;
	}

	// 汎用 Tablet / Kindle 等
	if ( preg_match( '/Tablet|PlayBook|Silk/i', $ua ) ) {
		return true;
	}

	// iPadOS（Mobile 付き Macintosh Safari）
	if ( preg_match( '/Macintosh|Mac OS X/i', $ua )
		&& preg_match( '/AppleWebKit/i', $ua )
		&& false === stripos( $ua, 'iPhone' )
		&& false === stripos( $ua, 'iPod' )
		&& false !== stripos( $ua, 'Mobile' ) ) {
		return true;
	}

	// Client Hints（対応ブラウザ）
	$ch_platform = isset( $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ) ? (string) $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] : '';
	if ( '' !== $ch_platform && false !== stripos( $ch_platform, 'iPad' ) ) {
		return true;
	}

	return false;
}

/**
 * node.zip 展開後のコピー元ディレクトリを解決する
 *
 * @param string $temp_extract_dir 一時展開先。
 * @return string|null 末尾スラッシュ付きパス。見つからない場合は null。
 */
function node_resolve_theme_update_source_dir( string $temp_extract_dir ): ?string {
	$candidates = array(
		$temp_extract_dir . '/Node',
		$temp_extract_dir . '/node',
		$temp_extract_dir . '/node-theme-production',
	);

	foreach ( $candidates as $dir ) {
		if ( node_is_valid_theme_update_source( $dir ) ) {
			return trailingslashit( $dir );
		}
	}

	$entries = is_dir( $temp_extract_dir ) ? scandir( $temp_extract_dir ) : false;
	if ( is_array( $entries ) ) {
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$dir = $temp_extract_dir . '/' . $entry;
			if ( is_dir( $dir ) && node_is_valid_theme_update_source( $dir ) ) {
				return trailingslashit( $dir );
			}
		}
	}

	if ( node_is_valid_theme_update_source( $temp_extract_dir ) ) {
		return trailingslashit( $temp_extract_dir );
	}

	return null;
}

/**
 * テーマ更新のコピー元として style.css が Node テーマか検証
 *
 * @param string $dir 検査対象ディレクトリ。
 */
function node_is_valid_theme_update_source( string $dir ): bool {
	$style = $dir . '/style.css';
	$index = $dir . '/index.php';
	if ( ! is_file( $style ) || ! is_file( $index ) ) {
		return false;
	}

	$data = get_file_data(
		$style,
		array(
			'Theme Name' => 'Theme Name',
		)
	);

	return isset( $data['Theme Name'] ) && 'Node' === $data['Theme Name'];
}
