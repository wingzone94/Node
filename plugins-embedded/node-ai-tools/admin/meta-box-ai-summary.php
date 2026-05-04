<?php
/**
 * AI 要約メタボックス UI（スタブ）
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function node_ai_render_summary_meta_box($post) {
    $summary       = get_post_meta($post->ID, '_node_ai_summary', true);
    $custom_prompt = get_post_meta($post->ID, '_node_ai_custom_prompt', true);
    wp_nonce_field('node_ai_generate_action', 'node_ai_generate_nonce'); // AJAX用
    wp_nonce_field('node_ai_save_meta_action', 'node_ai_save_meta_nonce'); // 記事保存用
    
    echo '<p><strong>AIへの追加指示 (プロンプト):</strong></p>';
    echo '<input type="text" id="node_ai_custom_prompt" name="node_ai_custom_prompt" style="width:100%; margin-bottom:10px;" placeholder="例: もっと短くして、専門用語を避けて..." value="'.esc_attr($custom_prompt).'" />';
    
    echo '<p><strong>記事の要約 (100文字程度):</strong></p>';
    echo '<textarea id="node_ai_summary_textarea" name="node_ai_summary" style="width:100%; height:80px;" placeholder="記事の要約を入力してください...">'.esc_textarea($summary).'</textarea>';
    
    echo '<p class="description" style="margin-bottom:15px;">Luminous Settings で設定されたモデルを利用して全自動生成・手動修正が可能です。</p>';
    echo '<p><button type="button" id="node_generate_ai_btn" class="button button-secondary" data-post-id="'.esc_attr($post->ID).'">AIで要約を生成</button>';
    echo '<span id="node_ai_generate_status" style="margin-left: 10px; font-weight: bold;"></span></p>';
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#node_generate_ai_btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var status = $('#node_ai_generate_status');
            var postId = btn.data('post-id');
            var nonce = $('#node_ai_generate_nonce').val();
            var customPrompt = $('#node_ai_custom_prompt').val();
            
            btn.prop('disabled', true);
            status.text('生成中... (数秒かかります)').css('color', '#FF9900');
            $.post(ajaxurl, {
                action: 'node_generate_ai_summary',
                post_id: postId,
                custom_prompt: customPrompt,
                nonce: nonce
            }, function(response) {
                btn.prop('disabled', false);
                if (response.success) {
                    $('#node_ai_summary_textarea').val(response.data.summary);
                    status.text('生成完了！内容を確認して保存してください。').css('color', 'green');
                } else {
                    status.text('エラー: ' + response.data.message).css('color', 'red');
                }
            }).fail(function() {
                btn.prop('disabled', false);
                status.text('通信エラーが発生しました。').css('color', 'red');
            });
        });
    });
    </script>
    <?php
}
