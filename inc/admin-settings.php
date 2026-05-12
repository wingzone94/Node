<?php
/**
 * Theme Settings Page
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Settings Menu
 */
function node_add_admin_menu() {
    $page = add_options_page(
        'Luminous Theme Settings',
        'Luminous Settings',
        'manage_options',
        'luminous-settings',
        'node_render_settings_page'
    );
    add_action( 'admin_print_scripts-' . $page, function() {
        wp_enqueue_media();
    } );
}
add_action( 'admin_menu', 'node_add_admin_menu' );

/**
 * Register Settings
 */
function node_register_settings() {
    // --- Gemini設定 ---
    // Gemini設定はユーザーごとに保存するため、ここでは登録しません
    // register_setting( 'node_settings_group', 'node_gemini_api_key' );
    // register_setting( 'node_settings_group', 'node_gemini_model' );

    // --- ライブラリ設定 (Node Library) ---
    register_setting( 'node_settings_group', 'node_library_auto_insert' );
    register_setting( 'node_settings_group', 'node_library_header_text' );
    register_setting( 'node_settings_group', 'node_library_button_text' );

    // --- 商品リンク設定 (Nexus) ---
    register_setting( 'node_settings_group', 'luminous_nexus_amazon_id' );
    register_setting( 'node_settings_group', 'luminous_nexus_rakuten_id' );
    register_setting( 'node_settings_group', 'luminous_nexus_disclosure_amazon' );
    register_setting( 'node_settings_group', 'luminous_nexus_disclosure_rakuten' );
    
    // --- X (Twitter) 連携設定 ---
    register_setting( 'node_settings_group', 'node_x_api_key' );
    register_setting( 'node_settings_group', 'node_x_api_secret' );
    register_setting( 'node_settings_group', 'node_x_access_token' );
    register_setting( 'node_settings_group', 'node_x_access_token_secret' );
    register_setting( 'node_settings_group', 'node_x_post_template' );

    // --- OGP設定 ---
    register_setting( 'node_settings_group', 'node_ogp_enabled' );
    register_setting( 'node_settings_group', 'node_ogp_bg_id' );
    register_setting( 'node_settings_group', 'node_ogp_logo_id' );
}
/**
 * ユーザー個別のGemini設定を保存
 */
function node_save_user_gemini_settings() {
    if ( ! isset( $_POST['node_gemini_settings_nonce'] ) || ! wp_verify_nonce( $_POST['node_gemini_settings_nonce'], 'node_save_gemini' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $user_id = get_current_user_id();

    if ( isset( $_POST['node_gemini_api_key'] ) ) {
        update_user_meta( $user_id, 'node_gemini_api_key', sanitize_text_field( $_POST['node_gemini_api_key'] ) );
    }

    if ( isset( $_POST['node_gemini_model'] ) ) {
        update_user_meta( $user_id, 'node_gemini_model', sanitize_text_field( $_POST['node_gemini_model'] ) );
    }
}
add_action( 'admin_init', 'node_save_user_gemini_settings' );

add_action( 'admin_init', 'node_register_settings' );

/**
 * Render Settings Page
 */
function node_render_settings_page() {
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 30px;">Luminous Core テーマ設定</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'node_settings_group' );
            wp_nonce_field( 'node_save_gemini', 'node_gemini_settings_nonce' );
            ?>
            
            <!-- Gemini設定 -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-rest-api"></span> Gemini 設定
                </h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <?php $user_api_key = get_user_meta( get_current_user_id(), 'node_gemini_api_key', true ); ?>
                            <input type="password" name="node_gemini_api_key" value="<?php echo esc_attr( $user_api_key ); ?>" class="regular-text" />
                            <p class="description">※この設定はあなた専用です。他のユーザーには共有されません。</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gemini Model</th>
                        <td>
                            <select name="node_gemini_model" id="node_gemini_model">
                                <?php $current_model = get_user_meta( get_current_user_id(), 'node_gemini_model', true ) ?: 'gemini-3-flash-preview'; ?>
                                <option value="gemini-3.1-pro-preview" <?php selected( $current_model, 'gemini-3.1-pro-preview' ); ?>>Gemini 3.1 Pro Preview</option>
                                <option value="gemini-3-flash-preview" <?php selected( $current_model, 'gemini-3-flash-preview' ); ?>>Gemini 3 Flash Preview</option>
                                <option value="gemini-3.1-flash-lite" <?php selected( $current_model, 'gemini-3.1-flash-lite' ); ?>>Gemini 3.1 Flash-Lite</option>
                                <option value="gemini-2.5-pro" <?php selected( $current_model, 'gemini-2.5-pro' ); ?>>Gemini 2.5 Pro</option>
                                <option value="gemini-2.0-flash" <?php selected( $current_model, 'gemini-2.0-flash' ); ?>>Gemini 2.0 Flash</option>
                            </select>
                            <p class="description">AI要約機能などで使用するモデルを選択してください。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ライブラリ設定 -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-database"></span> ライブラリ設定 (Node Library)
                </h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">自動表示の有効化</th>
                        <td>
                            <?php $auto_insert = get_option( 'node_library_auto_insert', '1' ); ?>
                            <label>
                                <input type="checkbox" name="node_library_auto_insert" value="1" <?php checked( $auto_insert, '1' ); ?> />
                                ライターエリアの下に「ゲーム・アプリ情報」を自動で表示する
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">カード見出しの文言</th>
                        <td>
                            <input type="text" name="node_library_header_text" value="<?php echo esc_attr( get_option( 'node_library_header_text', 'GAME / APP INFO' ) ); ?>" class="regular-text" />
                            <p class="description">ライブラリカードの上部に表示される見出しです。</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">ボタンの補助テキスト</th>
                        <td>
                            <input type="text" name="node_library_button_text" value="<?php echo esc_attr( get_option( 'node_library_button_text', 'で見る' ) ); ?>" class="regular-text" />
                            <p class="description">プラットフォーム名の後に続く文言です。（例：「Steam で見る」の「で見る」部分）</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 商品リンク設定 -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-cart"></span> 商品リンク設定 (Amazon/楽天)
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Amazon アソシエイト ID</th>
                        <td>
                            <input type="text" name="luminous_nexus_amazon_id" value="<?php echo esc_attr( get_option( 'luminous_nexus_amazon_id' ) ); ?>" class="regular-text" placeholder="example-22" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Amazon 用注釈文言</th>
                        <td>
                            <input type="text" name="luminous_nexus_disclosure_amazon" value="<?php echo esc_attr( get_option( 'luminous_nexus_disclosure_amazon', 'Amazonのアソシエイトとして、当メディアは適格販売により収入を得ています。' ) ); ?>" class="large-text" />
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr></td></tr>
                    <tr>
                        <th scope="row">楽天 アフィリエイト ID</th>
                        <td>
                            <input type="text" name="luminous_nexus_rakuten_id" value="<?php echo esc_attr( get_option( 'luminous_nexus_rakuten_id' ) ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">楽天 用注釈文言</th>
                        <td>
                            <input type="text" name="luminous_nexus_disclosure_rakuten" value="<?php echo esc_attr( get_option( 'luminous_nexus_disclosure_rakuten', '当メディアは、楽天アフィリエイト・プログラムの参加者です。' ) ); ?>" class="large-text" />
                        </td>
                    </tr>
                </table>
            </div>

            <!-- X (Twitter) 連携設定 -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #1DA1F2; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-twitter"></span> X (Twitter) 連携設定
                </h2>
                <p class="description">記事の公開時に自動でポストするための設定です。X Developer Platform で Free 以上のプランが必要です。</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td><input type="text" name="node_x_api_key" value="<?php echo esc_attr( get_option( 'node_x_api_key' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">API Key Secret</th>
                        <td><input type="password" name="node_x_api_secret" value="<?php echo esc_attr( get_option( 'node_x_api_secret' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Access Token</th>
                        <td><input type="text" name="node_x_access_token" value="<?php echo esc_attr( get_option( 'node_x_access_token' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Access Token Secret</th>
                        <td><input type="password" name="node_x_access_token_secret" value="<?php echo esc_attr( get_option( 'node_x_access_token_secret' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">投稿テンプレート</th>
                        <td>
                            <textarea name="node_x_post_template" rows="6" class="large-text" placeholder="【新着記事】{{title}}&#10;&#10;{{summary}}&#10;&#10;続きはこちら： {{url}}&#10;#Node #{{category}}"><?php echo esc_textarea( get_option( 'node_x_post_template', "【新着記事】{{title}}\n\n{{summary}}\n\n続きはこちら： {{url}}\n#Node #{{category}}" ) ); ?></textarea>
                            <p class="description">
                                使用可能な変数: <code>{{title}}</code> (タイトル), <code>{{url}}</code> (URL), <code>{{summary}}</code> (AI要約/抜粋), <code>{{category}}</code> (カテゴリ名)<br>
                                ※AIを使わず、これらの変数を記事データに自動で置き換えて投稿します。
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- OGP自動生成設定 -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #6200EE; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-format-image"></span> OGP 自動生成設定
                </h2>
                <p class="description">記事タイトルを合成したOGP画像を自動で生成します（GDライブラリを使用）。</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">自動生成を有効にする</th>
                        <td>
                            <label>
                                <input type="checkbox" name="node_ogp_enabled" value="1" <?php checked( get_option( 'node_ogp_enabled' ), '1' ); ?> />
                                記事保存時にOGP画像を自動生成する
                            </label>
                            <p class="description">
                                ※背景デザインおよびロゴは「Luminous Core 公式仕様」としてシステムに固定されています。
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('すべての設定を保存'); ?>

            <script>
            jQuery(document).ready(function($){
                // wp.media が存在する場合のみ実行（JSエラー防止）
                if ( typeof wp !== 'undefined' && typeof wp.media !== 'undefined' ) {
                    var frame;
                    $('.node-media-upload').on('click', function(e) {
                        e.preventDefault();
                        var button = $(this);
                        var target = $(button.data('target'));
                        var preview = $(button.data('preview'));
                        
                        if (frame) { frame.open(); return; }
                        frame = wp.media({ title: '画像を選択', button: { text: 'これを使う' }, multiple: false });
                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            target.val(attachment.id);
                            preview.html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border-radius: 8px;">');
                        });
                        frame.open();
                    });
                    $('.node-media-clear').on('click', function() {
                        $($(this).data('target')).val('');
                        $($(this).data('preview')).empty();
                    });
                }
            });
            </script>
        </form>
    </div>
    <?php
}
