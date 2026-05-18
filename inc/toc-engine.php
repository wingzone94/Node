<?php
/**
 * TOC Engine — Heading ID Auto-Generation
 *
 * 記事内の見出し（h2-h6）に自動的にIDを付与し、目次機能のジャンプを確実に動作させます。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function node_get_current_multipage_number() {
    global $page;

    $current_page = max( 1, (int) $page );
    if ( $current_page > 1 ) {
        return $current_page;
    }

    return max( 1, (int) get_query_var( 'page' ) );
}

function node_extract_heading_id_from_attr( $attr ) {
    if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attr, $match ) ) {
        return trim( $match[2] );
    }

    return '';
}

function node_normalize_toc_heading_text( $html ) {
    return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $html ) ) ) );
}

function node_create_toc_heading_id( $text, array &$used_ids ) {
    $base_id = sanitize_title( $text );

    if ( empty( $base_id ) ) {
        $base_id = 'section-' . substr( md5( $text ), 0, 8 );
    }

    $id     = $base_id;
    $suffix = 2;

    while ( in_array( $id, $used_ids, true ) ) {
        $id = $base_id . '-' . $suffix;
        $suffix++;
    }

    $used_ids[] = $id;

    return $id;
}

function node_get_article_toc_items( $post_id = null ) {
    $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
    if ( ! $post_id ) {
        return array();
    }

    static $cache = array();
    if ( isset( $cache[ $post_id ] ) ) {
        return $cache[ $post_id ];
    }

    $content = get_post_field( 'post_content', $post_id );
    if ( ! is_string( $content ) || '' === trim( $content ) ) {
        $cache[ $post_id ] = array();
        return $cache[ $post_id ];
    }

    $pages    = preg_split( '/<!--\s*nextpage\s*-->/i', $content );
    $items    = array();
    $used_ids = array();
    $pattern  = '/<(h[1-6])\b([^>]*)>(.*?)<\/\1>/is';

    foreach ( $pages as $page_index => $page_content ) {
        if ( ! preg_match_all( $pattern, $page_content, $matches, PREG_SET_ORDER ) ) {
            continue;
        }

        foreach ( $matches as $match ) {
            $text = node_normalize_toc_heading_text( $match[3] );
            if ( '' === $text ) {
                continue;
            }

            $existing_id = node_extract_heading_id_from_attr( $match[2] );
            $id          = $existing_id ? $existing_id : node_create_toc_heading_id( $text, $used_ids );

            if ( $existing_id && ! in_array( $existing_id, $used_ids, true ) ) {
                $used_ids[] = $existing_id;
            }

            $items[] = array(
                'id'    => $id,
                'level' => strtolower( $match[1] ),
                'text'  => $text,
                'page'  => $page_index + 1,
            );
        }
    }

    $cache[ $post_id ] = $items;

    return $cache[ $post_id ];
}

function node_get_multipage_url( $page_number, $post_id = null ) {
    $page_number = max( 1, (int) $page_number );
    $post_id     = $post_id ? (int) $post_id : (int) get_the_ID();

    if ( function_exists( '_wp_link_page' ) ) {
        $link = _wp_link_page( $page_number );
        if ( preg_match( '/href=(["\'])(.*?)\1/', $link, $match ) ) {
            return html_entity_decode( $match[2], ENT_QUOTES, get_bloginfo( 'charset' ) );
        }
    }

    $permalink = get_permalink( $post_id );
    if ( $page_number <= 1 ) {
        return $permalink;
    }

    return trailingslashit( $permalink ) . $page_number . '/';
}

function node_get_article_toc_export_items( $post_id = null ) {
    $post_id      = $post_id ? (int) $post_id : (int) get_the_ID();
    $current_page = node_get_current_multipage_number();

    return array_map(
        static function ( $item ) use ( $post_id, $current_page ) {
            $item['href']    = ( (int) $item['page'] === $current_page )
                ? '#' . $item['id']
                : node_get_multipage_url( (int) $item['page'], $post_id ) . '#' . $item['id'];
            $item['current'] = ( (int) $item['page'] === $current_page );

            return $item;
        },
        node_get_article_toc_items( $post_id )
    );
}

/**
 * 本文中の見出しタグに ID を自動付与する
 */
function node_add_heading_ids( $content ) {
    if ( ! is_singular() ) {
        return $content;
    }

    $toc_items    = node_get_article_toc_items( get_the_ID() );
    $current_page = node_get_current_multipage_number();
    $page_items   = array_values(
        array_filter(
            $toc_items,
            static function ( $item ) use ( $current_page ) {
                return (int) $item['page'] === $current_page;
            }
        )
    );
    $heading_index = 0;
    $used_ids      = array();
    $pattern       = '/<(h[1-6])\b([^>]*)>(.*?)<\/\1>/is';

    return preg_replace_callback(
        $pattern,
        static function ( $matches ) use ( &$heading_index, $page_items, &$used_ids ) {
            $text = node_normalize_toc_heading_text( $matches[3] );
            if ( '' === $text ) {
                return $matches[0];
            }

            $toc_item = $page_items[ $heading_index ] ?? null;
            $heading_index++;

            if ( node_extract_heading_id_from_attr( $matches[2] ) ) {
                return $matches[0];
            }

            $id = $toc_item['id'] ?? node_create_toc_heading_id( $text, $used_ids );

            return '<' . $matches[1] . $matches[2] . ' id="' . esc_attr( $id ) . '">' . $matches[3] . '</' . $matches[1] . '>';
        },
        $content
    );
}
add_filter( 'the_content', 'node_add_heading_ids', 15 );
