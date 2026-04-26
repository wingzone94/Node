<?php
/**
 * CERO Z (年齢制限) メタボックス
 *
 * @package Luminous_Interactivity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_cero_z_meta_box_callback' ) ) {
    /**
     * メタボックスのレンダリング
     */
    function node_cero_z_meta_box_callback($post) {
        $value = get_post_meta($post->ID, '_node_is_cero_z', true);
        // 保存用の Nonce
        wp_nonce_field('luminous_interactivity_save_cero_z_action', 'luminous_interactivity_cero_z_nonce');
        echo '<label><input type="checkbox" name="node_is_cero_z" value="1" '.checked($value, '1', false).'> CERO Z (18歳以上) を適用</label>';
    }
}

if ( ! function_exists( 'luminous_interactivity_save_cero_z_meta' ) ) {
    /**
     * CERO Z 設定の保存処理
     */
    function luminous_interactivity_save_cero_z_meta($post_id) {
        // Nonce チェック
        if (!isset($_POST['luminous_interactivity_cero_z_nonce']) || !wp_verify_nonce($_POST['luminous_interactivity_cero_z_nonce'], 'luminous_interactivity_save_cero_z_action')) {
            return;
        }

        // 自動保存時はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['node_is_cero_z'])) {
            update_post_meta($post_id, '_node_is_cero_z', '1');
        } else {
            delete_post_meta($post_id, '_node_is_cero_z');
        }
    }
}
