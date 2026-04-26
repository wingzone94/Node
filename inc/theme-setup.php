<?php
// サイト名を強制的に Luminous Core に変更
add_filter('pre_option_blogname', function() {
    return 'Luminous Core';
});
// 表示件数の動的制御 (Mobile: 16, PC: 32)
function node_modify_posts_per_page($query) {
    if (!is_admin() && $query->is_main_query() && (is_home() || is_archive() || is_search())) {
        if (wp_is_mobile()) {
            $query->set('posts_per_page', 8);   // Mobile: 1列×8件 - スクロール負荷を軽減
        } else {
            $query->set('posts_per_page', 16);  // PC: 4列×4行 - グリッドに最適
        }
    }
}
add_action('pre_get_posts', 'node_modify_posts_per_page');
// メニュー登録
function node_register_menus() {
    register_nav_menus([
        'primary' => 'ヘッダーメニュー',
        'drawer'  => 'サイドドロワーメニュー（カテゴリ等）'
    ]);
}
add_action('after_setup_theme', 'node_register_menus');
function node_enqueue_assets() {
    $version = '0.4.0';
    wp_enqueue_style('node-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_style('node-assets-style', get_template_directory_uri() . '/assets/css/style.css', [], $version);
    wp_enqueue_style('node-blocks-style', get_template_directory_uri() . '/assets/css/blocks.css', [], $version);

    wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', [], null, true);
    wp_enqueue_script('gsap-scrollto', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js', ['gsap'], null, true);
    wp_enqueue_script('node-main-js', get_template_directory_uri() . '/assets/js/main.js', ['gsap', 'gsap-scrollto'], $version, true);
    wp_enqueue_script('node-blocks-js', get_template_directory_uri() . '/assets/js/blocks.js', ['gsap'], $version, true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

/**
 * アーカイブのタイトルから「カテゴリー: 」などを削除
 */
add_filter('get_the_archive_title', function ($title) {
    if (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_post_type_archive()) {
        $title = post_type_archive_title('', false);
    } elseif (is_tax()) {
        $title = single_term_title('', false);
    }
    return $title;
});

/**
 * SEO/AMP: 構造化データ (JSON-LD) の追加
 */
function node_add_json_ld() {
    if (is_single()) {
        global $post;
        $json = [
            "@context" => "https://schema.org",
            "@type" => "BlogPosting",
            "headline" => get_the_title(),
            "image" => [get_the_post_thumbnail_url($post->ID, 'full')],
            "datePublished" => get_the_date('c'),
            "dateModified" => get_the_modified_date('c'),
            "author" => [
                "@type" => "Person",
                "name" => get_the_author(),
                "url" => get_author_posts_url(get_the_author_meta('ID'))
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => get_bloginfo('name'),
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => get_template_directory_uri() . '/pwa-icon.png'
                ]
            ],
            "description" => get_the_excerpt()
        ];
        echo "\n" . '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
add_action('wp_head', 'node_add_json_ld');

/**
 * Safari/Chrome用テーマカラーの強制出力 (最優先)
 */
function node_force_theme_color_meta() {
    echo '<meta name="theme-color" content="#FF9900">' . "\n";
    echo '<meta name="msapplication-TileColor" content="#FF9900">' . "\n";
    // Safariのレンダリングエンジン向けに、最上部の背景色を認識させるためのスクリプト
    echo '<script>document.documentElement.style.backgroundColor = "#FF9900";</script>' . "\n";
}
add_action('wp_head', 'node_force_theme_color_meta', 9999);

function node_enqueue_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php', 'term.php', 'edit-tags.php'])) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('node-admin-js', get_template_directory_uri() . '/assets/js/editor.js', ['wp-color-picker', 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post'], null, true);
    wp_add_inline_script('node-admin-js', 'jQuery(function($){ $(".node-color-picker").wpColorPicker(); });');
}
add_action('admin_enqueue_scripts', 'node_enqueue_admin_assets');

// テーマサポート
add_theme_support('post-thumbnails');
add_theme_support('responsive-embeds');
add_theme_support('align-wide'); // YouTube等のブロックで「幅広」「全幅」や配置ポップアップを表示するために必須
/**
 * Anti-FOUC: 即座にカラーテーマ（システム同期 or 手動）を反映するインラインスクリプト
 */
function node_anti_fouc_script() {
    ?>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('theme');
            var sync = localStorage.getItem('theme-sync') !== 'false'; // デフォルトは同期ON
            var isSysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (sync) {
                document.documentElement.setAttribute('data-theme', isSysDark ? 'dark' : 'light');
            } else if (theme) {
                document.documentElement.setAttribute('data-theme', theme);
            } else {
                document.documentElement.setAttribute('data-theme', isSysDark ? 'dark' : 'light');
            }
        } catch(e) {}
    })();
    </script>
    <?php
}
add_action('wp_head', 'node_anti_fouc_script', 1);
