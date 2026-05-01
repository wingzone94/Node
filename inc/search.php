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
		$types = (array) $params['m3_media_type'];
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
		$types = isset( $_GET['m3_media_type'] ) ? (array) $_GET['m3_media_type'] : array();
		
		foreach ( $types as $type ) {
			$pattern = '';
			if ( $type === 'image' ) $pattern = '<img|wp-block-image';
			elseif ( $type === 'video' ) $pattern = '<video|wp-block-video';
			elseif ( $type === 'map' ) $pattern = 'google.com/maps|wp-block-embed-google-maps';
			elseif ( $type === 'youtube' ) $pattern = 'youtube.com|youtu.be|wp-block-embed-youtube';
			elseif ( $type === 'sns' ) $pattern = 'twitter.com|x.com|instagram.com|tiktok.com|wp-block-embed-';
			elseif ( $type === 'download' ) $pattern = '\\.(pdf|zip|exe|dmg|rar|7z)';
			
			if ( ! empty( $pattern ) ) {
				// 各条件を AND で結合
				$where .= " AND {$wpdb->posts}.post_content REGEXP '" . esc_sql( $pattern ) . "'";
			}
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

	$advanced_args = node_get_advanced_search_args( $_GET );

	if ( ! empty( $advanced_args['tax_query'] ) ) {
		$query->set( 'tax_query', $advanced_args['tax_query'] );
	}
	if ( ! empty( $advanced_args['meta_query'] ) ) {
		$query->set( 'meta_query', $advanced_args['meta_query'] );
	}
	if ( ! empty( $advanced_args['date_query'] ) ) {
		$query->set( 'date_query', $advanced_args['date_query'] );
	}

	// 並び替え
	$sort = isset( $_GET['m3_sort'] ) ? sanitize_text_field( $_GET['m3_sort'] ) : '';
	if ( $sort === 'word_count' ) {
		$query->set( 'meta_key', '_node_char_count' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'DESC' );
	} elseif ( $sort === 'oldest' ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'ASC' );
	} elseif ( $sort === 'newest' ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	} elseif ( $sort === 'alpha' ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'node_advanced_search_query' );

/**
 * AJAXで検索ヒット件数を取得する
 */
function node_ajax_get_search_count() {
	$args = node_get_advanced_search_args( $_GET );
	$args['posts_per_page'] = -1;
	$args['fields']         = 'ids';
	$args['node_is_ajax_search'] = true; // カスタムフラグを付与
	
	// AJAX時は他のアクションを妨げないよう、一時的にメインクエリっぽく振る舞わせる（必要なら）
	$query = new WP_Query( $args );
	$count = $query->found_posts;
	
	wp_send_json_success( array( 'count' => $count ) );
}
add_action( 'wp_ajax_node_get_search_count', 'node_ajax_get_search_count' );
add_action( 'wp_ajax_nopriv_node_get_search_count', 'node_ajax_get_search_count' );
