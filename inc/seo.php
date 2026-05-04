<?php
/**
 * SEO & Semantic HTML Functions
 *
 * 構造化データ (JSON-LD)、パンくずリスト、動的タイトル管理など
 * SEO対策の中核を担うロジック。
 *
 * @package Node
 */

/**
 * 1. 構造化データ (JSON-LD) の出力
 */
function node_seo_json_ld() {
    $payloads = [];

    // 1. Article Schema (Singular only)
    if ( is_singular() ) {
        $post_id = get_the_ID();
        $author_id = get_post_field( 'post_author', $post_id );
        
        $payloads[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'headline' => get_the_title(),
            'image'    => [ get_the_post_thumbnail_url( $post_id, 'large' ) ?: get_site_icon_url() ],
            'datePublished' => get_the_date( 'c' ),
            'dateModified'  => get_the_modified_date( 'c' ),
            'author' => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $author_id ),
                'url'   => get_author_posts_url( $author_id ),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => get_site_icon_url(),
                ],
            ],
            'description' => wp_trim_words( get_the_excerpt(), 160 ),
        ];
    }

    // 2. WebSite Schema (Home page only)
    if ( is_front_page() || is_home() ) {
        $payloads[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    foreach ( $payloads as $payload ) {
        echo "\n" . '<script type="application/ld+json">' . json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    }
}
add_action( 'wp_head', 'node_seo_json_ld' );

/**
 * 2. 標準メタディスクリプションの出力
 */
function node_seo_meta_description() {
    if ( is_singular() ) {
        $desc = get_the_excerpt();
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $desc = term_description();
    } else {
        $desc = get_bloginfo( 'description' );
    }

    $desc = wp_strip_all_tags( $desc );
    $desc = wp_trim_words( $desc, 160 );

    if ( $desc ) {
        echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'node_seo_meta_description', 1 );

/**
 * 3. パンくずリスト生成関数
 */
function node_the_breadcrumbs() {
    if ( is_front_page() || is_home() ) {
        return;
    }

    echo '<nav class="m3-breadcrumbs" aria-label="Breadcrumb">';
    echo '<ol class="m3-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';
    
    // Home
    echo '<li class="m3-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
    echo '<a itemprop="item" href="' . esc_url( home_url( '/' ) ) . '"><span itemprop="name">HOME</span></a>';
    echo '<meta itemprop="position" content="1" /></li>';

    if ( is_singular() ) {
        $categories = get_the_category();
        if ( ! empty( $categories ) ) {
            $cat = $categories[0];
            echo '<li class="m3-breadcrumbs__separator"><span class="material-symbols-outlined">chevron_right</span></li>';
            echo '<li class="m3-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            echo '<a itemprop="item" href="' . esc_url( get_category_link( $cat->term_id ) ) . '"><span itemprop="name">' . esc_html( $cat->name ) . '</span></a>';
            echo '<meta itemprop="position" content="2" /></li>';
        }
        echo '<li class="m3-breadcrumbs__separator"><span class="material-symbols-outlined">chevron_right</span></li>';
        echo '<li class="m3-breadcrumbs__item m3-breadcrumbs__item--current" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        echo '<span itemprop="name">' . esc_html( get_the_title() ) . '</span>';
        echo '<meta itemprop="position" content="3" /></li>';
    } elseif ( is_category() || is_tag() || is_tax() ) {
        echo '<li class="m3-breadcrumbs__separator"><span class="material-symbols-outlined">chevron_right</span></li>';
        echo '<li class="m3-breadcrumbs__item m3-breadcrumbs__item--current" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        echo '<span itemprop="name">' . esc_html( single_term_title( '', false ) ) . '</span>';
        echo '<meta itemprop="position" content="2" /></li>';
    } elseif ( is_search() ) {
        echo '<li class="m3-breadcrumbs__separator"><span class="material-symbols-outlined">chevron_right</span></li>';
        echo '<li class="m3-breadcrumbs__item m3-breadcrumbs__item--current" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        echo '<span itemprop="name">検索結果: ' . esc_html( get_search_query() ) . '</span>';
        echo '<meta itemprop="position" content="2" /></li>';
    }

    echo '</ol></nav>';
}

/**
 * 4. 動的アーカイブタイトル取得 (h1用)
 */
function node_get_archive_title() {
    if ( is_category() ) {
        return single_cat_title( '', false );
    } elseif ( is_tag() ) {
        return single_tag_title( '', false );
    } elseif ( is_author() ) {
        return get_the_author();
    } elseif ( is_year() ) {
        return get_the_date( 'Y年' );
    } elseif ( is_month() ) {
        return get_the_date( 'F Y' );
    } elseif ( is_day() ) {
        return get_the_date( 'Y年m月d日' );
    } elseif ( is_search() ) {
        return '検索結果: ' . get_search_query();
    } elseif ( is_home() ) {
        return get_bloginfo( 'name' );
    }
    return '記事一覧';
}
