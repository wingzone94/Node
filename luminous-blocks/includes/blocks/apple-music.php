<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
