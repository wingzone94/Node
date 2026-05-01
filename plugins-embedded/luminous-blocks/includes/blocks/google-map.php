<?php
/**
 * Google Map ショートコード
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'node_google_map_shortcode' ) ) {
    function node_google_map_shortcode(array $atts): string {
        $atts = shortcode_atts(['url' => ''], $atts);
        if (empty($atts['url'])) return '';
        
        $src = node_extract_src_from_input($atts['url']);
        if (!str_contains($src, 'google.com/maps')) return '';

        ob_start();
        ?>
        <div class="m3-embed-container m3-embed-container--google-map">
            <iframe src="<?php echo esc_url($src); ?>" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <?php
        return ob_get_clean();
    }
}
