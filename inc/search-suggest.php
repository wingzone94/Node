<?php

declare(strict_types=1);
/**
 * 検索キーワードのサジェスト（Node Library / カテゴリ）
 *
 * ヘッダー検索の入力に対して、Node Library に登録済みのゲーム・アプリ名と
 * カテゴリ名を「検索キーワードの候補」として返す。候補を押すとその語で通常検索が
 * 走るので、正式名称のうろ覚え（例: 「ゼルダ」→「ゼルダの伝説 ティアーズ オブ ザ キングダム」）
 * でも目的の記事へ辿り着ける。
 *
 * 詳細検索モーダルのタグ補完（luminous-core-engine の /search/tags）とは別物。
 * あちらはタグ入力欄の補完で、こちらはヘッダー検索そのものの補完。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1種別あたりの候補数。合計でも多すぎるとドロップダウンがスクロール前提になるため絞る。
 */
const NODE_SEARCH_SUGGEST_PER_SOURCE = 5;
const NODE_SEARCH_SUGGEST_LIMIT      = 6;

/**
 * サジェスト用の REST ルートを登録する。
 */
function node_register_search_suggest_route(): void {
	register_rest_route(
		'node/v1',
		'/search/suggest',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'node_search_suggest_response',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'node_register_search_suggest_route' );

/**
 * 検索キーワード候補を返す。
 *
 * @param WP_REST_Request $request リクエスト。
 * @return WP_REST_Response 候補の配列。
 */
function node_search_suggest_response( WP_REST_Request $request ): WP_REST_Response {
	$query = trim( (string) $request->get_param( 'q' ) );

	// 1文字だと候補がほぼ全件になり、絞り込みの役に立たない。
	if ( '' === $query || mb_strlen( $query ) < 2 ) {
		return rest_ensure_response( array() );
	}

	$suggestions = array_merge(
		node_get_library_keyword_suggestions( $query ),
		node_get_category_keyword_suggestions( $query )
	);

	return rest_ensure_response( node_merge_search_keyword_suggestions( $suggestions ) );
}

/**
 * Node Library の項目名から候補を作る。
 *
 * @param string $query 入力中のキーワード。
 * @return array<int, array<string, mixed>> 候補。
 */
function node_get_library_keyword_suggestions( string $query ): array {
	if ( ! post_type_exists( 'node_library' ) ) {
		return array();
	}

	$items = get_posts(
		array(
			'post_type'        => 'node_library',
			'post_status'      => 'publish',
			'posts_per_page'   => NODE_SEARCH_SUGGEST_PER_SOURCE,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			's'                => $query,
		)
	);

	$suggestions = array();
	foreach ( $items as $item ) {
		$type = (string) get_post_meta( $item->ID, '_node_library_type', true );

		$suggestions[] = array(
			'keyword'     => $item->post_title,
			'source'      => 'library',
			'sourceLabel' => 'ライブラリ',
			// ゲームとアプリでアイコンを分ける。node-library のカード
			// （templates/card-library.php）と同じ組み合わせにそろえる。
			'icon'        => 'app' === $type ? 'smartphone' : 'sports_esports',
		);
	}

	return $suggestions;
}

/**
 * カテゴリ名から候補を作る。
 *
 * @param string $query 入力中のキーワード。
 * @return array<int, array<string, mixed>> 候補。
 */
function node_get_category_keyword_suggestions( string $query ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'number'     => NODE_SEARCH_SUGGEST_PER_SOURCE,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'search'     => $query,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$suggestions = array();
	foreach ( $terms as $term ) {
		$suggestions[] = array(
			'keyword'     => $term->name,
			'source'      => 'category',
			'sourceLabel' => 'カテゴリ',
			'icon'        => 'category',
			'count'       => (int) $term->count,
		);
	}

	return $suggestions;
}

/**
 * 同じ表示名の候補を1項目にまとめる。
 *
 * Node Library とカテゴリは、たとえば "Minecraft" のように同じ名前を持つことがある。
 * その場合は同じ検索キーワードとして扱い、候補行は1つだけ表示する。
 *
 * @param array<int, array<string, mixed>> $suggestions 候補。
 * @return array<int, array<string, mixed>> 統合済み候補。
 */
function node_merge_search_keyword_suggestions( array $suggestions ): array {
	$merged = array();
	$order  = array();

	foreach ( $suggestions as $suggestion ) {
		$keyword = isset( $suggestion['keyword'] ) ? trim( (string) $suggestion['keyword'] ) : '';
		if ( '' === $keyword ) {
			continue;
		}

		$key    = node_search_suggest_keyword_key( $keyword );
		$source = isset( $suggestion['source'] ) ? sanitize_key( (string) $suggestion['source'] ) : 'search';

		if ( ! isset( $merged[ $key ] ) ) {
			$merged[ $key ]             = $suggestion;
			$merged[ $key ]['keyword']  = $keyword;
			$merged[ $key ]['_sources'] = array();
			$order[]                    = $key;
		}

		if ( ! in_array( $source, $merged[ $key ]['_sources'], true ) ) {
			$merged[ $key ]['_sources'][] = $source;
		}

		if ( 'category' === $source && isset( $suggestion['count'] ) ) {
			$merged[ $key ]['count'] = (int) $suggestion['count'];
		}
	}

	$result = array();
	foreach ( $order as $key ) {
		$item    = $merged[ $key ];
		$sources = $item['_sources'];
		unset( $item['_sources'] );

		$item['source']      = implode( '+', $sources );
		$item['sourceLabel'] = node_search_suggest_source_label( $sources );
		$result[]            = $item;

		if ( count( $result ) >= NODE_SEARCH_SUGGEST_LIMIT ) {
			break;
		}
	}

	return $result;
}

/**
 * 候補の表示名を重複判定用に正規化する。
 */
function node_search_suggest_keyword_key( string $keyword ): string {
	$key = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $keyword ) ) ?? $keyword );

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $key, 'UTF-8' ) : strtolower( $key );
}

/**
 * 統合した候補種別を短い表示ラベルにする。
 *
 * @param string[] $sources 候補種別。
 */
function node_search_suggest_source_label( array $sources ): string {
	$labels = array();

	if ( in_array( 'library', $sources, true ) ) {
		$labels[] = 'ライブラリ';
	}

	if ( in_array( 'category', $sources, true ) ) {
		$labels[] = 'カテゴリ';
	}

	return implode( ' / ', $labels );
}
