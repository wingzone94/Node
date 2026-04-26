<?php
// ==========================================================================
// 4. メディアライブラリ: AI生成メディアのメタデータ追加
// ==========================================================================

// メディアライブラリの編集画面にAI生成チェックボックスを追加
function node_add_attachment_ai_field($form_fields, $post) {
    $is_ai = get_post_meta($post->ID, '_node_is_ai_media', true);
    $form_fields['node_is_ai_media'] = array(
        'label' => 'AI生成メディア',
        'input' => 'html',
        'html'  => '<label><input type="checkbox" name="attachments[' . $post->ID . '][node_is_ai_media]" value="1" ' . checked($is_ai, '1', false) . ' /> この画像/動画はAIによって生成された</label>',
    );
    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'node_add_attachment_ai_field', 10, 2);

// メディアライブラリでのAI生成チェックボックスの保存
function node_save_attachment_ai_field($post, $attachment) {
    if (isset($attachment['node_is_ai_media'])) {
        update_post_meta($post['ID'], '_node_is_ai_media', '1');
    } else {
        delete_post_meta($post['ID'], '_node_is_ai_media');
    }
    return $post;
}
add_filter('attachment_fields_to_save', 'node_save_attachment_ai_field', 10, 2);

// REST APIでアタッチメントのメタデータを操作可能にする
function node_register_attachment_meta() {
    register_meta('post', '_node_is_ai_media', [
        'object_subtype' => 'attachment',
        'type'           => 'boolean',
        'single'         => true,
        'show_in_rest'   => true,
    ]);
}
add_action('init', 'node_register_attachment_meta');
