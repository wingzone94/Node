<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
