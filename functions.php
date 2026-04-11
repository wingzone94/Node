<?php
/**
 * Node Theme Functions
 */

// 1. 各種メタボックスの追加
function node_add_custom_meta_boxes() {
    // CERO Z (既存)
    add_meta_box('node_content_rating', 'コンテンツ評価設定', 'node_cero_z_meta_box_callback', 'post', 'side');
    // ラベル設定 (AI / スポンサー)
    add_meta_box('node_post_labels', '記事ラベル設定', 'node_post_labels_callback', 'post', 'side');
    // AI要約 (Nexus Abstract)
    add_meta_box('node_ai_summary', 'Nexus Abstract (AI要約)', 'node_ai_summary_callback', 'post', 'normal', 'high');
    // ゲーム・アプリ情報
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
    <div id="game-links-container">
        <p>ストアリンク (JSON形式):</p>
        <textarea name="node_game_links" style="width:100%; font-family:monospace;"><?php echo esc_textarea(json_encode($info['links'])); ?></textarea>
        <small>例: [{"platform":"Steam", "url":"..."}, {"platform":"iOS", "url":"..."}]</small>
    </div>
    <?php
}

// 保存処理
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
        if (isset($_POST[$post_key])) update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        else delete_post_meta($post_id, $meta_key);
    }

    $game_info = [
        'title' => sanitize_text_field($_POST['node_game_title']),
        'summary' => sanitize_textarea_field($_POST['node_game_summary']),
        'links' => json_decode(stripslashes($_POST['node_game_links']), true) ?: []
    ];
    update_post_meta($post_id, '_node_game_info', $game_info);
}
add_action('save_post', 'node_save_custom_meta');

// 2. アセット読み込み
function node_enqueue_assets() {
    wp_enqueue_style('material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null);
    wp_enqueue_style('node-style', get_stylesheet_uri(), array(), '0.1.3');
    wp_enqueue_style('node-features-style', get_template_directory_uri() . '/features.css', array(), '0.1.3');
    wp_enqueue_script('node-features-js', get_template_directory_uri() . '/features.js', array(), '0.1.3', true);
    wp_localize_script('node-features-js', 'nodeData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('node_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

// 3. CERO Z ダイアログ
function node_render_cero_z_dialog() {
    if (is_single() && get_post_meta(get_the_ID(), '_node_is_cero_z', true) === '1') {
        ?>
        <dialog id="cero-z-dialog" class="node-dialog">
            <div class="node-dialog__content">
                <h2>年齢制限の確認</h2>
                <p>この記事には18歳以上の方のみ閲覧可能な表現が含まれています。</p>
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

// 4. サムネイルサポート
add_theme_support('post-thumbnails');
set_post_thumbnail_size(800, 450, true);

// 5. コメント機能の拡張
// 下線 (u) と リンク (a) タグを許可
function node_allow_extra_comment_tags($allowedtags) {
    if (!isset($allowedtags['u'])) {
        $allowedtags['u'] = array();
    }
    if (!isset($allowedtags['a'])) {
        $allowedtags['a'] = array(
            'href'   => array(),
            'title'  => array(),
            'target' => array(),
            'rel'    => array(),
        );
    }
    return $allowedtags;
}
add_filter('wp_kses_allowed_html', 'node_allow_extra_comment_tags', 10, 2);

// 名前が空の場合にデフォルト値を設定
function node_optional_comment_author($commentdata) {
    if (empty(trim($commentdata['comment_author']))) {
        $commentdata['comment_author'] = '名無しさん';
    }
    return $commentdata;
}
add_filter('preprocess_comment', 'node_optional_comment_author');

// 7. 日付の相対表示フォーマット
function node_get_relative_date($post_id) {
    $post_time = get_post_time('U', false, $post_id);
    $diff = current_time('timestamp') - $post_time;

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return get_the_date('Y年n月j日', $post_id) . ' (' . ($hours > 0 ? $hours : 1) . '時間前)';
    }
    return get_the_date('Y年n月j日', $post_id);
}