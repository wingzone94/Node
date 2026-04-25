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

/**
 * ==========================================================================
 * OGP自動生成機能 (Gemini APIなし、GDライブラリ使用)
 * アイキャッチがない場合に、タイトル入りの画像を動的に生成してSNSシェアに対応する。
 * ==========================================================================
 */

/**
 * 1. OGPタグの自動出力
 */
function node_ogp_head_output() {
    if (!is_singular()) return;

    $post_id = get_the_ID();
    $title   = get_the_title();
    $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 60);
    $url     = get_permalink();
    $image   = node_get_dynamic_og_image_url($post_id);

    echo "\n<!-- Luminous Core Dynamic OGP -->\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($excerpt) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($excerpt) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    echo "<!-- /Luminous Core Dynamic OGP -->\n\n";
}
add_action('wp_head', 'node_ogp_head_output', 5);

/**
 * 2. OGP画像のURL取得（未生成なら生成）
 */
function node_get_dynamic_og_image_url($post_id) {
    // アイキャッチがあればそれを使用
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, 'large');
    }

    $upload_dir = wp_upload_dir();
    $cache_dir  = $upload_dir['basedir'] . '/ogp-cache';
    $cache_url  = $upload_dir['baseurl'] . '/ogp-cache';
    $filename   = 'ogp-' . $post_id . '.png';
    $filepath   = $cache_dir . '/' . $filename;

    // キャッシュが存在すればそのURLを返す
    if (file_exists($filepath)) {
        return $cache_url . '/' . $filename . '?v=' . filemtime($filepath);
    }

    // なければ画像を生成してURLを返す
    return node_create_dynamic_ogp($post_id, $cache_dir, $cache_url, $filename);
}

/**
 * 3. GDライブラリによる画像生成コアロジック
 */
function node_create_dynamic_ogp($post_id, $cache_dir, $cache_url, $filename) {
    if (!extension_loaded('gd')) return '';

    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }

    $width  = 1200;
    $height = 630;
    $image  = imagecreatetruecolor($width, $height);

    // 背景色（記事のテーマカラーまたはデフォルトのオレンジ）
    $seed_color = '#FF9900';
    $category_color = null;
    $categories = get_the_category($post_id);
    if (!empty($categories)) {
        $category_color = get_term_meta($categories[0]->term_id, '_m3_color', true);
    }
    $seed_color = get_post_meta($post_id, '_m3_primary_color', true) ?: ($category_color ?: '#FF9900');

    list($r, $g, $b) = sscanf($seed_color, "#%02x%02x%02x");
    $bg_color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $bg_color);

    // 文字色（白）
    $text_color = imagecolorallocate($image, 255, 255, 255);

    // タイトル描画
    $title = get_the_title($post_id);
    
    // システムフォントの探索 (日本語対応)
    $font = '';
    $possible_fonts = [
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/truetype/noto/NotoSansJP-Bold.otf',
        '/usr/share/fonts/truetype/fonts-japanese-gothic.ttf',
        '/System/Library/Fonts/AppleSDGothicNeo.ttc', // Mac
        '/System/Library/Fonts/Hiragino Sans GB.ttc', // Mac
        '/Library/Fonts/Arial Unicode.ttf',
        '/usr/share/fonts/truetype/ipafont/ipag.ttf', // Linux
    ];
    foreach ($possible_fonts as $f) {
        if (file_exists($f)) { $font = $f; break; }
    }

    if ($font && function_exists('imagettftext')) {
        // タイトル描画（折り返し）
        $wrapped_title = node_mb_wordwrap($title, 18);
        imagettftext($image, 45, 0, 100, 200, $text_color, $font, $wrapped_title);
        
        // サイト名を描画
        imagettftext($image, 25, 0, 100, 550, $text_color, $font, get_bloginfo('name'));
    } else {
        // Fallback: 日本語不可の場合は英数字のみ
        imagestring($image, 5, 100, 100, "Luminous Core Article", $text_color);
        imagestring($image, 5, 100, 130, "OGP Image Generated", $text_color);
    }

    $dest = $cache_dir . '/' . $filename;
    imagepng($image, $dest);
    imagedestroy($image);

    return $cache_url . '/' . $filename;
}

/**
 * 日本語マルチバイト対応の簡易Wordwrap
 */
function node_mb_wordwrap($str, $width = 18) {
    $lines = [];
    $len = mb_strlen($str);
    for ($i = 0; $i < $len; $i += $width) {
        $lines[] = mb_substr($str, $i, $width);
    }
    return implode("\n", $lines);
}

/**
 * 記事更新時にOGPキャッシュをクリア
 */
function node_clear_ogp_cache($post_id) {
    $upload_dir = wp_upload_dir();
    $filepath   = $upload_dir['basedir'] . '/ogp-cache/ogp-' . $post_id . '.png';
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}
add_action('save_post', 'node_clear_ogp_cache');

function node_save_category_meta($term_id) {
    if (isset($_POST['m3_color'])) {
        update_term_meta($term_id, '_m3_color', sanitize_text_field($_POST['m3_color']));
    }
}
add_action('edited_category', 'node_save_category_meta');
add_action('create_category', 'node_save_category_meta');

function node_category_add_form_fields() {
    ?>
    <div class="form-field">
        <label for="m3_color">テーマカラー (Hex)</label>
        <input name="m3_color" id="m3_color" type="text" value="" class="node-color-picker" data-default-color="#FF9900">
        <p>カテゴリのベースカラーを16進数で指定します（例: #FF9900）。空欄または「auto」の場合はアイキャッチ画像から自動抽出します。</p>
    </div>
    <?php
}
add_action('category_add_form_fields', 'node_category_add_form_fields');

function node_category_edit_form_fields($term) {
    $color = get_term_meta($term->term_id, '_m3_color', true) ?: '#FF9900';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="m3_color">テーマカラー (Hex)</label></th>
        <td>
            <input name="m3_color" id="m3_color" type="text" value="<?php echo esc_attr($color); ?>" class="node-color-picker" data-default-color="#FF9900">
            <p class="description">カテゴリのベースカラー（例: #FF9900）。</p>
        </td>
    </tr>
    <?php
}

// アセット
function node_enqueue_assets() {
    $version = '0.4.0';
    wp_enqueue_style('node-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_style('node-assets-style', get_template_directory_uri() . '/assets/css/style.css', [], $version);
    wp_enqueue_style('node-blocks-style', get_template_directory_uri() . '/assets/css/blocks.css', [], $version);

    wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', [], null, true);
    wp_enqueue_script('gsap-scrollto', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js', ['gsap'], null, true);
    wp_enqueue_script('node-main-js', get_template_directory_uri() . '/assets/js/main.js', ['gsap', 'gsap-scrollto'], $version, true);
    wp_enqueue_script('node-blocks-js', get_template_directory_uri() . '/assets/js/blocks.js', ['gsap'], $version, true);
}
add_action('wp_enqueue_scripts', 'node_enqueue_assets');

/**
 * アーカイブのタイトルから「カテゴリー: 」などを削除
 */
add_filter('get_the_archive_title', function ($title) {
    if (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_post_type_archive()) {
        $title = post_type_archive_title('', false);
    } elseif (is_tax()) {
        $title = single_term_title('', false);
    }
    return $title;
});

/**
 * SEO/AMP: 構造化データ (JSON-LD) の追加
 */
function node_add_json_ld() {
    if (is_single()) {
        global $post;
        $json = [
            "@context" => "https://schema.org",
            "@type" => "BlogPosting",
            "headline" => get_the_title(),
            "image" => [get_the_post_thumbnail_url($post->ID, 'full')],
            "datePublished" => get_the_date('c'),
            "dateModified" => get_the_modified_date('c'),
            "author" => [
                "@type" => "Person",
                "name" => get_the_author(),
                "url" => get_author_posts_url(get_the_author_meta('ID'))
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => get_bloginfo('name'),
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => get_template_directory_uri() . '/pwa-icon.png'
                ]
            ],
            "description" => get_the_excerpt()
        ];
        echo "\n" . '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
add_action('wp_head', 'node_add_json_ld');

/**
 * Safari/Chrome用テーマカラーの強制出力 (最優先)
 */
function node_force_theme_color_meta() {
    echo '<meta name="theme-color" content="#FF9900">' . "\n";
    echo '<meta name="msapplication-TileColor" content="#FF9900">' . "\n";
    // Safariのレンダリングエンジン向けに、最上部の背景色を認識させるためのスクリプト
    echo '<script>document.documentElement.style.backgroundColor = "#FF9900";</script>' . "\n";
}
add_action('wp_head', 'node_force_theme_color_meta', 9999);

function node_enqueue_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php', 'term.php', 'edit-tags.php'])) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('node-admin-js', get_template_directory_uri() . '/assets/js/editor.js', ['wp-color-picker', 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post'], null, true);
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
    $diff = intval($current_time) - intval($post_time);

    $full_date = get_the_date('Y年n月j日', $post_id);

    // 24時間（86400秒）以内の場合のみカッコ書きを入れる
    if ($diff > 0 && $diff < 86400) {
        $relative = '';
        if ($diff < 3600) {
            $relative = ($diff < 60) ? 'たった今' : floor($diff / 60) . '分前';
        } else {
            $relative = floor($diff / 3600) . '時間前';
        }
        return $full_date . ' （' . $relative . '）';
    }

    return $full_date;
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
    
    // JSのカラー抽出用にアイキャッチURLを取得
    $thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '';
    
    $is_card = !is_single();
    // カード表示なら3つ、シングルページならすべて（999個）表示
    $limit = $is_card ? 3 : 999;
    $count = count($categories);
    $display_cats = array_slice($categories, 0, $limit);

    echo '<div class="m3-article__category-group' . ($is_card ? ' is-card' : '') . '">';
    if (!$is_card) {
        echo '<span class="m3-article__category-label">CATEGORY</span>';
    }
    
    foreach ($display_cats as $cat) {
        echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" ';
        echo 'class="m3-label--category" ';
        echo 'data-color="auto" ';
        echo 'data-thumb="' . esc_url($thumb_url) . '"';
        echo '>';
        echo '<span class="material-symbols-outlined">folder</span>' . esc_html($cat->name) . '</a>';
    }
    
    if ($count > $limit) {
        $remaining = $count - $limit;
        echo '<span class="m3-label--category-more" title="さらに ' . $remaining . ' 件のカテゴリがあります">+' . $remaining . '</span>';
    }
    echo '</div>';
}

function node_the_post_badges($post_id = null, $mode = 'compact') {
    if (!$post_id) $post_id = get_the_ID();
    
    // AI生成ラベル
    if (get_post_meta($post_id, '_node_is_ai_generated', true) === '1') {
        $ai_tooltip = 'AI生成されたメディアを含みます';
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
