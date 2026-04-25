<?php
/**
 * Luminous Core Theme Functions
 */

// サイト名を強制的に Luminous Core に変更
add_filter('pre_option_blogname', function() {
    return 'Luminous Core';
});

// フォント読み込み設定の追加
require_once get_template_directory() . '/functions_fonts.php';

// カスタムブロックとメディアブリッジの追加
require_once get_template_directory() . '/functions-blocks.php';

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

add_action('wp_ajax_node_generate_ai_summary', 'node_ajax_generate_ai_summary');
function node_ajax_generate_ai_summary() {
    check_ajax_referer('node_ai_generate_action', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => '権限がありません。']);
    }
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_send_json_error(['message' => '不正な投稿IDです。']);
    $post = get_post($post_id);
    if (!$post) wp_send_json_error(['message' => '記事が見つかりません。']);
    $content = strip_shortcodes(strip_tags($post->post_content));
    if (empty(trim($content))) wp_send_json_error(['message' => '記事本文が空です。']);
    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($api_key)) wp_send_json_error(['message' => 'GEMINI_API_KEYが設定されていません。']);

    $prompt = "以下の記事本文を100文字程度で簡潔に要約してください。\n\n" . mb_substr($content, 0, 3000);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key=' . $api_key;
    $body = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 150, 'temperature' => 0.3]
    ]);
    $response = wp_remote_post($url, ['headers' => ['Content-Type' => 'application/json'], 'body' => $body, 'timeout' => 15]);
    if (is_wp_error($response)) wp_send_json_error(['message' => 'APIリクエスト失敗: ' . $response->get_error_message()]);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        wp_send_json_success(['summary' => str_replace(["\r\n", "\r", "\n"], ' ', trim($data['candidates'][0]['content']['parts'][0]['text']))]);
    } else {
        wp_send_json_error(['message' => 'APIから正しいレスポンスが返されませんでした。']);
    }
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
    if (!current_user_can('edit_post', $post_id)) return;

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

// 表示件数の動的制御 (Mobile: 16, PC: 32)
function node_modify_posts_per_page($query) {
    if (!is_admin() && $query->is_main_query() && (is_home() || is_archive() || is_search())) {
        if (wp_is_mobile()) {
            $query->set('posts_per_page', 8);   // Mobile: 1列×8件 - スクロール負荷を軽減
        } else {
            $query->set('posts_per_page', 16);  // PC: 4列×4行 - グリッドに最適
        }
    }
}
add_action('pre_get_posts', 'node_modify_posts_per_page');

/**
 * SPOTLIGHT記事を取得する (スラッグ 'spotlight' のカテゴリとその子)
 */
function node_get_spotlight_categories() {
    $parent = get_category_by_slug('spotlight');
    if (!$parent) return [];

    $args = [
        'parent' => $parent->term_id,
        'hide_empty' => true // 記事がない特集カテゴリは非表示
    ];

    $categories = get_terms('category', $args);
    $result = [];

    if (!is_wp_error($categories) && !empty($categories)) {
        foreach ($categories as $cat) {
            $color = get_term_meta($cat->term_id, '_m3_color', true);
            if (!$color) {
                $color = 'var(--md-sys-color-primary)';
            }
            $result[] = [
                // 「文字の設定等は禁止」の要件通り、自動で「カテゴリ名＋特集」を生成
                'name' => $cat->name . '特集',
                'url' => get_category_link($cat->term_id),
                'color' => $color
            ];
        }
    }

    return $result;
}

/**
 * 管理画面の投稿一覧に『SPOTLIGHT』列を追加
 */
function node_add_spotlight_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['node_spotlight'] = 'SPOTLIGHT';
        }
    }
    return $new_columns;
}
add_filter('manage_post_posts_columns', 'node_add_spotlight_column');

/**
 * 管理画面の『SPOTLIGHT』列の内容を表示
 */
function node_render_spotlight_column($column, $post_id) {
    if ($column === 'node_spotlight') {
        if (has_category('spotlight', $post_id)) {
            echo '<span class="dashicons dashicons-star-filled" style="color: #FF9900;" title="SPOTLIGHTカテゴリに属しています"></span>';
        } else {
            echo '<span class="dashicons dashicons-star-empty" style="color: #ccc; opacity: 0.3;"></span>';
        }
    }
}
add_action('manage_post_posts_custom_column', 'node_render_spotlight_column', 10, 2);

/**
 * 管理画面の列幅を調整
 */
function node_admin_spotlight_style() {
    echo '<style>.column-node_spotlight { width: 100px; text-align: center; }</style>';
}
add_action('admin_head', 'node_admin_spotlight_style');

/**
 * 商品リンクのURLを生成（将来的にアフィリエイトIDを付与しやすいよう独立）
 */
function node_generate_product_link($url, $type = 'amazon') {
    if (empty($url)) return '';
    return esc_url($url);
}

/**
 * 商品リンク ショートコード [m3_product]
 * [product_card] へ一本化済み。後方互換性のためエイリアスとして維持。
 * 既存記事で [m3_product] を使用していても正常に動作します。
 */
add_shortcode('m3_product', 'node_product_card_shortcode');


// メニュー登録
function node_register_menus() {
    register_nav_menus([
        'primary' => 'ヘッダーメニュー',
        'drawer'  => 'サイドドロワーメニュー（カテゴリ等）'
    ]);
}
add_action('after_setup_theme', 'node_register_menus');

// --- Blog Card ---

/**
 * URLからOGP情報を取得する
 */
function node_get_ogp_data($url) {
    $transient_key = 'node_ogp_' . md5($url);
    $cached = get_transient($transient_key);
    if ($cached !== false) return $cached;

    $ogp = [
        'title'       => '',
        'description' => '',
        'image'       => '',
        'favicon'     => '',
        'site_name'   => '',
        'is_internal' => false,
    ];

    $home_url = home_url();
    if (str_contains($url, $home_url)) {
        $post_id = url_to_postid($url);
        if ($post_id) {
            $ogp['title']       = get_the_title($post_id);
            $ogp['description'] = get_the_excerpt($post_id);
            $ogp['image']       = get_the_post_thumbnail_url($post_id, 'large');
            $ogp['site_name']   = get_bloginfo('name');
            $ogp['is_internal'] = true;
            $ogp['favicon']     = get_site_icon_url(32);
            set_transient($transient_key, $ogp, WEEK_IN_SECONDS);
            return $ogp;
        }
    }

    $response = wp_safe_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) return false;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);

    $ogp['title']       = $xpath->evaluate('string(//meta[@property="og:title"]/@content)') ?: $xpath->evaluate('string(//title)');
    $ogp['description'] = $xpath->evaluate('string(//meta[@property="og:description"]/@content)') ?: $xpath->evaluate('string(//meta[@name="description"]/@content)');
    $ogp['image']       = $xpath->evaluate('string(//meta[@property="og:image"]/@content)');
    $ogp['site_name']   = $xpath->evaluate('string(//meta[@property="og:site_name"]/@content)') ?: parse_url($url, PHP_URL_HOST);
    $ogp['favicon']     = 'https://www.google.com/s2/favicons?domain=' . parse_url($url, PHP_URL_HOST) . '&sz=64';

    set_transient($transient_key, $ogp, WEEK_IN_SECONDS);
    return $ogp;
}

function node_blogcard_shortcode($atts) {
    $atts = shortcode_atts(['url' => ''], $atts);
    if (empty($atts['url'])) return '';

    $ogp = node_get_ogp_data($atts['url']);
    if (!$ogp) return '<a href="' . esc_url($atts['url']) . '">' . esc_html($atts['url']) . '</a>';

    ob_start();
    ?>
    <div class="m3-blogcard" onclick="window.open('<?php echo esc_url($atts['url']); ?>', '_blank')">
        <div class="m3-blogcard__content">
            <div class="m3-blogcard__text">
                <h4 class="m3-blogcard__title"><?php echo esc_html($ogp['title']); ?></h4>
                <p class="m3-blogcard__description"><?php echo esc_html(wp_trim_words($ogp['description'], 60)); ?></p>
                <div class="m3-blogcard__footer">
                    <?php if ($ogp['favicon']) : ?>
                        <img src="<?php echo esc_url($ogp['favicon']); ?>" class="m3-blogcard__favicon" alt="" loading="lazy">
                    <?php endif; ?>
                    <span class="m3-blogcard__sitename"><?php echo esc_html($ogp['site_name']); ?></span>
                    <?php if ($ogp['is_internal']) : ?>
                        <span class="m3-blogcard__internal-badge">内部記事</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($ogp['image']) : ?>
                <div class="m3-blogcard__image">
                    <img src="<?php echo esc_url($ogp['image']); ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('blogcard', 'node_blogcard_shortcode');

function node_auto_blogcard($content) {
    $pattern = '/^(<p>)?(https?:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)(<\/p>)?$/im';
    return preg_replace_callback($pattern, function($matches) {
        return node_blogcard_shortcode(['url' => $matches[2]]);
    }, $content);
}
add_filter('the_content', 'node_auto_blogcard', 11);

// AmazonのデフォルトoEmbed（Get Book / Read Sample などのKindle電子書籍専用プレビュー）を確実に無効化する
add_filter('oembed_providers', function($providers) {
    foreach ($providers as $url => $provider) {
        if (strpos($url, 'amazon') !== false) {
            unset($providers[$url]);
        }
    }
    return $providers;
});

function node_save_category_meta($term_id) {
    if (isset($_POST['m3_color'])) {
        update_term_meta($term_id, '_m3_color', sanitize_text_field($_POST['m3_color']));
    }
}
add_action('edited_category', 'node_save_category_meta');
add_action('create_category', 'node_save_category_meta');

// アセット
function node_enqueue_assets() {
    $version = '0.3.2';
    wp_enqueue_style('node-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_style('node-assets-style', get_template_directory_uri() . '/assets/css/style.css', [], $version);
    wp_enqueue_style('node-blocks-style', get_template_directory_uri() . '/assets/css/blocks.css', [], $version);

    wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', [], null, true);
    wp_enqueue_script('node-main-js', get_template_directory_uri() . '/assets/js/main.js', ['gsap'], $version, true);
    wp_enqueue_script('node-blocks-js', get_template_directory_uri() . '/assets/js/blocks.js', ['gsap'], $version, true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

function node_enqueue_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php', 'term.php', 'edit-tags.php'])) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('node-admin-js', get_template_directory_uri() . '/assets/js/editor.js', ['wp-color-picker'], null, true);
    wp_add_inline_script('node-admin-js', 'jQuery(function($){ $(".node-color-picker").wpColorPicker(); });');
}
add_action('admin_enqueue_scripts', 'node_enqueue_admin_assets');

// テーマサポート
add_theme_support('post-thumbnails');
add_theme_support('responsive-embeds');
add_theme_support('align-wide'); // YouTube等のブロックで「幅広」「全幅」や配置ポップアップを表示するために必須

// --- ユーティリティ ---

function node_get_relative_date($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $post_time = get_the_time('U', $post_id);
    $current_time = current_time('timestamp');
    $diff = $current_time - $post_time;

    if ($diff < 3600) {
        return ($diff < 60) ? 'たった今' : floor($diff / 60) . '分前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '時間' . floor(($diff % 3600) / 60) . '分前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '日前';
    }
    return get_the_date('', $post_id);
}

function node_get_image_seed_color($attachment_id) {
    if (!$attachment_id) return null;
    $cached = get_post_meta($attachment_id, '_node_seed_color', true);
    if ($cached) return $cached;

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return null;

    $info = getimagesize($file_path);
    if (!$info) return null;

    $image = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($file_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($file_path); break;
        case IMAGETYPE_WEBP: $image = imagecreatefromwebp($file_path); break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($file_path); break;
    }
    if (!$image) return null;

    $pixel = imagecreatetruecolor(1, 1);
    imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
    $rgb = imagecolorat($pixel, 0, 0);
    $hex = sprintf("#%02x%02x%02x", ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);

    update_post_meta($attachment_id, '_node_seed_color', $hex);
    imagedestroy($image);
    imagedestroy($pixel);
    return $hex;
}

/**
 * 投稿・カテゴリのカラー設定を優先し、M3シードカラーを動的に生成する。
 * 優先順位: 投稿個別カラー > カテゴリカラー > アイキャッチ画像抽出色 > デフォルト
 * API は使用しない（保存済みメタから読み込むのみ）
 */
function node_generate_m3_colors() {
    // デフォルトカラー（Luminous Core ブランドカラー）
    $default_primary      = '#FF9900';
    $default_primary_dark = '#ffb85d';

    $seed_color      = '';
    $seed_color_dark = '';

    // 個別記事ページおよび固定ページ: 投稿メタ → カテゴリメタ → アイキャッチ抽出色 の順に解決
    if (is_singular(['post', 'page'])) {
        $post_id = get_the_ID();

        // 1. 投稿個別カラー
        $post_color = get_post_meta($post_id, '_m3_primary_color', true);
        if (!empty($post_color)) {
            $seed_color = sanitize_hex_color($post_color);
        }

        // 2. カテゴリカラー
        if (empty($seed_color)) {
            $categories = get_the_category($post_id);
            if (!empty($categories)) {
                $cat_color = get_term_meta($categories[0]->term_id, '_m3_color', true);
                if (!empty($cat_color)) {
                    $seed_color = sanitize_hex_color($cat_color);
                }
            }
        }

        // 3. アイキャッチ画像から抽出した色
        if (empty($seed_color)) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $extracted = node_get_image_seed_color($thumb_id);
                if (!empty($extracted)) {
                    $seed_color = sanitize_hex_color($extracted);
                }
            }
        }
    }

    // アーカイブページ: カテゴリカラーを使用
    if (is_category()) {
        $cat_color = get_term_meta(get_queried_object_id(), '_m3_color', true);
        if (!empty($cat_color)) {
            $seed_color = sanitize_hex_color($cat_color);
        }
    }

    // フォールバック: デフォルトカラー
    if (empty($seed_color)) {
        $seed_color = $default_primary;
    }
    if (empty($seed_color_dark)) {
        $seed_color_dark = $default_primary_dark;
    }
    ?>
    <style id="m3-dynamic-colors">
        :root {
            --md-sys-color-primary: <?php echo esc_attr($seed_color); ?>;
            --md-sys-color-on-primary: #ffffff;
            --md-sys-color-primary-container: #ffdcbe;
            --md-sys-color-on-primary-container: #2c1600;
            --md-sys-color-surface: #FFF8F0;
            --md-sys-color-on-surface: #201b16;
            --md-sys-color-surface-container-low: #fff5e9;
            --md-sys-color-surface-container: #ffebcc;
            --md-sys-color-surface-container-high: #ffe5b8;
            --md-sys-color-outline: #817567;
            --md-sys-color-outline-variant: #d3c4b4;
        }
        [data-theme="dark"] {
            --md-sys-color-primary: <?php echo esc_attr($seed_color_dark); ?>;
            --md-sys-color-on-primary: #4a2800;
            --md-sys-color-surface: #1e1b16;
            --md-sys-color-on-surface: #ebe0d9;
            --md-sys-color-surface-container-low: #25221b;
            --md-sys-color-surface-container: #2a2720;
            --md-sys-color-surface-container-high: #322f28;
        }
    </style>
    <?php
}
add_action('wp_head', 'node_generate_m3_colors');

/**
 * Anti-FOUC: 即座にカラーテーマ（システム同期 or 手動）を反映するインラインスクリプト
 */
function node_anti_fouc_script() {
    ?>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('theme');
            var sync = localStorage.getItem('theme-sync') !== 'false'; // デフォルトは同期ON
            var isSysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (sync) {
                document.documentElement.setAttribute('data-theme', isSysDark ? 'dark' : 'light');
            } else if (theme) {
                document.documentElement.setAttribute('data-theme', theme);
            } else {
                document.documentElement.setAttribute('data-theme', isSysDark ? 'dark' : 'light');
            }
        } catch(e) {}
    })();
    </script>
    <?php
}
add_action('wp_head', 'node_anti_fouc_script', 1);

function node_the_category_labels($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $categories = get_the_category($post_id);
    if (empty($categories)) return;
    $cat = $categories[0];
    
    // JSのカラー抽出用にアイキャッチURLを取得
    $thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '';
    
    echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" ';
    echo 'class="m3-label--category" ';
    echo 'data-color="auto" ';
    echo 'data-thumb="' . esc_url($thumb_url) . '"';
    echo '>';
    echo '<span class="material-symbols-outlined">folder</span>' . esc_html($cat->name) . '</a>';
}

function node_the_post_badges($post_id = null, $mode = 'compact') {
    if (!$post_id) $post_id = get_the_ID();
    
    // AI生成ラベル
    if (get_post_meta($post_id, '_node_is_ai_generated', true) === '1') {
        $ai_tooltip = 'この記事にはAIで生成されたメディアを含みます。';
        $ai_class = 'm3-label--ai m3-tooltip-target';
        if ($mode === 'compact') $ai_class .= ' m3-label--icon-only';

        echo '<span class="' . esc_attr($ai_class) . '" data-tooltip="' . esc_attr($ai_tooltip) . '">';
        echo '<span class="material-symbols-outlined">auto_awesome</span>';
        if ($mode === 'full') {
            echo '<span class="m3-label__text">生成されたメディアを含む</span>';
        }
        echo '</span>';
    }

    // スポンサーラベル
    if (get_post_meta($post_id, '_node_is_sponsor', true) === '1') {
        $sponsor_text = get_post_meta($post_id, '_node_sponsor_text', true) ?: 'SPONSORED';
        
        // 個別ページ（full）の場合は管理画面のカスタム文言を使用、カード（compact）は固定文言
        if ($mode === 'full') {
            $sponsor_tooltip = get_post_meta($post_id, '_node_sponsor_tooltip', true) ?: 'この記事はスポンサー提供です。';
        } else {
            $sponsor_tooltip = 'この記事はスポンサー提供です。';
        }
        
        $sp_class = 'm3-label--sponsor m3-tooltip-target';
        if ($mode === 'compact') $sp_class .= ' m3-label--icon-only';

        echo '<span class="' . esc_attr($sp_class) . '" data-tooltip="' . esc_attr($sponsor_tooltip) . '">';
        echo '<span class="material-symbols-outlined">info</span>';
        if ($mode === 'full') {
            echo '<span class="m3-label__text">' . esc_html($sponsor_text) . '</span>';
        }
        echo '</span>';
    }
}

function node_generate_ai_metadata($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || $post->post_type !== 'post' || $post->post_status !== 'publish') return;
    $content = strip_shortcodes(strip_tags($post->post_content));
    $hash = md5($content);
    if ($hash !== get_post_meta($post_id, '_node_content_hash', true)) {
        $char_count = mb_strlen(preg_replace('/\s+/', '', $content));
        $total_seconds = ceil(($char_count / 800) * 60);
        update_post_meta($post_id, '_node_reading_time', floor($total_seconds / 60) . '分' . sprintf('%02d', $total_seconds % 60) . '秒');
        update_post_meta($post_id, '_node_content_hash', $hash);
    }
}
add_action('save_post', 'node_generate_ai_metadata', 20, 3);

/* ==========================================================================
   広告エリアの制御
   ========================================================================== */
function node_the_ad_area($position) {
    // 広告コードは将来的に設定画面等から取得できるようにオプション設定を使用
    $ad_code = get_option('node_ad_code_' . $position, '');
    
    // 広告タグが設定されていない場合は非表示（出力しない）
    if (empty(trim($ad_code))) {
        return;
    }
    
    echo '<div class="m3-ad-area m3-ad-area--' . esc_attr($position) . '">' . do_shortcode($ad_code) . '</div>';
}
