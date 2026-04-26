<?php
/**
 * AI 要約メタボックス UI（スタブ）
 *
 * @package Luminous_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function node_ai_summary_callback($post) {
    $summary = get_post_meta($post->ID, '_node_ai_summary', true);
    wp_nonce_field('node_ai_generate_action', 'node_ai_generate_nonce');
    echo '<textarea id="node_ai_summary_textarea" name="node_ai_summary" style="width:100%; height:80px;" placeholder="記事の3行要約を入力してください...">'.esc_textarea($summary).'</textarea>';
    echo '<p class="description">Gemini 3.1 Pro Preview モデルを利用して自動生成・手動修正が可能です。<br>';
    echo '<button type="button" id="node_generate_ai_btn" class="button button-secondary" data-post-id="'.esc_attr($post->ID).'">AIで要約を生成</button>';
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
            btn.prop('disabled', true);
            status.text('生成中...').css('color', '#FF9900');
            $.post(ajaxurl, {
                action: 'node_generate_ai_summary',
                post_id: postId,
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
