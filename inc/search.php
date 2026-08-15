<?php
/**
 * Luminous Core — Advanced Search Logic
 * 
 * 詳細検索機能（カテゴリ、タグ、文字数指定、ワイルドカード等）の
 * バックエンド処理を管理します。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 投稿保存時に文字数をメタデータとして保存する
 */
function node_save_post_char_count( $post_id ) {
	// 自動保存やクイック編集、リビジョンは対象外
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	if ( wp_is_post_revision( $post_id ) ) return;

	$post = get_post( $post_id );
	if ( $post->post_type !== 'post' ) return;

	// 文字数を計算（タグやショートコードを除去）
	$content = $post->post_content;
	$chars = mb_strlen( strip_tags( strip_shortcodes( $content ) ), 'UTF-8' );

	update_post_meta( $post_id, '_node_char_count', $chars );
}
add_action( 'save_post', 'node_save_post_char_count' );

/**
 * 詳細検索パラメータに基づいてクエリ引数を生成するヘルパー関数
 */
function node_get_advanced_search_args( $params ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'meta_query'     => array(),
		'tax_query'      => array(),
	);

	if ( ! empty( $params['s'] ) ) {
		$args['s'] = sanitize_text_field( $params['s'] );
	}

	// 1. カテゴリ指定
	if ( ! empty( $params['m3_cat'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => intval( $params['m3_cat'] ),
		);
	}

	// 1.5 タグ指定
	if ( ! empty( $params['m3_tag'] ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'name',
			'terms'    => sanitize_text_field( $params['m3_tag'] ),
		);
	}

	// 2. 文字数範囲指定 (0-10000の場合はフィルタしない)
	$min_chars = isset( $params['m3_min'] ) ? intval( $params['m3_min'] ) : 0;
	$max_chars = isset( $params['m3_max'] ) ? intval( $params['m3_max'] ) : 10000;

	if ( $min_chars > 0 || $max_chars < 10000 ) {
		$meta_range = array(
			'key'  => '_node_char_count',
			'type' => 'NUMERIC',
		);
		
		if ( $min_chars > 0 && $max_chars < 10000 ) {
			$meta_range['value']   = array( $min_chars, $max_chars );
			$meta_range['compare'] = 'BETWEEN';
		} elseif ( $min_chars > 0 ) {
			$meta_range['value']   = $min_chars;
			$meta_range['compare'] = '>=';
		} else {
			// maxのみ指定されている場合、メタデータがない記事（0文字扱い）も含める
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_node_char_count',
					'value'   => $max_chars,
					'type'    => 'NUMERIC',
					'compare' => '<=',
				),
				array(
					'key'     => '_node_char_count',
					'compare' => 'NOT EXISTS',
				),
			);
			$meta_range = null; // 上記で追加済み
		}
		
		if ( $meta_range ) {
			$args['meta_query'][] = $meta_range;
		}
	}

	// 3. 日付範囲指定
	$date_query = array();
	if ( ! empty( $params['m3_start_date'] ) ) {
		$date_query['after'] = sanitize_text_field( $params['m3_start_date'] );
	}
	if ( ! empty( $params['m3_end_date'] ) ) {
		$date_query['before'] = sanitize_text_field( $params['m3_end_date'] );
	}
	if ( ! empty( $date_query ) ) {
		$date_query['inclusive'] = true;
		$args['date_query'] = array( $date_query );
	}

	// 3.5 並び順（メイン検索と件数取得で同じ契約を共有する）
	$sort = isset( $params['m3_sort'] ) ? sanitize_key( $params['m3_sort'] ) : '';
	if ( 'word_count' === $sort ) {
		$args['meta_key'] = '_node_char_count';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
	} elseif ( 'oldest' === $sort ) {
		$args['orderby'] = 'date';
		$args['order']   = 'ASC';
	} elseif ( 'newest' === $sort ) {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	} elseif ( 'alpha' === $sort ) {
		$args['orderby'] = 'title';
		$args['order']   = 'ASC';
	}

	// 4. AI生成フィルタ
	if ( ! empty( $params['m3_ai'] ) && $params['m3_ai'] !== 'all' ) {
		if ( $params['m3_ai'] === 'only' ) {
			$args['meta_query'][] = array(
				'key'     => '_node_ai_generated',
				'value'   => '1',
				'compare' => '=',
			);
		} else {
			$args['meta_query'][] = array(
				'key'     => '_node_ai_generated',
				'compare' => 'NOT EXISTS',
			);
		}
	}

	// 5. プラットフォーム指定
	if ( ! empty( $params['m3_platform'] ) ) {
		$platforms = (array) $params['m3_platform'];
		$platform_query = array( 'relation' => 'OR' );
		foreach ( $platforms as $p ) {
			$platform_query[] = array(
				'key'     => '_node_platforms',
				'value'   => sanitize_text_field( $p ),
				'compare' => 'LIKE',
			);
		}
		$args['meta_query'][] = $platform_query;
	}

	// 5.5 メディアタイプ・埋め込みフィルタ (Content Search)
	if ( ! empty( $params['m3_media_type'] ) ) {
		$allowed_types = array( 'image', 'video', 'map', 'youtube', 'sns', 'download' );
		$types         = array_map( 'sanitize_key', (array) $params['m3_media_type'] );
		$types         = array_values( array_intersect( $types, $allowed_types ) );

		if ( empty( $types ) ) {
			return $args;
		}

		// リクエストのグローバル値ではなく、WP_Query 自身へ条件を渡す。
		// これによりGETの検索結果とPOSTのAJAX件数が同じ条件で評価される。
		$args['node_media_types'] = $types;
		// フィルタを一回だけ追加するようにする（グローバルなどで管理）
		if ( ! has_filter( 'posts_where', 'node_content_media_search_filter' ) ) {
			add_filter( 'posts_where', 'node_content_media_search_filter', 10, 2 );
		}
	}

	return $args;
}

/**
 * post_content に対して正規表現で検索を行う (Media Type Filter)
 */
function node_content_media_search_filter( $where, $query ) {
	global $wpdb;

	// メインクエリまたは特定の AJAX クエリでのみ動作させる
	if ( ( $query->is_main_query() && $query->is_search() ) || $query->get( 'node_is_ajax_search' ) ) {
		$types      = (array) $query->get( 'node_media_types' );
		$conditions = array();
		
		foreach ( $types as $type ) {
			$pattern = '';
			if ( $type === 'image' ) $pattern = '<img|wp-block-image';
			elseif ( $type === 'video' ) $pattern = '<video|wp-block-video';
			elseif ( $type === 'map' ) $pattern = 'google.com/maps|wp-block-embed-google-maps';
			elseif ( $type === 'youtube' ) $pattern = 'youtube.com|youtu.be|wp-block-embed-youtube';
			elseif ( $type === 'sns' ) $pattern = 'twitter.com|x.com|instagram.com|tiktok.com|wp-block-embed-';
			elseif ( $type === 'download' ) $pattern = '\\.(pdf|zip|exe|dmg|rar|7z)';
			
			if ( ! empty( $pattern ) ) {
				$conditions[] = "{$wpdb->posts}.post_content REGEXP '" . esc_sql( $pattern ) . "'";
			}
		}

		// 同一ファセット内の複数選択はOR。例:「画像」「動画」なら、どちらかを含む記事。
		if ( ! empty( $conditions ) ) {
			$where .= ' AND (' . implode( ' OR ', $conditions ) . ')';
		}
	}

	return $where;
}

/**
 * 検索クエリを詳細検索パラメータに基づいて拡張する (メインクエリ用)
 */
function node_advanced_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	// 件数取得AJAX（node_get_search_count_for_params）と同じ母集合で検索する。
	// これが無いとメイン検索に固定ページ等が混ざり、モーダルの件数表示と結果が食い違う。
	$query->set( 'post_type', 'post' );
	$query->set( 'post_status', 'publish' );

	$advanced_args = node_get_advanced_search_args( $_GET );

	$forwarded_keys = array(
		'tax_query',
		'meta_query',
		'date_query',
		'meta_key',
		'orderby',
		'order',
		'node_media_types',
	);

	foreach ( $forwarded_keys as $key ) {
		if ( ! empty( $advanced_args[ $key ] ) ) {
			$query->set( $key, $advanced_args[ $key ] );
		}
	}
}
add_action( 'pre_get_posts', 'node_advanced_search_query' );

/**
 * 件数取得AJAXで使うPOSTパラメータを返す。
 *
 * @return array<string, mixed>
 */
function node_get_search_count_request_params() {
	return wp_unslash( $_POST );
}

/**
 * 詳細検索条件に一致する公開記事数を返す。
 *
 * @param array<string, mixed> $params 検索条件。
 */
function node_get_search_count_for_params( $params ) {
	$args                       = node_get_advanced_search_args( $params );
	$args['posts_per_page']     = 1;
	$args['fields']             = 'ids';
	$args['no_found_rows']      = false;
	$args['node_is_ajax_search'] = true;

	$query = new WP_Query( $args );

	return (int) $query->found_posts;
}

/**
 * AJAXで検索ヒット件数を取得する
 */
function node_ajax_get_search_count() {
	$count = node_get_search_count_for_params( node_get_search_count_request_params() );

	wp_send_json_success( array( 'count' => $count ) );
}
add_action( 'wp_ajax_node_get_search_count', 'node_ajax_get_search_count' );
add_action( 'wp_ajax_nopriv_node_get_search_count', 'node_ajax_get_search_count' );
