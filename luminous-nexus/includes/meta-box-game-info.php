<?php
/**
 * ゲーム・アプリ情報メタボックス
 *
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_game_info_callback' ) ) {
    /**
     * メタボックスのレンダリング
     */
    function node_game_info_callback($post) {
        $info = get_post_meta($post->ID, '_node_game_info', true);
        if (!is_array($info)) {
            $info = ['title' => '', 'summary' => '', 'links' => []];
        }
        // 保存用の Nonce
        wp_nonce_field('luminous_nexus_save_game_action', 'luminous_nexus_game_nonce');
        ?>
        <p><label>タイトル: <input type="text" name="node_game_title" value="<?php echo esc_attr($info['title']); ?>" style="width:100%"></label></p>
        <p><label>要約: <textarea name="node_game_summary" style="width:100%"><?php echo esc_textarea($info['summary']); ?></textarea></label></p>
        <p><label>リンク (JSON形式):<br>
        <textarea name="node_game_links" style="width:100%; height: 100px; font-family: monospace;"><?php echo esc_textarea(json_encode($info['links'])); ?></textarea></label></p>
        <?php
    }
}

if ( ! function_exists( 'luminous_nexus_save_meta' ) ) {
    /**
     * ゲーム情報の保存処理
     */
    function luminous_nexus_save_meta($post_id) {
        // Nonce チェック
        if (!isset($_POST['luminous_nexus_game_nonce']) || !wp_verify_nonce($_POST['luminous_nexus_game_nonce'], 'luminous_nexus_save_game_action')) {
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

        if (isset($_POST['node_game_title'])) {
            $info = [
                'title'   => sanitize_text_field($_POST['node_game_title']),
                'summary' => sanitize_textarea_field($_POST['node_game_summary']),
                'links'   => json_decode(stripslashes($_POST['node_game_links']), true) ?: []
            ];
            update_post_meta($post_id, '_node_game_info', $info);
        }
    }
}
