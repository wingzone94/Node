<?php
/**
 * Luminous Core - Hegemony Blocks & Media Bridge (Material 3)
 * v0.5.0: 各サービス公式埋め込み仕様準拠・利用規約対応リライト
 *
 * 【設計方針】
 * すべての埋め込みショートコードは、各サービスが提供する公式の「埋め込みコード」から
 * src 属性のURLをコピーして `url` パラメータに貼り付ける方式を採用しています。
 * iPhone属性やiframe属性はサービス公式ドキュメントに従い、改変していません。
 */

if (!defined('ABSPATH')) exit;

/* ==========================================================================
   共通ユーティリティ: 許可ドメイン検証
   ========================================================================== */

/**
 * 指定されたURLが許可ドメインに属するか検証する
 */
function node_validate_embed_url(string $url, array $allowed): bool {
    $url = trim($url);
    if (empty($url)) return false;

    // iframeタグが丸ごと貼り付けられた場合に src を抽出する試み
    if (str_starts_with($url, '<iframe') || str_contains($url, ' src=')) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $url, $match)) {
            $url = $match[1];
        }
    }

    // プロトコルがない場合は補完 (wp_parse_url対策)
    if (!str_contains($url, '://') && !str_starts_with($url, '//')) {
        $url = 'https://' . $url;
    }

    $parsed = wp_parse_url($url); 
    if (empty($parsed['host'])) return false;

    $host = strtolower($parsed['host']);

    foreach ($allowed as $allowed_host) {
        $allowed_host = strtolower($allowed_host);
        if ($host === $allowed_host || str_ends_with($host, '.' . $allowed_host)) {
            return true;
        }
    }
    return false;
}

/**
 * 入力から src URL を安全に抽出する
 */
function node_extract_src_from_input($input) {
    $input = trim($input);
    if (empty($input)) return '';
    
    // タグ形式なら中身を抽出
    if (str_starts_with($input, '<iframe') || str_contains($input, ' src=')) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $input, $match)) {
            return $match[1];
        }
    }
    
    // プロトコル補完
    if (!str_contains($input, '://') && !str_starts_with($input, '//')) {
        return 'https://' . $input;
    }
    
    return $input;
}

/* ==========================================================================
   Gutenberg ブロック登録
   ========================================================================== */

function node_register_m3_blocks() {
    // 1. Smart Sort Table
    register_block_type('node/sort-table', [
        'render_callback' => 'node_render_sort_table_block',
        'attributes' => [
            'enable_sort' => ['type' => 'boolean', 'default' => true],
            'headers'     => ['type' => 'array', 'default' => ['項目', '値', '備考']],
            'rows'        => ['type' => 'array', 'default' => [['Apple', '100', 'Red'], ['Banana', '50', 'Yellow']]]
        ]
    ]);

    // 2. Apple Music
    register_block_type('node/apple-music', [
        'render_callback' => function($attr) { return node_apple_music_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'url'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '175']
        ]
    ]);

    // 3. Google Map
    register_block_type('node/google-map', [
        'render_callback' => function($attr) { return node_google_map_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'src'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '450']
        ]
    ]);

    // 4. Spotify
    register_block_type('node/spotify', [
        'render_callback' => function($attr) { return node_spotify_shortcode($attr); },
        'supports' => ['align' => true],
        'attributes' => [
            'url'    => ['type' => 'string', 'default' => ''],
            'height' => ['type' => 'string', 'default' => '352']
        ]
    ]);

    // 5. Product Card
    register_block_type('node/product-card', [
        'render_callback' => function($attr) { return node_product_card_shortcode($attr); },
        'attributes' => [
            'title'       => ['type' => 'string', 'default' => ''],
            'price'       => ['type' => 'string', 'default' => ''],
            'image_url'   => ['type' => 'string', 'default' => ''],
            'amazon_url'  => ['type' => 'string', 'default' => ''],
            'rakuten_url' => ['type' => 'string', 'default' => '']
        ]
    ]);
}
add_action('init', 'node_register_m3_blocks');

/* ==========================================================================
   ブロックレンダリング
   ========================================================================== */

function node_render_sort_table_block($attributes) {
    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-sort-table-wrapper">
            <table class="m3-sort-table" data-sort-enabled="<?php echo $attributes['enable_sort'] ? 'true' : 'false'; ?>">
                <thead><tr>
                    <?php foreach ($attributes['headers'] as $header): ?>
                        <th><?php echo esc_html($header); ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($attributes['rows'] as $row): ?>
                        <tr><?php foreach ($row as $cell): ?>
                            <td><?php echo esc_html($cell); ?></td>
                        <?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ==========================================================================
   2. Media Label Wrapper
   ========================================================================== */
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

/* ==========================================================================
   3. Voting Block
   ========================================================================== */
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

/* ==========================================================================
   4. oEmbed パススルー
   各サービスの埋め込みHTMLは改変せずそのまま出力します（利用規約準拠）。
   WordPressデフォルトの oEmbed 出力をそのまま使用します。
 */
// oEmbed ラッパーは適用しない（各サービス利用規約準拠のため）
// add_filter('embed_oembed_html', 'node_m3_oembed_bridge_v4', 20, 4);

/* ==========================================================================
   5. Apple Music 埋め込みショートコード
   利用規約: https://www.apple.com/jp/legal/internet-services/itunes/jp/terms.html

   【使い方】
   Apple Music でコンテンツを開き「共有」>「埋め込み」から表示される
   iframeコードの src 属性の値をそのまま url に貼り付けてください。

   例:
   [apple_music url="https://embed.music.apple.com/jp/album/xxxx/yyyy"]
   [apple_music url="https://embed.music.apple.com/jp/album/xxxx/yyyy" height="450"]

   ※ Apple が提供する公式の iframe 属性を そのまま使用しています。
   ========================================================================== */
function node_apple_music_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'url'    => '',
        'height' => '175',
    ], $atts, 'apple_music');

    $url = node_extract_src_from_input($atts['url']);

    if (empty($url)) return '';

    // 通常の共有URL(music.apple.com)が貼られた場合、埋め込み用URL(embed.music.apple.com)に自動変換する
    if (str_contains($url, 'music.apple.com') && !str_contains($url, 'embed.music.apple.com')) {
        $url = str_replace('music.apple.com', 'embed.music.apple.com', $url);
    }

    // embed.music.apple.com のみ許可
    if (!node_validate_embed_url($url, ['embed.music.apple.com', 'music.apple.com'])) {
        return '<!-- Invalid Apple Music URL -->';
    }

    $height = absint($atts['height']);
    if ($height < 52 || $height > 600) {
        $height = 175;
    }

    // ★ Apple 公式埋め込み仕様に準拠した iframe 属性
    // 参考: Apple Music Embeds (https://music.apple.com の「埋め込み」機能)
    ob_start();
    ?>
    <div class="m3-block-container">
        <iframe
            allow="autoplay *; encrypted-media *; fullscreen *; clipboard-write"
            frameborder="0"
            height="<?php echo esc_attr($height); ?>"
            style="width:100%;max-width:100%;overflow:hidden;border-radius:12px;display:block;"
            sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation"
            src="<?php echo esc_url($url); ?>"
            title="Apple Music"
        ></iframe>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('apple_music', 'node_apple_music_shortcode');

/* ==========================================================================
   6. Google Maps 埋め込みショートコード
   利用規約: https://cloud.google.com/maps-platform/terms

   【使い方】
   Google マップでコンテンツを開き「共有」>「地図を埋め込む」から表示される
   iframeコードの src 属性の値をそのまま src に貼り付けてください。

   例:
   [google_map src="https://www.google.com/maps/embed?pb=xxxx"]
   [google_map src="https://www.google.com/maps/embed?pb=xxxx" height="450"]

   ※ Google が提供する公式の iframe 属性を そのまま使用しています。
   ========================================================================== */
function node_google_map_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'src'    => '',
        'height' => '450',
    ], $atts, 'google_map');

    $src = node_extract_src_from_input($atts['src']);

    if (empty($src)) return '';

    // バリデーション (日本・グローバルのマップドメインを許可)
    if (!node_validate_embed_url($src, ['google.com', 'google.co.jp', 'maps.google.com', 'maps.google.co.jp'])) {
        return '<!-- Invalid Google Maps URL -->';
    }

    // パスチェック (埋め込み用 URL であることを確認)
    if (!str_contains($src, '/maps/') || (!str_contains($src, 'embed') && !str_contains($src, 'pb='))) {
        return '<!-- Google Maps URL must be an embed URL (src from iframe) -->';
    }

    $height = absint($atts['height']);
    if ($height < 100 || $height > 800) {
        $height = 450;
    }

    // ★ Google Maps 公式埋め込み仕様に準拠した iframe 属性
    // 参考: https://developers.google.com/maps/documentation/embed/get-started
    ob_start();
    ?>
    <div class="m3-block-container">
        <div style="height:<?php echo esc_attr($height); ?>px; position:relative; overflow:hidden; border-radius:12px;">
            <iframe
                src="<?php echo esc_url($src); ?>"
                width="100%"
                height="100%"
                style="border:0; position:absolute; inset:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Google マップ"
            ></iframe>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('google_map', 'node_google_map_shortcode');

/* ==========================================================================
   7. Spotify 埋め込みショートコード
   利用規約: https://developer.spotify.com/documentation/embeds/terms

   【使い方】
   Spotify でコンテンツを右クリック（長押し）>「シェア」>「埋め込む」から
   表示されるiframeコードの src 属性の値をそのまま url に貼り付けてください。

   例:
   [spotify url="https://open.spotify.com/embed/track/xxxx?utm_source=generator"]
   [spotify url="https://open.spotify.com/embed/album/xxxx?utm_source=generator" height="352"]

   ※ Spotify が提供する公式の iframe 属性を そのまま使用しています。
   ========================================================================== */
function node_spotify_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'url'    => '',
        'height' => '352',
    ], $atts, 'spotify');

    $url = node_extract_src_from_input($atts['url']);

    if (empty($url)) return '';

    // open.spotify.com のみ許可
    if (!node_validate_embed_url($url, ['open.spotify.com'])) {
        return '<!-- Invalid Spotify URL -->';
    }

    $parsed = wp_parse_url($url);
    // 通常の共有URL ( /track/xxx 等 ) が貼られた場合、 /embed/track/xxx に自動変換する
    if (!str_starts_with($parsed['path'] ?? '', '/embed')) {
        $url = str_replace('open.spotify.com/', 'open.spotify.com/embed/', $url);
    }

    $height = absint($atts['height']);
    // Spotify 公式: compact=152, standard=352
    if ($height < 152 || $height > 352) {
        $height = 352;
    }

    // ★ Spotify 公式埋め込み仕様に準拠した iframe 属性
    // 参考: https://developer.spotify.com/documentation/embeds/tutorials/creating-an-embed
    ob_start();
    ?>
    <div class="m3-block-container">
        <iframe
            style="border-radius:12px; display:block;"
            src="<?php echo esc_url($url); ?>"
            width="100%"
            height="<?php echo esc_attr($height); ?>"
            frameBorder="0"
            allowfullscreen=""
            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
            loading="lazy"
            title="Spotify"
        ></iframe>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('spotify', 'node_spotify_shortcode');

/* ==========================================================================
   8. 商品リンクカード（Amazon・楽天 同時表示）
   既存の [m3_product] ショートコードの拡張版として [product_card] を提供
   functions.php の node_m3_product_shortcode() も引き続き使用可能

   【使い方】
   [product_card
     title="商品名"
     price="¥3,980"
     image_url="https://example.com/image.jpg"
     amazon_url="https://www.amazon.co.jp/dp/xxxx"
     rakuten_url="https://item.rakuten.co.jp/xxxx"
   ]

   ※ Amazon・楽天どちらか一方のみでも機能します。
   ========================================================================== */
function node_product_card_shortcode(array $atts): string {
    $atts = shortcode_atts([
        'title'       => '',
        'price'       => '',
        'image_url'   => '',
        'amazon_url'  => '',
        'rakuten_url' => '',
    ], $atts, 'product_card');

    // 少なくとも1つのリンクが必要
    $has_amazon  = !empty($atts['amazon_url']);
    $has_rakuten = !empty($atts['rakuten_url']);

    if (!$has_amazon && !$has_rakuten) return '';

    // 各ストアURLのドメイン検証
    $allowed_amazon  = ['amazon.co.jp', 'amazon.com', 'amazon.jp', 'amzn.to', 'amzn.asia', 'amzn.jp', 'a.co', 'amazon-adsystem.com'];
    $allowed_rakuten = ['rakuten.co.jp', 'rakuten.ne.jp', 'a.r10.to', 'hb.afl.rakuten.co.jp', 'rakuten.co.jp'];

    if ($has_amazon  && !node_validate_embed_url($atts['amazon_url'],  $allowed_amazon))  $has_amazon  = false;
    if ($has_rakuten && !node_validate_embed_url($atts['rakuten_url'], $allowed_rakuten)) $has_rakuten = false;

    if (!$has_amazon && !$has_rakuten) {
        return '<!-- Product Card: No valid Store URLs found among ' . esc_html($atts['amazon_url'] . ' ' . $atts['rakuten_url']) . ' -->';
    }

    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-product-card">
            <?php if (!empty($atts['image_url'])) : ?>
            <div class="m3-product-card__image">
                <img src="<?php echo esc_url($atts['image_url']); ?>"
                     alt="<?php echo esc_attr($atts['title']); ?>"
                     loading="lazy"
                     width="120"
                     height="120">
            </div>
            <?php endif; ?>

            <div class="m3-product-card__body">
                <?php if (!empty($atts['title'])) : ?>
                <h4 class="m3-product-card__title"><?php echo esc_html($atts['title']); ?></h4>
                <?php endif; ?>

                <?php if (!empty($atts['price'])) : ?>
                <p class="m3-product-card__price"><?php echo esc_html($atts['price']); ?></p>
                <?php endif; ?>

                <div class="m3-product-card__buttons">
                    <?php if ($has_amazon) : ?>
                    <a href="<?php echo esc_url($atts['amazon_url']); ?>"
                       class="m3-product-btn m3-product-btn--amazon"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                            <path d="M.045 18.02c.072-.116.187-.124.348-.022 3.636 2.11 7.594 3.166 11.87 3.166 2.852 0 5.668-.533 8.447-1.595.577-.23.877-.053.9.232.024.187-.057.377-.24.57-2.414 2.667-5.33 4-8.747 4-3.65 0-6.903-1.15-9.76-3.45-.286-.23-.336-.523-.155-.9zM12.022 2.9c1.08 0 2.264.277 3.547.832.47.204.594.454.374.748-.19.25-.44.34-.748.27-1.33-.31-2.405-.467-3.22-.467-1.063 0-2.122.22-3.177.658-.486.207-.783.133-.893-.222-.11-.355.057-.61.5-.763C9.534 3.155 10.71 2.9 12.022 2.9zm2.627 2.06c.48.078.97.24 1.47.488.5.25.617.6.35 1.05-.234.39-.534.5-.9.33-.49-.23-.863-.38-1.12-.45-.26-.07-.47-.1-.635-.1-.48 0-.737.15-.77.45-.032.247.112.455.43.622.318.168.83.347 1.535.54l.433.127c.622.183 1.083.41 1.384.68.3.27.45.66.45 1.17 0 .618-.205 1.1-.615 1.445-.41.345-.95.517-1.622.517-.39 0-.817-.065-1.284-.194s-.872-.304-1.216-.523c-.486-.304-.58-.66-.28-1.07.222-.314.504-.41.848-.29.42.146.76.265 1.02.36.26.093.52.14.775.14.307 0 .54-.054.697-.163.157-.11.23-.268.22-.475-.01-.187-.11-.34-.3-.458-.19-.12-.537-.256-1.04-.41-.38-.114-.742-.237-1.087-.37-.345-.133-.64-.31-.885-.527-.245-.218-.43-.48-.557-.784-.127-.305-.19-.66-.19-1.07 0-.58.19-1.055.57-1.424.38-.37.9-.554 1.558-.554zM12.022 1c-5.523 0-10 4.477-10 10s4.477 10 10 10 10-4.477 10-10-4.477-10-10-10z"/>
                        </svg>
                        Amazonで見る
                    </a>
                    <?php endif; ?>

                    <?php if ($has_rakuten) : ?>
                    <a href="<?php echo esc_url($atts['rakuten_url']); ?>"
                       class="m3-product-btn m3-product-btn--rakuten"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm4.95 16.5h-2.193l-2.43-3.24H10.5V16.5H8.55V7.5h4.08c1.98 0 3.24 1.017 3.24 2.88 0 1.395-.765 2.34-1.98 2.745L16.95 16.5zm-4.65-4.83c.855 0 1.35-.45 1.35-1.215 0-.78-.495-1.23-1.35-1.23H10.5v2.445h1.8z"/>
                        </svg>
                        楽天市場で見る
                    </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('product_card', 'node_product_card_shortcode');

/* ==========================================================================
   oEmbed ハンドラー (URL直貼りで自動的にM3ブロック化)
   ========================================================================== */

function node_register_oembed_handlers() {
    // 1. Apple Music (music.apple.com または embed.music.apple.com)
    wp_embed_register_handler(
        'node_apple_music',
        '#^https?://(embed\.)?music\.apple\.com/.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_apple_music_shortcode(['url' => $url]);
        }
    );

    // 2. Spotify (open.spotify.com)
    wp_embed_register_handler(
        'node_spotify',
        '#^https?://open\.spotify\.com/.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_spotify_shortcode(['url' => $url]);
        }
    );

    // 3. Google Maps (/maps/embed パスが含まれる場合のみ)
    wp_embed_register_handler(
        'node_google_map',
        '#^https?://(www\.)?google\.(com|co\.jp)/maps/embed.*#i',
        function($matches, $attr, $url, $rawattr) {
            return node_google_map_shortcode(['src' => $url]);
        }
    );
}
add_action('init', 'node_register_oembed_handlers');
