<?php
/**
 * Node Theme Functions
 */

// CERO Z メタボックスの追加
function node_add_cero_z_meta_box() {
    add_meta_box(
        'node_content_rating',
        'コンテンツ評価設定',
        'node_cero_z_meta_box_callback',
        'post',
        'side'
    );
}
add_action('add_meta_boxes', 'node_add_cero_z_meta_box');

function node_cero_z_meta_box_callback($post) {
    $value = get_post_meta($post->ID, '_node_is_cero_z', true);
    wp_nonce_field('node_save_cero_z_meta_box', 'node_cero_z_meta_box_nonce');
    ?>
    <label>
        <input type="checkbox" name="node_is_cero_z" value="1" <?php checked($value, '1'); ?>>
        CERO Z (18歳以上のみ閲覧可) を適用する
    </label>
    <?php
}

function node_save_cero_z_meta_box($post_id) {
    // セキュリティチェック
    if (!isset($_POST['node_cero_z_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['node_cero_z_meta_box_nonce'], 'node_save_cero_z_meta_box')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // データの保存
    if (isset($_POST['node_is_cero_z'])) {
        update_post_meta($post_id, '_node_is_cero_z', '1');
    } else {
        delete_post_meta($post_id, '_node_is_cero_z');
    }
}
add_action('save_post', 'node_save_cero_z_meta_box');

// 警告ダイアログのHTML出力
function node_render_cero_z_dialog() {
    if (is_single() && get_post_meta(get_the_ID(), '_node_is_cero_z', true) === '1') {
        ?>
        <dialog id="cero-z-dialog" class="node-dialog">
            <div class="node-dialog__content">
                <h2>年齢制限の確認</h2>
                <p>この記事には過激な表現が含まれる可能性があるため、18歳以上の方のみ閲覧可能です。</p>
                <div class="node-dialog__actions">
                    <button id="cero-z-decline" class="m3-button m3-button--text">戻る</button>
                    <button id="cero-z-accept" class="m3-button m3-button--filled">閲覧する</button>
                </div>
            </div>
        </dialog>
        <?php
    }
}
add_action('wp_footer', 'node_render_cero_z_dialog');

function node_enqueue_assets() {
    wp_enqueue_style('node-features-style', get_template_directory_uri() . '/features.css');
    wp_enqueue_script('node-features-js', get_template_directory_uri() . '/features.js', array(), '1.0', true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');