<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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
