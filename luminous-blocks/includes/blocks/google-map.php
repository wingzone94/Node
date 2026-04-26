<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
