<?php
/**
 * Luminous Core v0.4 - Hegemony Blocks & Media Bridge (Material 3)
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. Smart Sort Table Block
 */
register_block_type('node/sort-table', [
    'render_callback' => 'node_render_sort_table_block',
    'attributes' => [
        'enable_sort' => ['type' => 'boolean', 'default' => true],
        'headers' => ['type' => 'array', 'default' => ['項目', '値', '備考']],
        'rows' => ['type' => 'array', 'default' => [['Apple', '100', 'Red'], ['Banana', '50', 'Yellow']]]
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
 * 4. Media Bridge (oEmbed Refined for v0.4)
 */
function node_m3_oembed_bridge_v4($html, $url, $attr, $post_ID) {
    // M3 Standard: 16px radius, Elevated
    $style = 'border-radius:16px; overflow:hidden; box-shadow:var(--m3-elevation-2);';
    
    if (preg_match('/(apple\.com|spotify\.com|steampowered\.com|nicovideo\.jp)/', $url)) {
        return '<div class="m3-block-container"><div class="m3-embed-container" style="' . $style . '">' . $html . '</div></div>';
    }
    return $html;
}
add_filter('embed_oembed_html', 'node_m3_oembed_bridge_v4', 20, 4);
