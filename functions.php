<?php
/**
 * Node Theme Functions
 */

// フォント読み込み設定の追加
require_once get_template_directory() . '/functions_fonts.php';

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
    echo '<textarea name="node_ai_summary" style="width:100%; height:80px;" placeholder="記事の3行要約を入力してください...">'.esc_textarea($summary).'</textarea>';
    echo '<p class="description">Gemini API等で生成した要約をここに貼り付けてください。</p>';
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

// カテゴリメタ追加
function node_add_category_fields($term) {
    $color = get_term_meta($term->term_id, '_m3_color', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="m3_color">カテゴリカラー</label></th>
        <td>
            <input name="m3_color" id="m3_color" type="text" value="<?php echo esc_attr($color); ?>" class="node-color-picker" data-default-color="#6750A4">
            <p class="description">このカテゴリのデフォルトMaterial Youシードカラー。</p>
        </td>
    </tr>
    <?php
}
add_action('category_edit_form_fields', 'node_add_category_fields');

function node_add_category_fields_new($taxonomy) {
    ?>
    <div class="form-field">
        <label for="m3_color">カテゴリカラー</label>
        <input name="m3_color" id="m3_color" type="text" value="" class="node-color-picker" data-default-color="#6750A4">
        <p>このカテゴリのデフォルトMaterial Youシードカラー。</p>
    </div>
    <?php
}
add_action('category_add_form_fields', 'node_add_category_fields_new');

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
        '_node_sponsor_tooltip' => 'node_sponsor_tooltip',
        '_m3_primary_color' => 'm3_primary_color'
    ];

    foreach ($fields as $meta_key => $post_key) {
        if (isset($_POST[$post_key])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        } else {
            delete_post_meta($post_id, $meta_key);
        }
    }

    $game_info = [
        'title' => isset($_POST['node_game_title']) ? sanitize_text_field($_POST['node_game_title']) : '',
        'summary' => isset($_POST['node_game_summary']) ? sanitize_textarea_field($_POST['node_game_summary']) : '',
        'links' => isset($_POST['node_game_links']) ? json_decode(stripslashes($_POST['node_game_links']), true) ?: [] : []
    ];

    update_post_meta($post_id, '_node_game_info', $game_info);
}
add_action('save_post', 'node_save_custom_meta');

function node_save_category_meta($term_id) {
    if (isset($_POST['m3_color'])) {
        update_term_meta($term_id, '_m3_color', sanitize_text_field($_POST['m3_color']));
    }
}
add_action('edited_category', 'node_save_category_meta');
add_action('create_category', 'node_save_category_meta');

// アセット
function node_enqueue_assets() {
    wp_enqueue_style('node-style', get_stylesheet_uri());
    wp_enqueue_style('node-features-style', get_template_directory_uri() . '/features.css');
    // ViteでビルドされたメインJSを読み込む
    wp_enqueue_script('node-main-js', get_template_directory_uri() . '/assets/js/main.js', [], null, true);
    // テーマ固有の機能（ダークモード等）を読み込む
    wp_enqueue_script('node-features-js', get_template_directory_uri() . '/features.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

function node_enqueue_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php', 'term.php', 'edit-tags.php'])) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('node-admin-js', get_template_directory_uri() . '/assets/js/editor.js', ['wp-color-picker'], null, true);
    wp_add_inline_script('node-admin-js', 'jQuery(function($){ $(".node-color-picker").wpColorPicker(); });');
}
add_action('admin_enqueue_scripts', 'node_enqueue_admin_assets');

// サムネ
add_theme_support('post-thumbnails');

// --- ユーティリティ ---

/**
 * 投稿日時を相対表示（◯時間前など）にする
 */
if (!function_exists('node_get_relative_date')) {
    function node_get_relative_date($post_id = null) {
        $post_id = $post_id ?: get_the_ID();
        if (!$post_id) return '';

        $post_time = get_the_time('U', $post_id);
        $current_time = current_time('timestamp');
        $diff = $current_time - $post_time;

        if ($diff < 3600) {
            // 1時間以内
            if ($diff < 60) {
                return 'たった今';
            }
            return floor($diff / 60) . '分前';
        } elseif ($diff < 86400) {
            // 1日以内（◯時間◯分前）
            $hours = floor($diff / 3600);
            $minutes = floor(($diff % 3600) / 60);
            return $hours . '時間' . $minutes . '分前';
        } elseif ($diff < 604800) {
            // 7日以内
            return floor($diff / 86400) . '日前';
        } else {
            // それ以降
            return get_the_date('', $post_id);
        }
    }
}
// --- ユーティリティ ---

/**
 * 画像からシードカラー（主色）を抽出する（PHP GD使用）
 */
function node_get_image_seed_color($attachment_id) {
    if (!$attachment_id) return null;

    // キャッシュを確認
    $cached = get_post_meta($attachment_id, '_node_seed_color', true);
    if ($cached) return $cached;

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return null;

    // 画像サイズ情報を取得
    $info = getimagesize($file_path);
    if (!$info) return null;

    // 画像を読み込み（JPEG, PNG, WEBPに対応）
    $image = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($file_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($file_path); break;
        case IMAGETYPE_WEBP: $image = imagecreatefromwebp($file_path); break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($file_path); break;
    }

    if (!$image) return null;

    // 1x1ピクセルにリサイズして平均色を抽出
    $pixel = imagecreatetruecolor(1, 1);
    imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

    $rgb = imagecolorat($pixel, 0, 0);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

    // 次回からの高速化のためにメタデータに保存
    update_post_meta($attachment_id, '_node_seed_color', $hex);

    imagedestroy($image);
    imagedestroy($pixel);

    return $hex;
}

function node_get_category_color($cat_id, $post_id = null) {
    // 1. 投稿個別カラー
    if ($post_id) {
        $post_color = get_post_meta($post_id, '_m3_primary_color', true);
        if ($post_color) return $post_color;
    }

    // 2. カテゴリ個別カラー
    $cat_color = get_term_meta($cat_id, '_m3_color', true);
    if ($cat_color) return $cat_color;

    // 3. アイキャッチからの自動抽出 (PHP)
    if ($post_id && has_post_thumbnail($post_id)) {
        $thumb_id = get_post_thumbnail_id($post_id);
        $seed_color = node_get_image_seed_color($thumb_id);
        if ($seed_color) return $seed_color;
    }

    // 4. フォールバック
    return '#6750A4';
}


// カテゴリーラベル表示
function node_the_category_labels($post_id = null, $max = 4) {
    if (!$post_id) $post_id = get_the_ID();
    $categories = get_the_category($post_id);
    if (empty($categories)) return;

    $count = count($categories);
    $display_cats = array_slice($categories, 0, $max);

    $thumb_url = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'medium') : '';

    echo '<div class="m3-card__categories-top">';
    foreach ($display_cats as $cat) {
        $color = node_get_category_color($cat->term_id, $post_id);
        echo '<span class="m3-label m3-label--category" data-color="' . esc_attr($color) . '" data-thumb="' . esc_attr($thumb_url) . '">';
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
        echo '<span class="material-symbols-outlined">auto_awesome</span>';
        echo '生成されたメディアを含みます';
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