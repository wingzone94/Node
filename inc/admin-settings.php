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
            
            <!-- Gemini設定（管理者自身の個人キー。ライターは ユーザー → プロフィール で設定） -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-rest-api"></span> Gemini 設定（管理者・個人）
                </h2>
                <?php
                $current_user = wp_get_current_user();
                if ( function_exists( 'node_render_gemini_user_fields' ) ) {
                    node_render_gemini_user_fields( $current_user, true );
                }
                ?>
                <p class="description" style="margin-top:0;">
                    ライター（投稿権限のあるユーザー）は <strong>ユーザー → プロフィール</strong> から、各自の API キーを設定してください。
                </p>
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

            <!-- テーマのアップデート -->
            <div class="m3-admin-card" style="background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-update"></span> テーマのアップデート
                </h2>
                <p class="description">GitHub から最新の `node.zip` を取得して自動インストールします。</p>
                
                <div id="luminous-update-info" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p>現在のバージョン: <strong><?php echo wp_get_theme()->get('Version'); ?></strong></p>
                    <div id="update-check-result"></div>
                </div>

                <button type="button" id="luminous-check-update" class="button button-secondary">アップデートを確認</button>
                <button type="button" id="luminous-install-update" class="button button-primary" style="display:none;">最新版をインストール</button>
                
                <div id="update-progress-container" style="display:none; margin-top: 20px;">
                    <div style="width: 100%; background: #eee; border-radius: 10px; height: 10px; overflow: hidden;">
                        <div id="update-progress-bar" style="width: 0%; height: 100%; background: #FF9900; transition: width 0.3s ease;"></div>
                    </div>
                    <p id="update-status-text" style="margin-top: 10px; font-weight: bold;"></p>
                </div>
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

                // --- テーマアップデート機能 ---
                $('#luminous-check-update').on('click', function() {
                    var btn = $(this);
                    btn.prop('disabled', true).text('確認中...');
                    $('#update-check-result').html('');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'luminous_check_update',
                            nonce: '<?php echo wp_create_nonce("luminous_update_nonce"); ?>'
                        },
                        success: function(response) {
                            btn.prop('disabled', false).text('アップデートを確認');
                            if (response.success) {
                                var remote = response.data.remote_version;
                                var local = response.data.local_version;
                                if (response.data.update_available) {
                                    $('#update-check-result').html('<p style="color: #FF9900; font-weight: bold;">新しいバージョン (v' + remote + ') が見つかりました！</p>');
                                    $('#luminous-install-update').show();
                                } else {
                                    $('#update-check-result').html('<p style="color: #4CAF50;">最新バージョンです (v' + local + ')。</p>');
                                    $('#luminous-install-update').hide();
                                }
                            } else {
                                $('#update-check-result').html('<p style="color: #f44336;">エラー: ' + response.data + '</p>');
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false).text('アップデートを確認');
                            $('#update-check-result').html('<p style="color: #f44336;">ネットワークエラーが発生しました。</p>');
                        }
                    });
                });

                $('#luminous-install-update').on('click', function() {
                    if (!confirm('テーマを最新バージョンに更新しますか？\n(現在のファイルが上書きされます)')) return;

                    var btn = $(this);
                    btn.prop('disabled', true);
                    $('#luminous-check-update').hide();
                    $('#update-progress-container').show();
                    
                    updateStatus('最新版のダウンロードを開始しています...', 20);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'luminous_install_update',
                            nonce: '<?php echo wp_create_nonce("luminous_update_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                updateStatus('インストール完了！ページをリロードします...', 100);
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                updateStatus('エラー: ' + response.data, 0);
                                btn.prop('disabled', false);
                            }
                        },
                        error: function() {
                            updateStatus('インストール中に致命的なエラーが発生しました。', 0);
                            btn.prop('disabled', false);
                        }
                    });
                });

                function updateStatus(text, progress) {
                    $('#update-status-text').text(text);
                    $('#update-progress-bar').css('width', progress + '%');
                }
            });
            </script>
        </form>
    </div>
    <?php
}
