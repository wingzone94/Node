<?php

declare(strict_types=1);
/**
 * Theme Setup for Luminous Core (Node)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
