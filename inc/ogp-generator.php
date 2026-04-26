<?php
/**
 * ==========================================================================
 * OGP自動生成機能 (Gemini APIなし、GDライブラリ使用)
 * アイキャッチがない場合に、タイトル入りの画像を動的に生成してSNSシェアに対応する。
 * ==========================================================================
 */

/**
 * 1. OGPタグの自動出力
 */
function node_ogp_head_output() {
    if (!is_singular()) return;

    $post_id = get_the_ID();
    $title   = get_the_title();
    $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 60);
    $url     = get_permalink();
    $image   = node_get_dynamic_og_image_url($post_id);

    echo "\n<!-- Luminous Core Dynamic OGP -->\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($excerpt) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($excerpt) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    echo "<!-- /Luminous Core Dynamic OGP -->\n\n";
}
add_action('wp_head', 'node_ogp_head_output', 5);

/**
 * 2. OGP画像のURL取得（未生成なら生成）
 */
function node_get_dynamic_og_image_url($post_id) {
    // アイキャッチがあればそれを使用
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, 'large');
    }

    $upload_dir = wp_upload_dir();
    $cache_dir  = $upload_dir['basedir'] . '/ogp-cache';
    $cache_url  = $upload_dir['baseurl'] . '/ogp-cache';
    $filename   = 'ogp-' . $post_id . '.png';
    $filepath   = $cache_dir . '/' . $filename;

    // キャッシュが存在すればそのURLを返す
    if (file_exists($filepath)) {
        return $cache_url . '/' . $filename . '?v=' . filemtime($filepath);
    }

    // なければ画像を生成してURLを返す
    return node_create_dynamic_ogp($post_id, $cache_dir, $cache_url, $filename);
}

/**
 * 3. GDライブラリによる画像生成コアロジック
 */
function node_create_dynamic_ogp($post_id, $cache_dir, $cache_url, $filename) {
    if (!extension_loaded('gd')) return '';

    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }

    $width  = 1200;
    $height = 630;
    $image  = imagecreatetruecolor($width, $height);

    // 背景色（記事のテーマカラーまたはデフォルトのオレンジ）
    $seed_color = '#FF9900';
    $category_color = null;
    $categories = get_the_category($post_id);
    if (!empty($categories)) {
        $category_color = get_term_meta($categories[0]->term_id, '_m3_color', true);
    }
    $seed_color = get_post_meta($post_id, '_m3_primary_color', true) ?: ($category_color ?: '#FF9900');

    list($r, $g, $b) = sscanf($seed_color, "#%02x%02x%02x");
    $bg_color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $bg_color);

    // 文字色（白）
    $text_color = imagecolorallocate($image, 255, 255, 255);

    // タイトル描画
    $title = get_the_title($post_id);
    
    // システムフォントの探索 (日本語対応)
    $font = '';
    $possible_fonts = [
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansJP-Bold.otf',
        '/usr/share/fonts/truetype/fonts-japanese-gothic.ttf',
        '/System/Library/Fonts/AppleSDGothicNeo.ttc', // Mac
        '/System/Library/Fonts/Hiragino Sans GB.ttc', // Mac
        '/Library/Fonts/Arial Unicode.ttf',
        '/usr/share/fonts/truetype/ipafont/ipag.ttf', // Linux
    ];
    foreach ($possible_fonts as $f) {
        if (file_exists($f)) { $font = $f; break; }
    }

    if ($font && function_exists('imagettftext')) {
        // タイトル描画（折り返し）
        $wrapped_title = node_mb_wordwrap($title, 18);
        imagettftext($image, 45, 0, 100, 200, $text_color, $font, $wrapped_title);
        
        // サイト名を描画
        imagettftext($image, 25, 0, 100, 550, $text_color, $font, get_bloginfo('name'));
    } else {
        // Fallback: 日本語不可の場合は英数字のみ
        imagestring($image, 5, 100, 100, "Luminous Core Article", $text_color);
        imagestring($image, 5, 100, 130, "OGP Image Generated", $text_color);
    }

    $dest = $cache_dir . '/' . $filename;
    imagepng($image, $dest);
    imagedestroy($image);

    return $cache_url . '/' . $filename;
}

/**
 * 日本語マルチバイト対応の簡易Wordwrap
 */
function node_mb_wordwrap($str, $width = 18) {
    $lines = [];
    $len = mb_strlen($str);
    for ($i = 0; $i < $len; $i += $width) {
        $lines[] = mb_substr($str, $i, $width);
    }
    return implode("\n", $lines);
}

/**
 * 記事更新時にOGPキャッシュをクリア
 */
function node_clear_ogp_cache($post_id) {
    $upload_dir = wp_upload_dir();
    $filepath   = $upload_dir['basedir'] . '/ogp-cache/ogp-' . $post_id . '.png';
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}
add_action('save_post', 'node_clear_ogp_cache');
