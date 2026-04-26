<?php
/**
 * メタボックスの管理（テーマ側）
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_add_custom_meta_boxes' ) ) {
    /**
     * 各種メタボックスの追加
     */
    function node_add_custom_meta_boxes() {
        add_meta_box('node_post_labels', '記事ラベル設定', 'node_post_labels_callback', 'post', 'side');
        add_meta_box('node_m3_color', 'Material You カラー設定', 'node_m3_color_meta_box_callback', 'post', 'side');
    }
}
add_action('add_meta_boxes', 'node_add_custom_meta_boxes');

// --- コールバック関数 ---

if ( ! function_exists( 'node_post_labels_callback' ) ) {
    function node_post_labels_callback($post) {
        $is_ai = get_post_meta($post->ID, '_node_is_ai_generated', true);
        $is_sponsor = get_post_meta($post->ID, '_node_is_sponsor', true);
        $sponsor_text = get_post_meta($post->ID, '_node_sponsor_text', true) ?: 'SPONSORED';
        $sponsor_tooltip = get_post_meta($post->ID, '_node_sponsor_tooltip', true) ?: '本記事はスポンサー提供です。';
        wp_nonce_field('node_save_meta_box', 'node_meta_box_nonce');
        echo '<p><label><input type="checkbox" name="node_is_ai_generated" value="1" '.checked($is_ai, '1', false).'> 生成されたメディアを含みます</label></p>';
        echo '<p><label><input type="checkbox" name="node_is_sponsor" value="1" '.checked($is_sponsor, '1', false).'> スポンサー記事（案件 ）</label></p>';
        echo '<p><label>スポンサーラベル文言:<br><input type="text" name="node_sponsor_text" value="'.esc_attr($sponsor_text).'" style="width:100%"></label></p>';
        echo '<p><label>スポンサー説明文 (ホバー時):<br><input type="text" name="node_sponsor_tooltip" value="'.esc_attr($sponsor_tooltip).'" style="width:100%"></label></p>';
    }
}

if ( ! function_exists( 'node_m3_color_meta_box_callback' ) ) {
    function node_m3_color_meta_box_callback($post) {
        $color = get_post_meta($post->ID, '_m3_primary_color', true);
        echo '<p><label>投稿個別カラー（Material You）:<br>';
        echo '<input type="text" name="m3_primary_color" value="' . esc_attr($color) . '" class="node-color-picker"></label></p>';
        echo '<p class="description">未設定の場合はカテゴリ設定またはアイキャッチ画像から自動生成されます。</p>';
    }
}

if ( ! function_exists( 'node_save_custom_meta' ) ) {
    /**
     * 保存処理（テーマ側）
     * 責務: 記事ラベル、スポンサー情報、個別カラー設定
     */
    function node_save_custom_meta($post_id) {
        if (!isset($_POST['node_meta_box_nonce']) || !wp_verify_nonce($_POST['node_meta_box_nonce'], 'node_save_meta_box')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // --- AI生成メディアの自動判別 ---
        $has_ai_media = false;
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id && get_post_meta($thumbnail_id, '_node_is_ai_media', true) === '1') {
            $has_ai_media = true;
        }
        if (!$has_ai_media) {
            $post_content = get_post_field('post_content', $post_id);
            preg_match_all('/wp-image-([0-9]+)/', $post_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $att_id) {
                    if (get_post_meta($att_id, '_node_is_ai_media', true) === '1') {
                        $has_ai_media = true;
                        break;
                    }
                }
            }
        }
        if ($has_ai_media) {
            $_POST['node_is_ai_generated'] = '1';
        }

        // 保存対象フィールド
        $text_fields = [
            '_node_is_ai_generated' => 'node_is_ai_generated',
            '_node_is_sponsor'      => 'node_is_sponsor',
            '_node_sponsor_text'    => 'node_sponsor_text',
            '_node_sponsor_tooltip' => 'node_sponsor_tooltip',
            '_m3_primary_color'     => 'm3_primary_color',
        ];

        foreach ($text_fields as $key => $post_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $key, sanitize_text_field($_POST[$post_key]));
            } else {
                delete_post_meta($post_id, $key);
            }
        }
    }
}
add_action('save_post', 'node_save_custom_meta');
