<?php
/**
 * Luminous Core - Hegemony Blocks & Media Bridge (Material 3)
 * v0.4.1: Apple Music / Google Maps 埋め込みブロック追加
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. Smart Sort Table Block
 */
register_block_type('node/sort-table', [
    'render_callback' => 'node_render_sort_table_block',
    'attributes' => [
        'enable_sort' => ['type' => 'boolean', 'default' => true],
        'headers'     => ['type' => 'array', 'default' => ['項目', '値', '備考']],
        'rows'        => ['type' => 'array', 'default' => [['Apple', '100', 'Red'], ['Banana', '50', 'Yellow']]]
    ]
]);

function node_render_sort_table_block($attributes) {
    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-sort-table-wrapper">
            <table class="m3-sort-table" data-sort-enabled="<?php echo $attributes['enable_sort'] ? 'true' : 'false'; ?>">
                <thead>
                    <tr>
                        <?php foreach ($attributes['headers'] as $header): ?>
                            <th><?php echo esc_html($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attributes['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo esc_html($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 2. Media Label Wrapper (for Images)
 */
function node_render_media_label($image_url, $label_text) {
    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-media-container">
            <input type="checkbox" class="m3-media-checkbox">
            <img src="<?php echo esc_url($image_url); ?>" alt="" style="width:100%; display:block;">
            <div class="m3-media-label"><?php echo esc_html($label_text); ?></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 3. Voting Block (Refined Results)
 */
function node_render_voting_refined($attributes) {
    $id = !empty($attributes['id']) ? $attributes['id'] : md5(serialize($attributes));
    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-voting-card" data-voted-id="<?php echo esc_attr($id); ?>">
            <canvas class="m3-voting-canvas"></canvas>
            <h3 class="m3-voting-title"><?php echo esc_html($attributes['question']); ?></h3>
            <div class="m3-voting-options">
                <?php foreach ($attributes['options'] as $index => $option): ?>
                    <button class="m3-voting-button" data-option="<?php echo esc_attr($index); ?>">
                        <?php echo esc_html($option['label']); ?>
                    </button>
                    <div class="m3-voting-result-container" style="display:none;">
                        <span><?php echo esc_html($option['label']); ?></span>
                        <span><?php echo esc_html($option['percent']); ?>% (<?php echo esc_html($option['count']); ?>票)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 4. Media Bridge (oEmbed - Apple / Spotify / Steam / Nico / Google Maps)
 * Google Mapsを追加し、M3スタイルラッパーを適用
 */
function node_m3_oembed_bridge_v4($html, $url, $attr, $post_ID) {
    $style = 'border-radius:16px; overflow:hidden; box-shadow:var(--m3-elevation-2);';

    if (preg_match('/(apple\.com|spotify\.com|steampowered\.com|nicovideo\.jp|google\.com\/maps)/', $url)) {
        return '<div class="m3-block-container"><div class="m3-embed-container" style="' . $style . '">' . $html . '</div></div>';
    }
    return $html;
}
add_filter('embed_oembed_html', 'node_m3_oembed_bridge_v4', 20, 4);

/* ==========================================================================
   5. Apple Music 埋め込みショートコード
   使い方: [apple_music url="https://music.apple.com/jp/album/..."]
           [apple_music url="https://music.apple.com/jp/album/..." height="450" theme="dark"]
   ========================================================================== */

/**
 * Apple Music 共有URLをembed URLに変換してiframeを出力する
 *
 * @param array $atts {
 *   @type string $url    Apple Musicの共有URL (必須)
 *   @type int    $height iframeの高さ px。52〜600の範囲 (デフォルト: 175)
 *   @type string $theme  'auto'|'light'|'dark' (デフォルト: auto)
 * }
 */
function node_apple_music_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'url'    => '',
        'height' => '175',
        'theme'  => 'auto',
    ], $atts, 'apple_music');

    if (empty($atts['url'])) return '';

    $url = esc_url_raw($atts['url']);

    // 共有URL → embed URL 変換
    if (!str_contains($url, 'embed.music.apple.com')) {
        $url = str_replace('https://music.apple.com', 'https://embed.music.apple.com', $url);
    }

    // Apple Music ドメインのみ許可（セキュリティ）
    $parsed = wp_parse_url($url);
    if (empty($parsed['host']) || !str_contains($parsed['host'], 'embed.music.apple.com')) {
        return '';
    }

    $height = absint($atts['height']);
    if ($height < 52 || $height > 600) {
        $height = 175;
    }

    $allowed_themes = ['auto', 'light', 'dark'];
    $theme = in_array($atts['theme'], $allowed_themes, true) ? $atts['theme'] : 'auto';

    $embed_url = add_query_arg(['app' => 'music', 'theme' => $theme], $url);

    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-embed-apple-music">
            <div class="m3-embed-service-header">
                <svg class="m3-embed-service-header__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="18" height="18">
                    <path d="M23.994 6.124a9.23 9.23 0 0 0-.24-2.19c-.317-1.31-1.062-2.31-2.18-3.043a5.022 5.022 0 0 0-1.877-.726 10.496 10.496 0 0 0-1.564-.15c-.04-.003-.083-.01-.124-.013H5.986c-.152.01-.303.017-.455.026C4.786.07 4.043.15 3.34.428 2.067.948 1.158 1.84.58 3.113A5.15 5.15 0 0 0 .04 4.985c-.01.15-.014.302-.018.454V18.56c.005.158.012.316.025.472.08.891.32 1.724.81 2.477.69 1.072 1.635 1.77 2.866 2.1.6.163 1.216.23 1.843.24.192.005.384.008.577.008h12.71c.191 0 .383-.003.575-.008.63-.01 1.246-.077 1.843-.24 1.231-.33 2.176-1.028 2.866-2.1.49-.753.73-1.586.81-2.477.013-.156.02-.314.025-.472V6.578c-.004-.152-.009-.305-.018-.454zM12 17.404c-2.983 0-5.404-2.421-5.404-5.404S9.017 6.596 12 6.596s5.404 2.421 5.404 5.404S14.983 17.404 12 17.404zm0-8.808c-1.878 0-3.404 1.526-3.404 3.404S10.122 15.404 12 15.404s3.404-1.526 3.404-3.404S13.878 8.596 12 8.596zM17.596 5.5a1.404 1.404 0 1 1 0 2.808 1.404 1.404 0 0 1 0-2.808z"/>
                </svg>
                <span class="m3-embed-service-header__label">Apple Music</span>
            </div>
            <iframe
                id="apple-music-<?php echo esc_attr(md5($url)); ?>"
                allow="autoplay *; encrypted-media *; fullscreen *; clipboard-write"
                frameborder="0"
                height="<?php echo esc_attr($height); ?>"
                style="width:100%; max-width:100%; display:block;"
                sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation"
                src="<?php echo esc_url($embed_url); ?>"
                loading="lazy"
                title="Apple Music プレーヤー"
            ></iframe>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('apple_music', 'node_apple_music_shortcode');

/* ==========================================================================
   6. Google Maps 埋め込みショートコード
   使い方: [google_map url="https://maps.google.com/maps?q=東京タワー"]
           [google_map q="東京タワー" height="400" zoom="15"]
   ========================================================================== */

/**
 * Google Maps 埋め込みショートコード
 * URLまたは検索クエリからiframeを生成する
 * セキュリティ: Googleドメイン以外のURLは出力しない
 *
 * @param array $atts {
 *   @type string $url    Google Mapsの共有URL
 *   @type string $q      検索クエリ（url未設定時に使用）
 *   @type int    $height iframeの高さ px。100〜800の範囲 (デフォルト: 400)
 *   @type int    $zoom   ズームレベル 1〜20 (デフォルト: 15)
 * }
 */
function node_google_map_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'url'    => '',
        'q'      => '',
        'height' => '400',
        'zoom'   => '15',
    ], $atts, 'google_map');

    $height = absint($atts['height']);
    if ($height < 100 || $height > 800) {
        $height = 400;
    }
    $zoom = min(20, max(1, absint($atts['zoom'])));

    // embed URLを生成
    if (!empty($atts['url'])) {
        $share_url = esc_url_raw($atts['url']);
        $parsed    = wp_parse_url($share_url);

        // Googleドメインのみ許可
        $allowed_hosts = ['maps.google.com', 'www.google.com', 'goo.gl', 'maps.app.goo.gl'];
        if (empty($parsed['host']) || !in_array($parsed['host'], $allowed_hosts, true)) {
            return '<p style="color:red;">[google_map] 許可されていないURLです。</p>';
        }

        // すでにembedパスならそのまま使用
        if (str_contains($share_url, '/maps/embed')) {
            $embed_url = $share_url;
        } else {
            // 共有URLの ?q= パラメータを取得
            parse_str($parsed['query'] ?? '', $query_params);
            $location  = $query_params['q'] ?? '';
            $embed_url = 'https://maps.google.com/maps/embed?q=' . rawurlencode($location) . '&zoom=' . $zoom;
        }
    } elseif (!empty($atts['q'])) {
        $embed_url = 'https://maps.google.com/maps/embed?q=' . rawurlencode(sanitize_text_field($atts['q'])) . '&zoom=' . $zoom;
    } else {
        return '';
    }

    $maps_link = 'https://maps.google.com/maps?q=' . rawurlencode($atts['q'] ?: '');

    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-embed-google-map">
            <div class="m3-embed-service-header">
                <svg class="m3-embed-service-header__icon m3-embed-service-header__icon--maps" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="18" height="18">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
                <span class="m3-embed-service-header__label">Google マップ</span>
            </div>
            <div class="m3-embed-google-map__frame-wrapper" style="height:<?php echo esc_attr($height); ?>px;">
                <iframe
                    src="<?php echo esc_url($embed_url); ?>"
                    width="100%"
                    height="100%"
                    frameborder="0"
                    style="border:0; display:block;"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                    loading="lazy"
                    title="Google マップ"
                ></iframe>
            </div>
            <div class="m3-embed-google-map__footer">
                <a href="<?php echo esc_url($maps_link); ?>" target="_blank" rel="noopener noreferrer" class="m3-embed-map-link">
                    <span class="material-symbols-outlined" aria-hidden="true">open_in_new</span>
                    Google マップで開く
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('google_map', 'node_google_map_shortcode');
