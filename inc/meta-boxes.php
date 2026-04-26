<?php
// 1. 各種メタボックスの追加
function node_add_custom_meta_boxes() {
    add_meta_box('node_content_rating', 'コンテンツ評価設定', 'node_cero_z_meta_box_callback', 'post', 'side');
    add_meta_box('node_post_labels', '記事ラベル設定', 'node_post_labels_callback', 'post', 'side');
    add_meta_box('node_m3_color', 'Material You カラー設定', 'node_m3_color_meta_box_callback', 'post', 'side');
    add_meta_box('node_ai_summary', 'Nexus Abstract (AI要約)', 'node_ai_summary_callback', 'post', 'normal', 'high');
    add_meta_box('node_game_info', 'ゲーム・アプリ情報', 'node_game_info_callback', 'post', 'normal');
}
add_action('add_meta_boxes', 'node_add_custom_meta_boxes');

// --- コールバック関数 ---

function node_cero_z_meta_box_callback($post) {
    $value = get_post_meta($post->ID, '_node_is_cero_z', true);
    wp_nonce_field('node_save_meta_box', 'node_meta_box_nonce');
    echo '<label><input type="checkbox" name="node_is_cero_z" value="1" '.checked($value, '1', false).'> CERO Z (18歳以上) を適用</label>';
}

function node_post_labels_callback($post) {
    $is_ai = get_post_meta($post->ID, '_node_is_ai_generated', true);
    $is_sponsor = get_post_meta($post->ID, '_node_is_sponsor', true);
    $sponsor_text = get_post_meta($post->ID, '_node_sponsor_text', true) ?: 'SPONSORED';
    $sponsor_tooltip = get_post_meta($post->ID, '_node_sponsor_tooltip', true) ?: '本記事はスポンサー提供です。';
    echo '<p><label><input type="checkbox" name="node_is_ai_generated" value="1" '.checked($is_ai, '1', false).'> 生成されたメディアを含みます</label></p>';
    echo '<p><label><input type="checkbox" name="node_is_sponsor" value="1" '.checked($is_sponsor, '1', false).'> スポンサー記事（案件 ）</label></p>';
    echo '<p><label>スポンサーラベル文言:<br><input type="text" name="node_sponsor_text" value="'.esc_attr($sponsor_text).'" style="width:100%"></label></p>';
    echo '<p><label>スポンサー説明文 (ホバー時):<br><input type="text" name="node_sponsor_tooltip" value="'.esc_attr($sponsor_tooltip).'" style="width:100%"></label></p>';
}

function node_m3_color_meta_box_callback($post) {
    $color = get_post_meta($post->ID, '_m3_primary_color', true);
    echo '<p><label>投稿個別カラー（Material You）:<br>';
    echo '<input type="text" name="m3_primary_color" value="' . esc_attr($color) . '" class="node-color-picker"></label></p>';
    echo '<p class="description">未設定の場合はカテゴリ設定またはアイキャッチ画像から自動生成されます。</p>';
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
function node_game_info_callback($post) {
    $info = get_post_meta($post->ID, '_node_game_info', true);
    if (!is_array($info)) {
        $info = ['title' => '', 'summary' => '', 'links' => []];
    }
    ?>
    <p><label>タイトル: <input type="text" name="node_game_title" value="<?php echo esc_attr($info['title']); ?>" style="width:100%"></label></p>
    <p><label>要約: <textarea name="node_game_summary" style="width:100%"><?php echo esc_textarea($info['summary']); ?></textarea></label></p>
    <textarea name="node_game_links" style="width:100%;"><?php echo esc_textarea(json_encode($info['links'])); ?></textarea>
    <?php
}

// 保存処理
function node_save_custom_meta($post_id) {
    if (!isset($_POST['node_meta_box_nonce']) || !wp_verify_nonce($_POST['node_meta_box_nonce'], 'node_save_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // --- AI生成メディアの自動判別 ---
    $has_ai_media = false;
    
    // 1. サムネイル画像（アイキャッチ）のチェック
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id && get_post_meta($thumbnail_id, '_node_is_ai_media', true) === '1') {
        $has_ai_media = true;
    }
    
    // 2. 本文内の画像のチェック (Gutenbergの wp-image-{id} クラスからIDを抽出)
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

    // AI生成メディアが含まれている場合、投稿自体の「生成されたメディアを含む」ラベルを強制的にONにする
    if ($has_ai_media) {
        $_POST['node_is_ai_generated'] = '1';
    }
    // -----------------------------

    // 通常のテキストフィールド（1行）
    $text_fields = [
        '_node_is_cero_z'       => 'node_is_cero_z',
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

    // AI要約は複数行テキストとして sanitize_textarea_field で処理
    if (isset($_POST['node_ai_summary'])) {
        update_post_meta($post_id, '_node_ai_summary', sanitize_textarea_field($_POST['node_ai_summary']));
    } else {
        delete_post_meta($post_id, '_node_ai_summary');
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
add_action('save_post', 'node_save_custom_meta');
