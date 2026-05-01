<?php
/**
 * 商品リンクのURLを生成（将来的にアフィリエイトIDを付与しやすいよう独立）
 */
function node_generate_product_link($url, $type = 'amazon') {
    if (empty($url)) return '';
    return esc_url($url);
}

/**
 * 商品リンク ショートコード [m3_product]
 * [product_card] へ一本化済み。後方互換性のためエイリアスとして維持。
 * 既存記事で [m3_product] を使用していても正常に動作します。
 */
add_shortcode('m3_product', 'node_product_card_shortcode');
// --- ユーティリティ ---

function node_get_relative_date($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $post_time = get_the_time('U', $post_id);
    $current_time = current_time('timestamp');
    $diff = intval($current_time) - intval($post_time);

    $full_date = get_the_date('Y年n月j日', $post_id);

    // 24時間（86400秒）以内の場合のみカッコ書きを入れる
    if ($diff > 0 && $diff < 86400) {
        $relative = '';
        if ($diff < 3600) {
            $relative = ($diff < 60) ? 'たった今' : floor($diff / 60) . '分前';
        } else {
            $relative = floor($diff / 3600) . '時間前';
        }
        return $full_date . ' （' . $relative . '）';
    }

    return $full_date;
}

function node_get_image_seed_color($attachment_id) {
    if (!$attachment_id) return null;
    $cached = get_post_meta($attachment_id, '_node_seed_color', true);
    if ($cached) return $cached;

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return null;

    $info = getimagesize($file_path);
    if (!$info) return null;

    $image = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($file_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($file_path); break;
        case IMAGETYPE_WEBP: $image = imagecreatefromwebp($file_path); break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($file_path); break;
    }
    if (!$image) return null;

    $pixel = imagecreatetruecolor(1, 1);
    imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
    $rgb = imagecolorat($pixel, 0, 0);
    $hex = sprintf("#%02x%02x%02x", ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);

    update_post_meta($attachment_id, '_node_seed_color', $hex);
    imagedestroy($image);
    imagedestroy($pixel);
    return $hex;
}
/**
 * 投稿・カテゴリのカラー設定を優先し、M3シードカラーを動的に生成する。
 * 優先順位: 投稿個別カラー > カテゴリカラー > アイキャッチ画像抽出色 > デフォルト
 * API は使用しない（保存済みメタから読み込むのみ）
 */
function node_generate_m3_colors() {
    // デフォルトカラー（Luminous Core ブランドカラー）
    $default_primary      = '#FF9900';
    $default_primary_dark = '#ffb85d';

    $seed_color      = '';
    $seed_color_dark = '';

    // 個別記事ページおよび固定ページ: 投稿メタ → カテゴリメタ → アイキャッチ抽出色 の順に解決
    if (is_singular(['post', 'page'])) {
        $post_id = get_the_ID();

        // 1. 投稿個別カラー
        $post_color = get_post_meta($post_id, '_m3_primary_color', true);
        if (!empty($post_color)) {
            $seed_color = sanitize_hex_color($post_color);
        }

        // 2. カテゴリカラー
        if (empty($seed_color)) {
            $categories = get_the_category($post_id);
            if (!empty($categories)) {
                $cat_color = get_term_meta($categories[0]->term_id, '_m3_color', true);
                if (!empty($cat_color)) {
                    $seed_color = sanitize_hex_color($cat_color);
                }
            }
        }

        // 3. アイキャッチ画像から抽出した色
        if (empty($seed_color)) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $extracted = node_get_image_seed_color($thumb_id);
                if (!empty($extracted)) {
                    $seed_color = sanitize_hex_color($extracted);
                }
            }
        }
    }

    // アーカイブページ: カテゴリカラーを使用
    if (is_category()) {
        $cat_color = get_term_meta(get_queried_object_id(), '_m3_color', true);
        if (!empty($cat_color)) {
            $seed_color = sanitize_hex_color($cat_color);
        }
    }

    // フォールバック: デフォルトカラー
    if (empty($seed_color)) {
        $seed_color = $default_primary;
    }
    if (empty($seed_color_dark)) {
        $seed_color_dark = $default_primary_dark;
    }
    ?>
    <style id="m3-dynamic-colors">
        :root {
            --md-sys-color-primary: <?php echo esc_attr($seed_color); ?>;
            --md-sys-color-on-primary: #ffffff;
            --md-sys-color-primary-container: #ffdcbe;
            --md-sys-color-on-primary-container: #2c1600;
            --md-sys-color-surface: #FFF4E5; /* Warm Orange Background */
            --md-sys-color-on-surface: #2b1700;
            --md-sys-color-surface-container-low: #ffffff;
            --md-sys-color-surface-container: #ffe8d1;
            --md-sys-color-surface-container-high: #f7ddc6;
            --md-sys-color-outline: #857362;
            --md-sys-color-outline-variant: #d6c2b1;
        }
        [data-theme="dark"] {
            --md-sys-color-primary: <?php echo esc_attr($seed_color_dark); ?>;
            --md-sys-color-on-primary: #4a2800;
            --md-sys-color-surface: #1e1b16;
            --md-sys-color-on-surface: #ebe0d9;
            --md-sys-color-surface-container-low: #25221b;
            --md-sys-color-surface-container: #2a2720;
            --md-sys-color-surface-container-high: #322f28;
        }
    </style>
    <?php
}
add_action('wp_head', 'node_generate_m3_colors');
function node_the_category_labels($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $categories = get_the_category($post_id);
    if (empty($categories)) return;
    
    // JSのカラー抽出用にアイキャッチURLを取得
    $thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '';
    
    $is_card = !is_single();
    // カード表示なら3つ、シングルページならすべて（999個）表示
    $limit = $is_card ? 3 : 999;
    $count = count($categories);
    $display_cats = array_slice($categories, 0, $limit);

    echo '<div class="m3-article__category-group' . ($is_card ? ' is-card' : '') . '">';
    if (!$is_card) {
        echo '<span class="m3-article__category-label">CATEGORY</span>';
    }
    
    foreach ($display_cats as $cat) {
        // カテゴリの説明欄に #FFFFFF 形式の色指定があればそれを使う
        $cat_desc = $cat->description;
        $custom_color = 'auto';
        $style_attr = '';
        if (preg_match('/#[a-fA-F0-9]{6}/', $cat_desc, $matches)) {
            $custom_color = $matches[0];
            $style_attr = 'style="background-color: ' . esc_attr($custom_color) . '; color: #ffffff;"';
        }

        echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" ';
        echo 'class="m3-label--category" ';
        echo 'data-color="' . esc_attr($custom_color) . '" ';
        echo 'data-thumb="' . esc_url($thumb_url) . '" ';
        echo $style_attr;
        echo '>';
        echo '<span class="material-symbols-outlined">folder</span>' . esc_html($cat->name) . '</a>';
    }
    
    if ($count > $limit) {
        $remaining = $count - $limit;
        echo '<span class="m3-label--category-more" title="さらに ' . $remaining . ' 件のカテゴリがあります">+' . $remaining . '</span>';
    }
    echo '</div>';
}

function node_the_post_badges($post_id = null, $mode = 'compact') {
    if (!$post_id) $post_id = get_the_ID();
    
    // AI生成ラベル
    if (get_post_meta($post_id, '_node_is_ai_generated', true) === '1') {
        $ai_tooltip = 'AI生成されたメディアを含みます';
        $ai_class = 'm3-label--ai m3-tooltip-target';
        $pos_attr = '';
        if ($mode === 'compact') {
            $ai_class .= ' m3-label--icon-only';
            $pos_attr = ' data-tooltip-pos="left"';
        }

        echo '<span class="' . esc_attr($ai_class) . '" data-tooltip="' . esc_attr($ai_tooltip) . '"' . $pos_attr . '>';
        echo '<span class="material-symbols-outlined">auto_awesome</span>';
        if ($mode === 'full') {
            echo '<span class="m3-label__text">生成されたメディアを含む</span>';
        }
        echo '</span>';
    }

    // スポンサーラベル
    if (get_post_meta($post_id, '_node_is_sponsor', true) === '1') {
        $sponsor_text = get_post_meta($post_id, '_node_sponsor_text', true) ?: 'SPONSORED';
        
        if ($mode === 'full') {
            $sponsor_tooltip = get_post_meta($post_id, '_node_sponsor_tooltip', true) ?: 'この記事はスポンサー提供です。';
        } else {
            $sponsor_tooltip = 'この記事はスポンサー提供です。';
        }
        
        $sp_class = 'm3-label--sponsor m3-tooltip-target';
        $pos_attr = '';
        if ($mode === 'compact') {
            $sp_class .= ' m3-label--icon-only';
            $pos_attr = ' data-tooltip-pos="left"';
        }

        echo '<span class="' . esc_attr($sp_class) . '" data-tooltip="' . esc_attr($sponsor_tooltip) . '"' . $pos_attr . '>';
        echo '<span class="material-symbols-outlined">info</span>';
        if ($mode === 'full') {
            echo '<span class="m3-label__text">' . esc_html($sponsor_text) . '</span>';
        }
        echo '</span>';
    }
}

/* ==========================================================================
   広告エリアの制御
   ========================================================================== */
function node_the_ad_area($position) {
    // 広告コードは将来的に設定画面等から取得できるようにオプション設定を使用
    $ad_code = get_option('node_ad_code_' . $position, '');
    
    // 広告タグが設定されていない場合は非表示（出力しない）
    if (empty(trim($ad_code))) {
        return;
    }
    
    echo '<div class="m3-ad-area m3-ad-area--' . esc_attr($position) . '">' . do_shortcode($ad_code) . '</div>';
}

/* ==========================================================================
   記事の文字数と読了目安ランクの取得
   ========================================================================== */

/**
 * ブログ全体の平均文字数を取得（トランジェントでキャッシュ）
 */
function node_get_global_average_chars() {
    $cache_key = 'node_global_average_chars';
    $avg_chars = get_transient($cache_key);

    if (false === $avg_chars) {
        $post_ids = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids'
        ]);

        $total_chars = 0;
        foreach ($post_ids as $id) {
            $content = get_post_field('post_content', $id);
            $total_chars += mb_strlen(strip_tags(strip_shortcodes($content)), 'UTF-8');
        }

        if (count($post_ids) > 0) {
            $avg_chars = ceil($total_chars / count($post_ids));
        } else {
            $avg_chars = 2000;
        }
        set_transient($cache_key, $avg_chars, DAY_IN_SECONDS);
    }

    return $avg_chars;
}

/**
 * 記事の文字数と読了目安ランクを取得する
 */
function node_get_article_ranking_info($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $content = get_post_field('post_content', $post_id);
    $chars = mb_strlen(strip_tags(strip_shortcodes($content)), 'UTF-8');
    
    // ブログ全体の平均文字数を基準にする
    $avg = node_get_global_average_chars();

    // ランク判定 (全体平均 $avg に対する割合で判定)
    if ($chars < $avg * 0.4) {
        $rank = 'short';
        $label = '短い';
        $color = '#FF4081'; // Pink
        $container_color = '#FCE4EC';
    } elseif ($chars < $avg * 0.8) {
        $rank = 'somewhat_short';
        $label = 'やや短い';
        $color = '#00E5FF'; // Cyan
        $container_color = '#E0F7FA';
    } elseif ($chars < $avg * 1.2) {
        $rank = 'standard';
        $label = '標準';
        $color = '#00E676'; // Green
        $container_color = '#E8F5E9';
    } elseif ($chars < $avg * 1.6) {
        $rank = 'somewhat_long';
        $label = 'やや長い';
        $color = '#2979FF'; // Blue
        $container_color = '#E3F2FD';
    } else {
        $rank = 'long';
        $label = '長い';
        $color = '#FF9100'; // Orange
        $container_color = '#FFF3E0';
    }

    // 進行度（プログレス）の計算
    // 「長い」の基準値($avg * 1.6)を100%とし、文字数に応じて滑らかに増えるようにする
    $long_threshold = $avg * 1.6;
    $progress = min(100, round(($chars / $long_threshold) * 100));

    // 読了時間の計算 (分速800文字換算)
    $reading_time = ceil(($chars / 800));

    return [
        'rank' => $rank,
        'label' => $label,
        'progress' => $progress,
        'color' => $color,
        'container_color' => $container_color,
        'reading' => max(1, $reading_time),
        'chars' => number_format($chars)
    ];
}

/**
 * プラットフォームの分類定義を取得する
 */
function node_get_platforms_by_category() {
    return [
        'smartphone_tablet' => [
            'label' => 'スマートフォン・タブレット',
            'items' => ['iOS', 'Android'],
            'class' => 'm3-platform-chip--mobile'
        ],
        'pc' => [
            'label' => 'PC',
            'items' => ['Windows', 'Mac', 'Linux', 'Chromebook'],
            'class' => 'm3-platform-chip--pc'
        ],
        'webapp' => [
            'label' => 'Web App',
            'items' => ['Web App'],
            'class' => 'm3-platform-chip--webapp'
        ]
    ];
}

/**
 * 個別のプラットフォーム名からカテゴリ名を取得する
 */
function node_get_platform_category_name( $platform_name ) {
    $all_definitions = node_get_platforms_by_category();
    foreach ( $all_definitions as $category => $def ) {
        if ( in_array( $platform_name, $def['items'], true ) ) {
            return $def['label'];
        }
    }
    return 'その他';
}
