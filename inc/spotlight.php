<?php
/**
 * SPOTLIGHT記事を取得する (スラッグ 'spotlight' のカテゴリとその子)
 */
function node_get_spotlight_categories() {
    $cache_key = 'node_spotlight_categories_cache';
    $cached_result = get_transient($cache_key);

    if (false !== $cached_result) {
        return $cached_result;
    }

    $parent = get_category_by_slug('spotlight');
    if (!$parent) return [];

    $args = [
        'parent' => $parent->term_id,
        'hide_empty' => true,
        'update_term_meta_cache' => true
    ];

    $categories = get_terms('category', $args);
    $result = [];

    if (!is_wp_error($categories) && !empty($categories)) {
        foreach ($categories as $cat) {
            $color = get_term_meta($cat->term_id, '_m3_color', true);
            if (!$color) {
                $color = 'var(--md-sys-color-primary)';
            }
            $label = $cat->name;
            if ( ! preg_match( '/特集$/u', $label ) ) {
                $label .= '特集';
            }

            $result[] = [
                'name'        => $label,
                'slug'        => $cat->slug,
                'url'         => get_category_link( $cat->term_id ),
                'color'       => $color,
                'count'       => (int) $cat->count,
                'description' => term_description( $cat->term_id, 'category' ),
            ];
        }
    }

    // 12時間キャッシュ
    set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);

    return $result;
}

/**
 * キャッシュのクリア
 */
function node_clear_spotlight_cache() {
    delete_transient('node_spotlight_categories_cache');
}
add_action('create_category', 'node_clear_spotlight_cache');
add_action('edit_category', 'node_clear_spotlight_cache');
add_action('delete_category', 'node_clear_spotlight_cache');
add_action('edited_category', 'node_clear_spotlight_cache');
// 記事が更新された際（hide_emptyの状態が変わる可能性があるため）もクリア
add_action('save_post', 'node_clear_spotlight_cache');

/**
 * 管理画面の投稿一覧に『SPOTLIGHT』列を追加
 */
function node_add_spotlight_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['node_spotlight'] = 'SPOTLIGHT';
        }
    }
    return $new_columns;
}
add_filter('manage_post_posts_columns', 'node_add_spotlight_column');

/**
 * 管理画面の『SPOTLIGHT』列の内容を表示
 */
function node_render_spotlight_column($column, $post_id) {
    if ($column === 'node_spotlight') {
        if (has_category('spotlight', $post_id)) {
            echo '<span class="dashicons dashicons-star-filled" style="color: #FF9900;" title="SPOTLIGHTカテゴリに属しています"></span>';
        } else {
            echo '<span class="dashicons dashicons-star-empty" style="color: #ccc; opacity: 0.3;"></span>';
        }
    }
}
add_action('manage_post_posts_custom_column', 'node_render_spotlight_column', 10, 2);

/**
 * 管理画面の列幅を調整
 */
function node_admin_spotlight_style() {
    echo '<style>.column-node_spotlight { width: 100px; text-align: center; }</style>';
}
add_action('admin_head', 'node_admin_spotlight_style');
