<?php
/**
 * SPOTLIGHT記事を取得する (スラッグ 'spotlight' のカテゴリとその子)
 */
function node_get_spotlight_categories() {
    $parent = get_category_by_slug('spotlight');
    if (!$parent) return [];

    $args = [
        'parent' => $parent->term_id,
        'hide_empty' => true // 記事がない特集カテゴリは非表示
    ];

    $categories = get_terms('category', $args);
    $result = [];

    if (!is_wp_error($categories) && !empty($categories)) {
        foreach ($categories as $cat) {
            $color = get_term_meta($cat->term_id, '_m3_color', true);
            if (!$color) {
                $color = 'var(--md-sys-color-primary)';
            }
            $result[] = [
                // 「文字の設定等は禁止」の要件通り、自動で「カテゴリ名＋特集」を生成
                'name' => $cat->name . '特集',
                'url' => get_category_link($cat->term_id),
                'color' => $color
            ];
        }
    }

    return $result;
}

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
