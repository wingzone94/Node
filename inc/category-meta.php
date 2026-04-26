<?php
function node_add_category_fields($term) {
    $color = get_term_meta($term->term_id, '_m3_color', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="m3_color">カテゴリカラー</label></th>
        <td>
            <input name="m3_color" id="m3_color" type="text" value="<?php echo esc_attr($color); ?>" class="node-color-picker" data-default-color="#6750A4">
            <p class="description">このカテゴリのデフォルトMaterial Youシードカラー。</p>
        </td>
    </tr>
    <?php
}
add_action('category_edit_form_fields', 'node_add_category_fields');

function node_add_category_fields_new($taxonomy) {
    ?>
    <div class="form-field">
        <label for="m3_color">カテゴリカラー</label>
        <input name="m3_color" id="m3_color" type="text" value="" class="node-color-picker" data-default-color="#6750A4">
        <p>このカテゴリのデフォルトMaterial Youシードカラー。</p>
    </div>
    <?php
}
add_action('category_add_form_fields', 'node_add_category_fields_new');
function node_save_category_meta($term_id) {
    if (isset($_POST['m3_color'])) {
        update_term_meta($term_id, '_m3_color', sanitize_text_field($_POST['m3_color']));
    }
}
add_action('edited_category', 'node_save_category_meta');
add_action('create_category', 'node_save_category_meta');

function node_category_add_form_fields() {
    ?>
    <div class="form-field">
        <label for="m3_color">テーマカラー (Hex)</label>
        <input name="m3_color" id="m3_color" type="text" value="" class="node-color-picker" data-default-color="#FF9900">
        <p>カテゴリのベースカラーを16進数で指定します（例: #FF9900）。空欄または「auto」の場合はアイキャッチ画像から自動抽出します。</p>
    </div>
    <?php
}
add_action('category_add_form_fields', 'node_category_add_form_fields');

function node_category_edit_form_fields($term) {
    $color = get_term_meta($term->term_id, '_m3_color', true) ?: '#FF9900';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="m3_color">テーマカラー (Hex)</label></th>
        <td>
            <input name="m3_color" id="m3_color" type="text" value="<?php echo esc_attr($color); ?>" class="node-color-picker" data-default-color="#FF9900">
            <p class="description">カテゴリのベースカラー（例: #FF9900）。</p>
        </td>
    </tr>
    <?php
}
