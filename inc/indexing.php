<?php

declare(strict_types=1);
/**
 * 検索インデックス制御（title / canonical / robots / sitemap）。
 *
 * Google Search Console の「ページがインデックスに登録されなかった理由」で
 * 実測した3系統の問題に対応する（2026-08-08 本番スキャン）。
 *
 *   1. 検出/クロール済み - インデックス未登録（155件）
 *      タクソノミーアーカイブ157件のうち91件が収録記事1件。とくにタグは
 *      101件中79件が1記事で、実質その記事の複製でしかない。クロール予算を
 *      薄いアーカイブが食い潰していた。→ 閾値未満は noindex + サイトマップ除外。
 *
 *   2. ページにリダイレクトがあります（11件）
 *      `/category/spotlight/` は wp-sitemap に載るが `/spotlight/` へ301する
 *      （functions.php の node_redirect_spotlight_category_archive）。
 *      サイトマップが転送元を案内していた。→ サイトマップから除外。
 *
 *   3. 重複しています。ユーザーにより、正規ページとして選択されていません（3件）
 *      `/spotlight/` と `/all-articles/` は独自リライト（node_spotlight /
 *      node_all_articles）で is_archive / is_home / is_singular が全て false に
 *      なるため、`<title>` がサイト名のみ・canonical 無しになっていた。
 *      NODE-1.3.md §16.1 で `/headlines/` について実測したのと同じ症状。
 *      HEADLINE は単一カテゴリの別名なので廃止したが、この2つは標準アーカイブに
 *      代替物が無いため、廃止ではなく title / canonical を与えて正規化する。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * タグアーカイブを索引対象にする最小記事数。
 *
 * 既定の 2 は「収録1件のタグは、その記事そのものの複製」という判断。
 * 3 以上に上げるとより攻めた間引きになる（本番実測では 2 で79件、3 で89件が対象）。
 * `node_thin_archive_threshold` フィルタで変更できる。
 */
const NODE_THIN_ARCHIVE_THRESHOLD = 2;

/**
 * 薄いアーカイブと見なす下限記事数を返す。
 */
function node_thin_archive_threshold(): int {
	$threshold = (int) apply_filters( 'node_thin_archive_threshold', NODE_THIN_ARCHIVE_THRESHOLD );

	return max( 1, $threshold );
}

/**
 * 指定タームが「薄い」（収録記事が閾値未満）か。
 *
 * @param WP_Term|null $term 判定するターム。
 */
function node_is_thin_term( $term ): bool {
	if ( ! $term instanceof WP_Term ) {
		return false;
	}

	// 間引くのはタグのみ。カテゴリはサイトの構造を表すので件数が少なくても残す。
	if ( 'post_tag' !== $term->taxonomy ) {
		return false;
	}

	return $term->count < node_thin_archive_threshold();
}

/**
 * 独自リライトの一覧ページか判定する。
 *
 * @return string '' / 'spotlight' / 'all_articles'
 */
function node_current_custom_archive(): string {
	if ( get_query_var( 'node_spotlight' ) ) {
		return 'spotlight';
	}

	if ( get_query_var( 'node_all_articles' ) ) {
		return 'all_articles';
	}

	return '';
}

/**
 * 独自リライト一覧ページに `<title>` を与える。
 *
 * これらは is_archive / is_home / is_singular が全て false のため、
 * WordPress 標準のタイトル生成がサイト名だけを返してしまう。
 *
 * @param array<string, string> $parts タイトル要素。
 * @return array<string, string>
 */
function node_indexing_document_title_parts( array $parts ): array {
	$archive = node_current_custom_archive();

	if ( '' === $archive ) {
		return $parts;
	}

	$parts['title'] = 'spotlight' === $archive
		? __( 'SPOTLIGHT 特集一覧', 'node' )
		: __( '記事一覧', 'node' );

	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 ) {
		/* translators: %s: ページ番号 */
		$parts['page'] = sprintf( __( '%s ページ目', 'node' ), number_format_i18n( $paged ) );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'node_indexing_document_title_parts' );

/**
 * 独自リライト一覧ページに自己参照 canonical を出力する。
 *
 * 標準の rel_canonical() は is_singular() のみが対象なので、ここで補う。
 * ページ送りは各ページを自己参照にする（Google は rel=next/prev を見ないため、
 * 2ページ目以降を1ページ目に集約すると本文が正規化先に存在しない状態になる）。
 */
function node_indexing_render_canonical(): void {
	$archive = node_current_custom_archive();

	if ( '' === $archive ) {
		return;
	}

	$base = 'spotlight' === $archive
		? node_get_spotlight_url()
		: home_url( '/' . NODE_ALL_ARTICLES_SLUG . '/' );

	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 ) {
		$base = trailingslashit( $base ) . 'page/' . $paged . '/';
	}

	printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $base ) );
}
add_action( 'wp_head', 'node_indexing_render_canonical', 10 );

/**
 * 索引に載せる価値の無いアーカイブを noindex にする。
 *
 * いずれも `follow` は残す（リンク先の記事は辿ってほしいため）。
 *
 * @param array<string, bool|string> $robots robotsディレクティブ。
 * @return array<string, bool|string>
 */
function node_indexing_robots( array $robots ): array {
	$should_noindex = false;

	// 日付アーカイブ（/2026/ /2026/08/ /2026/08/07/）。
	// 記事一覧の切り口違いでしかなく、サイトマップにも載っていない。
	if ( is_date() ) {
		$should_noindex = true;
	}

	// 収録記事が閾値未満のタグアーカイブ。
	if ( is_tag() && node_is_thin_term( get_queried_object() ) ) {
		$should_noindex = true;
	}

	if ( $should_noindex ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['nofollow'] );
	}

	return $robots;
}
add_filter( 'wp_robots', 'node_indexing_robots' );

/**
 * サイトマップから除外するタームIDを集める。
 *
 * noindex とサイトマップ除外は対で行う。noindex だけ付けてサイトマップに残すと
 * 「送信された URL に noindex タグが追加されています」というエラーに変わるだけで、
 * 問題が別の欄へ移動する。
 *
 * 除外は `wp_sitemaps_taxonomies_entry` ではなくクエリ引数側で行う。
 * 前者は WP_Sitemaps_Taxonomies::get_url_list() が戻り値を無条件に
 * `$url_list[]` へ push するため、空配列を返すと「除外」ではなく
 * 「空の項目が1件入る」ことになる（wp-includes/sitemaps/providers/
 * class-wp-sitemaps-taxonomies.php）。`exclude` で引く方がページ番号の
 * 計算（get_max_num_pages）とも整合する。
 *
 * @param string $taxonomy タクソノミー名。
 * @return array<int, int> 除外するタームID。
 */
function node_indexing_sitemap_excluded_term_ids( string $taxonomy ): array {
	$excluded = array();

	if ( 'post_tag' === $taxonomy ) {
		// WP_Term_Query に count の範囲指定が無いため全件取って絞り込む。
		// `fields => 'all'` で ID ではなくターム本体を取り、件数判定のために
		// 1件ずつ get_term() を呼ぶ（＝タグ数ぶんのクエリ）ことを避ける。
		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'fields'     => 'all',
				'number'     => 0,
			)
		);

		if ( ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				if ( node_is_thin_term( $tag ) ) {
					$excluded[] = (int) $tag->term_id;
				}
			}
		}
	}

	// `/category/spotlight/` は node_redirect_spotlight_category_archive() が
	// `/spotlight/` へ301するため、サイトマップに載せると転送元を案内してしまう。
	// 子カテゴリ（/category/spotlight/wwdc26/ 等）は転送されないので残す。
	if ( 'category' === $taxonomy ) {
		$spotlight = get_term_by( 'slug', 'spotlight', 'category' );
		if ( $spotlight instanceof WP_Term ) {
			$excluded[] = (int) $spotlight->term_id;
		}
	}

	return $excluded;
}

/**
 * サイトマップのタームクエリから除外対象を引く。
 *
 * @param array<string, mixed> $args     WP_Term_Query 引数。
 * @param string               $taxonomy タクソノミー名。
 * @return array<string, mixed>
 */
function node_indexing_sitemap_taxonomies_query_args( array $args, string $taxonomy ): array {
	$excluded = node_indexing_sitemap_excluded_term_ids( $taxonomy );

	if ( empty( $excluded ) ) {
		return $args;
	}

	$current = isset( $args['exclude'] ) ? (array) $args['exclude'] : array();

	$args['exclude'] = array_values( array_unique( array_merge( $current, $excluded ) ) );

	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'node_indexing_sitemap_taxonomies_query_args', 10, 2 );

/*
 * `/spotlight/` と `/all-articles/` はサイトマップに追加していない。
 * 独自プロバイダ（WP_Sitemaps_Provider の実装）が必要になる一方、どちらも
 * トップページから常時リンクされており、`/spotlight/` は
 * `/category/spotlight/` からの301の転送先でもあるため発見性は足りている。
 * canonical と title を与えたことで重複判定の材料は解消済み。
 */
