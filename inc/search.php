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
 * 検索クエリを詳細検索パラメータに基づいて拡張する
 */
function node_advanced_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$meta_query = array();
	$tax_query  = array();

	// 1. カテゴリ指定 (単一)
	if ( ! empty( $_GET['m3_cat'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => intval( $_GET['m3_cat'] ),
		);
	}

	// 1.5 タグ指定
	if ( ! empty( $_GET['m3_tag'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'name',
			'terms'    => sanitize_text_field( $_GET['m3_tag'] ),
		);
	}

	// 2. 文字数範囲指定
	$min_chars = isset( $_GET['m3_min'] ) ? intval( $_GET['m3_min'] ) : 0;
	$max_chars = isset( $_GET['m3_max'] ) ? intval( $_GET['m3_max'] ) : 0;

	if ( $min_chars > 0 || $max_chars > 0 ) {
		$meta_range = array(
			'key'     => '_node_char_count',
			'type'    => 'NUMERIC',
		);

		if ( $min_chars > 0 && $max_chars > 0 ) {
			$meta_range['value']   = array( $min_chars, $max_chars );
			$meta_range['compare'] = 'BETWEEN';
		} elseif ( $min_chars > 0 ) {
			$meta_range['value']   = $min_chars;
			$meta_range['compare'] = '>=';
		} else {
			$meta_range['value']   = $max_chars;
			$meta_range['compare'] = '<=';
		}
		$meta_query[] = $meta_range;
	}

	// 3. 日付範囲指定
	$date_query = array();
	if ( ! empty( $_GET['m3_start_date'] ) ) {
		$date_query['after'] = sanitize_text_field( $_GET['m3_start_date'] );
	}
	if ( ! empty( $_GET['m3_end_date'] ) ) {
		$date_query['before'] = sanitize_text_field( $_GET['m3_end_date'] );
	}
	if ( ! empty( $date_query ) ) {
		$date_query['inclusive'] = true;
		$query->set( 'date_query', array( $date_query ) );
	}

	// 4. タイトル内単語検索
	if ( ! empty( $_GET['m3_title_word'] ) ) {
		add_filter( 'posts_where', function ( $where, $query ) {
			global $wpdb;
			$title_word = sanitize_text_field( $_GET['m3_title_word'] );
			$where .= " AND {$wpdb->posts}.post_title LIKE '%" . $wpdb->esc_like( $title_word ) . "%'";
			return $where;
		}, 10, 2 );
	}

	// 5. メディア設定フィルタ
	$media_filter = isset( $_GET['m3_media'] ) ? sanitize_text_field( $_GET['m3_media'] ) : 'all';
	if ( $media_filter === 'image' ) {
		$meta_query[] = array(
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		);
	} elseif ( $media_filter === 'video' ) {
		// 動画投稿フォーマットまたは特定の動画メタデータをチェック
		$tax_query[] = array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-video' ),
		);
		// あるいはメタデータがある場合
		$meta_query[] = array(
			'key'     => '_node_video_url',
			'compare' => 'EXISTS',
		);
		$meta_query['relation'] = 'OR';
	} elseif ( $media_filter === 'none' ) {
		$meta_query[] = array(
			'key'     => '_thumbnail_id',
			'compare' => 'NOT EXISTS',
		);
	}

	// 6. プラットフォーム・ハードウェア指定
	if ( ! empty( $_GET['m3_platform'] ) && is_array( $_GET['m3_platform'] ) ) {
		$platforms = array_map( 'sanitize_text_field', $_GET['m3_platform'] );
		$all_definitions = node_get_platforms_by_category();
		
		$resolved_platforms = [];
		foreach ( $platforms as $p ) {
			if ( isset( $all_definitions[ $p ] ) ) {
				$resolved_platforms = array_merge( $resolved_platforms, $all_definitions[ $p ]['items'] );
			} else {
				$resolved_platforms[] = $p;
			}
		}
		$resolved_platforms = array_unique( $resolved_platforms );

		$platform_query = array( 'relation' => 'OR' );
		foreach ( $resolved_platforms as $p ) {
			$platform_query[] = array(
				'key'     => '_node_platforms', // プラットフォーム情報はここに保存されている想定
				'value'   => $p,
				'compare' => 'LIKE',
			);
		}
		$meta_query[] = $platform_query;
	}

	// タックスクエリとメタクエリを反映
	if ( ! empty( $tax_query ) ) {
		$query->set( 'tax_query', $tax_query );
	}
	if ( ! empty( $meta_query ) ) {
		$query->set( 'meta_query', $meta_query );
	}

	// 7. 並び替え指定
	$sort = isset( $_GET['m3_sort'] ) ? sanitize_text_field( $_GET['m3_sort'] ) : '';
	if ( $sort === 'word_count' ) {
		$query->set( 'meta_key', '_node_char_count' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'DESC' );
	} elseif ( $sort === 'relevance' ) {
		$query->set( 'orderby', 'relevance' );
	} elseif ( $sort === 'date' ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'node_advanced_search_query' );
