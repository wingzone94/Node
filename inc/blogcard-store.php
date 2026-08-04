<?php
/**
 * ゲームストア URL のブログカード対応
 *
 * ニンテンドーストア / PlayStation Store / Xbox（Microsoft ストア）/ Steam の商品ページは
 * ボット防御下にあり OGP 取得に失敗することがある。取得できなかった場合でも素のリンクへ
 * 落とさず、URL から組み立てた最低限の情報でストアカードを表示するためのヘルパー群。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL が対応ゲームストアの商品ページかどうかを判定する。
 *
 * @param string $url 判定対象 URL。
 * @return array{slug: string, name: string, brand_var: string}|array{} 該当しなければ空配列。
 */
function node_store_provider( string $url ): array {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	$path = strtolower( (string) parse_url( $url, PHP_URL_PATH ) );

	if ( '' === $host ) {
		return array();
	}

	// --- Nintendo ---
	// ストア専用ホストはパス不問。nintendo.com / nintendo.co.jp は情報サイトも兼ねるため
	// ストア配下のパスに限定する（トピックス記事等を誤ってストアカードにしない）。
	if ( node_store_host_matches( $host, array( 'store-jp.nintendo.com', 'store.nintendo.co.jp', 'ec.nintendo.com', 'store.nintendo.com' ) )
		|| ( node_store_host_matches( $host, array( 'nintendo.com' ) ) && str_contains( $path, '/store/' ) )
		|| ( node_store_host_matches( $host, array( 'nintendo.co.jp' ) ) && str_contains( $path, '/software/' ) ) ) {
		return array(
			'slug'      => 'nintendo',
			'name'      => 'ニンテンドーストア',
			'brand_var' => '--brand-nintendo',
		);
	}

	// --- PlayStation ---
	if ( node_store_host_matches( $host, array( 'store.playstation.com' ) ) ) {
		return array(
			'slug'      => 'playstation',
			'name'      => 'PlayStation Store',
			'brand_var' => '--brand-sony',
		);
	}

	// --- Xbox / Microsoft ストア ---
	if ( ( node_store_host_matches( $host, array( 'xbox.com' ) ) && str_contains( $path, '/games/store/' ) )
		|| node_store_host_matches( $host, array( 'marketplace.xbox.com', 'apps.microsoft.com' ) ) ) {
		return array(
			'slug'      => 'xbox',
			'name'      => 'Microsoft ストア',
			'brand_var' => '--brand-xbox',
		);
	}

	// --- Steam ---
	if ( node_store_host_matches( $host, array( 'store.steampowered.com', 's.team' ) ) ) {
		return array(
			'slug'      => 'steam',
			'name'      => 'Steam',
			'brand_var' => '--brand-windows',
		);
	}

	return array();
}

/**
 * ホストが対象ドメイン（またはそのサブドメイン）に一致するか判定する。
 *
 * `str_contains()` だと `nintendo.com.example.net` のような別ドメインを誤検知するため、
 * 完全一致かサブドメイン一致でのみ真を返す（node_is_excluded_oembed_provider() と同形）。
 *
 * @param string        $host    www を除いたホスト。
 * @param array<string> $domains 対象ドメイン。
 * @return bool
 */
function node_store_host_matches( string $host, array $domains ): bool {
	foreach ( $domains as $domain ) {
		if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
			return true;
		}
	}

	return false;
}

/**
 * ストア URL のスラッグから題名を組み立てる（メタ取得に失敗したときのフォールバック）。
 *
 * 例: /ja-JP/games/store/forza-horizon-5/9NKX70BBCDRN → Forza Horizon 5
 *     /app/570/Dota_2/                                → Dota 2
 * ニンテンドーストアの商品 ID 型パス（/item/software/D70010000073404）のように
 * 題名を復元できない形式では空文字を返す。
 *
 * @param string $url  ストア URL。
 * @param string $slug ストア識別子。
 * @return string 題名（組み立てられなければ空文字）。
 */
function node_store_title_from_url( string $url, string $slug ): string {
	$path     = (string) parse_url( $url, PHP_URL_PATH );
	$segments = array_values( array_filter( explode( '/', $path ), static fn( string $s ): bool => '' !== $s ) );
	if ( empty( $segments ) ) {
		return '';
	}

	$candidate = '';

	if ( 'steam' === $slug ) {
		// /app/<id>/<Name>/ 形式。名前セグメントが無い短縮形もある。
		foreach ( $segments as $index => $segment ) {
			if ( 'app' === strtolower( $segment ) && isset( $segments[ $index + 2 ] ) ) {
				$candidate = $segments[ $index + 2 ];
				break;
			}
		}
	} elseif ( 'xbox' === $slug ) {
		// /<locale>/games/store/<name>/<product-id>
		foreach ( $segments as $index => $segment ) {
			if ( 'store' === strtolower( $segment ) && isset( $segments[ $index + 1 ] ) ) {
				$candidate = $segments[ $index + 1 ];
				break;
			}
		}
	} else {
		// PlayStation は /<locale>/product/<CONCEPT-ID> のように ID 止まりのことが多い。
		// 汎用に「最後のセグメント」を候補にし、ID 判定で弾く。
		$candidate = (string) end( $segments );
	}

	return node_store_humanize_slug( $candidate );
}

/**
 * URL スラッグを人が読める題名へ変換する。ID とみなせる文字列は空文字を返す。
 *
 * @param string $slug URL セグメント。
 * @return string
 */
function node_store_humanize_slug( string $slug ): string {
	$slug = rawurldecode( $slug );
	$slug = (string) preg_replace( '/\.(html?|php)$/i', '', $slug );

	if ( '' === $slug ) {
		return '';
	}

	// 商品 ID 系は題名にならない。数字のみ（Steam の app id・任天堂の title id）と、
	// 小文字を含まず数字を含む羅列（`D70010000073404` / `9NKX70BBCDRN` /
	// PS の `JP0082-PPSA01284_00-ASTROSBGDELUXE01`）を除外する。
	if ( preg_match( '/^[0-9]+$/', $slug )
		|| ( preg_match( '/^[A-Z0-9_-]+$/', $slug ) && preg_match( '/[0-9]/', $slug ) ) ) {
		return '';
	}

	$words = trim( (string) preg_replace( '/[-_+]+/', ' ', $slug ) );
	$words = trim( (string) preg_replace( '/\s+/', ' ', $words ) );

	if ( '' === $words ) {
		return '';
	}

	// 日本語を含む場合はそのまま（ucwords がマルチバイトを壊すため）。
	if ( preg_match( '/[^\x20-\x7E]/', $words ) ) {
		return $words;
	}

	return ucwords( $words );
}

/**
 * ストア URL 用のフォールバック OGP 情報を組み立てる。
 *
 * @param string                                          $url      ストア URL。
 * @param array{slug: string, name: string, brand_var: string} $provider ストア情報。
 * @return array<string, mixed>
 */
function node_store_fallback_ogp( string $url, array $provider ): array {
	$title = node_store_title_from_url( $url, $provider['slug'] );

	return array(
		'title'       => '' !== $title ? $title : $provider['name'],
		'description' => '',
		'image'       => '',
		'favicon'     => 'https://www.google.com/s2/favicons?domain=' . rawurlencode( (string) parse_url( $url, PHP_URL_HOST ) ) . '&sz=64',
		'site_name'   => $provider['name'],
		'is_internal' => false,
		'store'       => $provider['slug'],
	);
}
