<?php
/**
 * Apple Music ショートコード
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'node_apple_music_shortcode' ) ) {
    function node_apple_music_shortcode(array $atts): string {
        $atts = shortcode_atts(['url' => ''], $atts);
        if (empty($atts['url'])) return '';
        
        $src = node_extract_src_from_input($atts['url']);
        if (!str_contains($src, 'music.apple.com')) return '';

        ob_start();
        ?>
        <div class="m3-embed-container m3-embed-container--apple-music">
            <iframe allow="autoplay *; encrypted-media *; fullscreen *; clipboard-write" frameborder="0" height="450" style="width:100%;max-width:660px;overflow:hidden;border-radius:10px;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="<?php echo esc_url($src); ?>"></iframe>
        </div>
        <?php
        return ob_get_clean();
    }
}
