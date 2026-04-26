<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
