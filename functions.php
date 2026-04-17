<?php
/**
 * Node Theme Functions
 */

// 1. 各種メタボックスの追加
function node_add_custom_meta_boxes() {
    add_meta_box('node_content_rating', 'コンテンツ評価設定', 'node_cero_z_meta_box_callback', 'post', 'side');
    add_meta_box('node_post_labels', '記事ラベル設定', 'node_post_labels_callback', 'post', 'side');
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
    echo '<p><label><input type="checkbox" name="node_is_ai_generated" value="1" '.checked($is_ai, '1', false).'> AI生成メディアを含む</label></p>';
    echo '<p><label><input type="checkbox" name="node_is_sponsor" value="1" '.checked($is_sponsor, '1', false).'> スポンサー記事（案件）</label></p>';
    echo '<p><label>スポンサーラベル文言:<br><input type="text" name="node_sponsor_text" value="'.esc_attr($sponsor_text).'" style="width:100%"></label></p>';
    echo '<p><label>スポンサー説明文 (ホバー時):<br><input type="text" name="node_sponsor_tooltip" value="'.esc_attr($sponsor_tooltip).'" style="width:100%"></label></p>';
}

function node_ai_summary_callback($post) {
    $summary = get_post_meta($post->ID, '_node_ai_summary', true);
    echo '<textarea name="node_ai_summary" style="width:100%; height:80px;" placeholder="記事の3行要約を入力してください...">'.esc_textarea($summary).'</textarea>';
    echo '<p class="description">Gemini API等で生成した要約をここに貼り付けてください。</p>';
}

function node_game_info_callback($post) {
    $info = get_post_meta($post->ID, '_node_game_info', true) ?: ['title' => '', 'summary' => '', 'links' => []];
    ?>
    <p><label>タイトル: <input type="text" name="node_game_title" value="<?php echo esc_attr($info['title']); ?>" style="width:100%"></label></p>
    <p><label>要約: <textarea name="node_game_summary" style="width:100%"><?php echo esc_textarea($info['summary']); ?></textarea></label></p>
    <textarea name="node_game_links" style="width:100%;"><?php echo esc_textarea(json_encode($info['links'])); ?></textarea>
    <?php
}

// 保存処理（修正済み）
function node_save_custom_meta($post_id) {
    if (!isset($_POST['node_meta_box_nonce']) || !wp_verify_nonce($_POST['node_meta_box_nonce'], 'node_save_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $fields = [
        '_node_is_cero_z' => 'node_is_cero_z',
        '_node_is_ai_generated' => 'node_is_ai_generated',
        '_node_is_sponsor' => 'node_is_sponsor',
        '_node_ai_summary' => 'node_ai_summary',
        '_node_sponsor_text' => 'node_sponsor_text',
        '_node_sponsor_tooltip' => 'node_sponsor_tooltip'
    ];

    foreach ($fields as $meta_key => $post_key) {
        if (isset($_POST[$post_key])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        } else {
            delete_post_meta($post_id, $meta_key);
        }
    }

    // 🔥 修正ポイント（安全化）
    $game_info = [
        'title' => isset($_POST['node_game_title']) ? sanitize_text_field($_POST['node_game_title']) : '',
        'summary' => isset($_POST['node_game_summary']) ? sanitize_textarea_field($_POST['node_game_summary']) : '',
        'links' => isset($_POST['node_game_links']) ? json_decode(stripslashes($_POST['node_game_links']), true) ?: [] : []
    ];

    update_post_meta($post_id, '_node_game_info', $game_info);
}
add_action('save_post', 'node_save_custom_meta');

// アセット
function node_enqueue_assets() {
    wp_enqueue_style('node-style', get_stylesheet_uri());
    wp_enqueue_style('node-features-style', get_template_directory_uri() . '/features.css');
    wp_enqueue_script('node-features-js', get_template_directory_uri() . '/features.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

// サムネ
add_theme_support('post-thumbnails');

// 🔥 日付関数（これが今回の主役）
function node_get_relative_date($post_id) {
    $post_time = get_post_time('U', false, $post_id);
    $current_time = current_time('timestamp');
    $diff = $current_time - $post_time;

    $date_str = get_the_date('Y年n月j日', $post_id);

    if ($diff > 0 && $diff < 86400) {
        $hours = floor($diff / 3600);
        if ($hours < 1) return $date_str . ' (1時間以内)';
        return $date_str . ' (' . $hours . '時間前)';
    }
    return $date_str;
}// カテゴリーラベル表示
function node_the_category_labels($post_id = null, $max = 4) {
    if (!$post_id) $post_id = get_the_ID();
    $categories = get_the_category($post_id);
    if (empty($categories)) return;

    $count = count($categories);
    $display_cats = array_slice($categories, 0, $max);

    echo '<div class="m3-card__categories-top">';
    foreach ($display_cats as $cat) {
        echo '<span class="m3-label m3-label--category">';
        echo '<span class="material-symbols-outlined">folder</span>';
        echo esc_html($cat->name);
        echo '</span>';
    }
    if ($count > $max) {
        echo '<span class="m3-label m3-label--category-more">+' . ($count - $max) . '</span>';
    }
    echo '</div>';
}

// 投稿バッジ表示
function node_the_post_badges($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    echo '<div class="m3-card__badges-top">';

    if (get_post_meta($post_id, '_node_is_ai_generated', true) === '1') {
        echo '<span class="m3-label m3-label--ai">';
        echo 'AI生成';
        echo '</span>';
    }

    if (get_post_meta($post_id, '_node_is_sponsor', true) === '1') {
        $sponsor_text = get_post_meta($post_id, '_node_sponsor_text', true) ?: 'SPONSOR';
        echo '<span class="m3-label m3-label--sponsor">';
        echo esc_html($sponsor_text);
        echo '</span>';
    }

    echo '</div>';
}