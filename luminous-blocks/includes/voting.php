<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
