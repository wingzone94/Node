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
    add_options_page(
        'Luminous Theme Settings',
        'Luminous Settings',
        'manage_options',
        'luminous-settings',
        'node_render_settings_page'
    );
}
add_action( 'admin_menu', 'node_add_admin_menu' );

/**
 * Register Settings
 */
function node_register_settings() {
    register_setting( 'node_settings_group', 'node_gemini_api_key' );
    register_setting( 'node_settings_group', 'node_gemini_model' );
}
add_action( 'admin_init', 'node_register_settings' );

/**
 * Render Settings Page
 */
function node_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Luminous Core テーマ設定</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'node_settings_group' );
            do_settings_sections( 'node_settings_group' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="password" name="node_gemini_api_key" value="<?php echo esc_attr( get_option( 'node_gemini_api_key' ) ); ?>" class="regular-text" />
                        <p class="description">
                            Intelligence Summary（AI要約）機能に使用するGoogle Gemini APIキーを入力してください。<br>
                            キーは <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a> から取得できます。
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Gemini Model</th>
                    <td>
                        <input type="text" name="node_gemini_model" value="<?php echo esc_attr( get_option( 'node_gemini_model', 'gemini-3-flash-preview' ) ); ?>" class="regular-text" />
                        <p class="description">
                            使用するモデル名を入力してください（例: <code>gemini-3.1-pro-preview</code>, <code>gemini-3-flash-preview</code> など）。<br>
                            最新のモデル名は <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener">公式ドキュメント</a> を参照してください。
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
