<?php
/**
 * Spotify ショートコード
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'node_spotify_shortcode' ) ) {
    function node_spotify_shortcode(array $atts): string {
        $atts = shortcode_atts(['url' => ''], $atts);
        if (empty($atts['url'])) return '';
        
        $src = node_extract_src_from_input($atts['url']);
        if (!str_contains($src, 'spotify.com')) return '';

        ob_start();
        ?>
        <div class="m3-embed-container m3-embed-container--spotify">
            <iframe src="<?php echo esc_url($src); ?>" width="100%" height="352" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
        </div>
        <?php
        return ob_get_clean();
    }
}
