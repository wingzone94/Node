<?php
/**
 * Luminous Nexus 設定画面
 * 
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Luminous_Nexus_Settings {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_settings_menu(): void {
		// テーマ側の Luminous Settings に統合されたため、ここではメニューを追加しません
		return;
	}

	public function register_settings(): void {
		register_setting( 'luminous_nexus_options', 'luminous_nexus_amazon_id' );
		register_setting( 'luminous_nexus_options', 'luminous_nexus_rakuten_id' );
		register_setting( 'luminous_nexus_options', 'luminous_nexus_disclosure_text' );
	}

	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1>Luminous Nexus 設定</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'luminous_nexus_options' );
				do_settings_sections( 'luminous_nexus_options' );
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Amazon アソシエイト ID</th>
						<td>
							<input type="text" name="luminous_nexus_amazon_id" value="<?php echo esc_attr( get_option( 'luminous_nexus_amazon_id' ) ); ?>" class="regular-text" placeholder="example-22" />
							<p class="description">Amazonの商品リンクに自動的に付与されるトラッキングIDです。</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">楽天 アフィリエイト ID</th>
						<td>
							<input type="text" name="luminous_nexus_rakuten_id" value="<?php echo esc_attr( get_option( 'luminous_nexus_rakuten_id' ) ); ?>" class="regular-text" />
							<p class="description">楽天の商品リンクに使用するアフィリエイトIDです。</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">広告宣伝の表記 (Disclosure)</th>
						<td>
							<textarea name="luminous_nexus_disclosure_text" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'luminous_nexus_disclosure_text', '本ページはプロモーションが含まれています' ) ); ?></textarea>
							<p class="description">商品カードの下部に表示される広告表記テキストです。</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

new Luminous_Nexus_Settings();
