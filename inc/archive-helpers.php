<?php
/**
 * Archive page context helpers (category, tag, author, date, taxonomy).
 *
 * @package Node
 */

/**
 * Material icon for a category archive header.
 *
 * @param WP_Term|null $term Category term.
 * @return string Material Symbols icon name.
 */
function node_get_category_archive_icon( $term ) {
	return 'folder';
}

/**
 * Build archive header context for the current query.
 *
 * @return array<string, mixed>
 */
function node_get_archive_context() {
	global $wp_query;

	$context = array(
		'type'         => 'archive',
		'title'        => '',
		'subtitle'     => __( 'アーカイブ', 'node' ),
		'icon'         => 'folder',
		'description'  => '',
		'count'        => isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0,
		'accent_color' => '',
		'header_id'    => 'archive-title',
	);

	if ( is_category() ) {
		$term = get_queried_object();
		$context['type']        = 'category';
		$context['title']       = single_cat_title( '', false );
		$context['subtitle']    = __( 'カテゴリ', 'node' );
		$context['icon']        = node_get_category_archive_icon( $term );
		$context['description'] = $term ? term_description( $term->term_id, 'category' ) : '';
		if ( $term && function_exists( 'node_get_category_label_props' ) ) {
			$context['label_props']  = node_get_category_label_props( $term );
			$context['accent_color'] = $context['label_props']['color'];
		}
	} elseif ( is_tag() ) {
		$term = get_queried_object();
		$context['type']        = 'tag';
		$context['title']       = single_tag_title( '', false );
		$context['subtitle']    = __( 'タグ', 'node' );
		$context['icon']        = 'folder';
		$context['description'] = $term ? term_description( $term->term_id, 'post_tag' ) : '';
	} elseif ( is_author() ) {
		$author_id = get_queried_object_id();
		$context['type']     = 'author';
		$context['title']    = get_the_author();
		$context['subtitle'] = __( '著者', 'node' );
		$context['icon']     = 'folder';
		$bio                 = get_the_author_meta( 'description', $author_id );
		if ( $bio ) {
			$context['description'] = wp_kses_post( $bio );
		}
	} elseif ( is_year() ) {
		$context['type']     = 'date';
		$context['title']    = get_the_date( 'Y年' );
		$context['subtitle'] = __( '年別アーカイブ', 'node' );
		$context['icon']     = 'folder';
	} elseif ( is_month() ) {
		$context['type']     = 'date';
		$context['title']    = get_the_date( 'Y年n月' );
		$context['subtitle'] = __( '月別アーカイブ', 'node' );
		$context['icon']     = 'folder';
	} elseif ( is_day() ) {
		$context['type']     = 'date';
		$context['title']    = get_the_date( 'Y年n月j日' );
		$context['subtitle'] = __( '日別アーカイブ', 'node' );
		$context['icon']     = 'folder';
	} elseif ( is_tax() ) {
		$term = get_queried_object();
		$context['type']        = 'taxonomy';
		$context['title']       = single_term_title( '', false );
		$context['subtitle']    = __( 'アーカイブ', 'node' );
		$context['icon']        = 'folder';
		$context['description'] = term_description();
		if ( $term && isset( $term->term_id ) && function_exists( 'node_get_category_label_props' ) ) {
			$context['label_props']  = node_get_category_label_props( $term );
			$context['accent_color'] = $context['label_props']['color'];
		}
	} elseif ( is_post_type_archive() ) {
		$context['type']     = 'post_type';
		$context['title']    = post_type_archive_title( '', false );
		$context['subtitle'] = __( 'アーカイブ', 'node' );
		$context['icon']     = 'folder';
	} elseif ( function_exists( 'node_get_archive_title' ) ) {
		$context['title'] = node_get_archive_title();
	}

	return $context;
}

/**
 * Archive header mark style (same tokens as category labels).
 *
 * @param array<string, mixed> $context Archive context.
 * @return string
 */
function node_get_archive_mark_style( $context ) {
	if ( ! empty( $context['label_props'] ) && function_exists( 'node_get_category_label_style' ) ) {
		return node_get_category_label_style( $context['label_props'] );
	}

	$accent = ! empty( $context['accent_color'] ) ? $context['accent_color'] : '#FF9900';
	$on     = function_exists( 'node_get_readable_text_color' )
		? node_get_readable_text_color( $accent )
		: '#ffffff';

	return sprintf(
		'--category-color:%1$s;--category-on-color:%2$s;--m3-archive-accent:%1$s;',
		esc_attr( $accent ),
		esc_attr( $on )
	);
}

/**
 * Merge default archive context with template overrides.
 *
 * @param array<string, mixed> $overrides Optional overrides.
 * @return array<string, mixed>
 */
function node_merge_archive_context( $overrides = array() ) {
	$context = node_get_archive_context();

	foreach ( $overrides as $key => $value ) {
		if ( null === $value || '' === $value ) {
			continue;
		}
		$context[ $key ] = $value;
	}

	return $context;
}
