<?php
/**
 * Plugin Name:  Luminous Nexus
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  ゲーム・アプリ情報の管理、商品カード、ブログカード（OGP 取得）。Luminous Core テーマと連携。
 * Version:      1.0.0
 * Author:       Luminous Core Teams
 * Author URI:   https://github.com/wingzone94
 * License:      MIT
 * Text Domain:  luminous-nexus
 * Requires PHP: 8.0
 *
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUMINOUS_NEXUS_VERSION', '1.0.0' );
define( 'LUMINOUS_NEXUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMINOUS_NEXUS_URL', plugin_dir_url( __FILE__ ) );

/**
 * プラグイン初期化
 */
final class Luminous_Nexus {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		require_once LUMINOUS_NEXUS_DIR . 'includes/shortcode-blogcard.php';
		require_once LUMINOUS_NEXUS_DIR . 'includes/shortcode-product.php';

		if ( is_admin() ) {
			require_once LUMINOUS_NEXUS_DIR . 'includes/meta-box-game-info.php';
		}
	}

	private function register_hooks(): void {
		// テーマの Hook に応答: 記事ヘッダー直後に Nexus カードを挿入
		add_action( 'luminous_after_article_header', [ $this, 'render_nexus_card' ] );

		// ショートコード登録
		add_shortcode( 'blogcard', 'luminous_nexus_blogcard_shortcode' );
		add_shortcode( 'product_card', 'luminous_nexus_product_card_shortcode' );
		add_shortcode( 'm3_product', 'luminous_nexus_product_card_shortcode' );

		// URL の自動ブログカード変換
		add_filter( 'the_content', [ $this, 'auto_blogcard' ], 11 );

		// Amazon oEmbed の無効化
		add_filter( 'oembed_providers', [ $this, 'disable_amazon_oembed' ] );
	}

	public function render_nexus_card( int $post_id ): void {
		$game_info = get_post_meta( $post_id, '_node_game_info', true );
		if ( is_array( $game_info ) && ! empty( $game_info['title'] ) ) {
			$info = $game_info;
			include LUMINOUS_NEXUS_DIR . 'templates/card-nexus.php';
		}
	}

	public function auto_blogcard( string $content ): string {
		$pattern = '/^(<p>)?(https?:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)(<\/p>)?$/im';
		return preg_replace_callback( $pattern, function ( $matches ) {
			return luminous_nexus_blogcard_shortcode( [ 'url' => $matches[2] ] );
		}, $content );
	}

	public function disable_amazon_oembed( array $providers ): array {
		foreach ( $providers as $url => $provider ) {
			if ( strpos( $url, 'amazon' ) !== false ) {
				unset( $providers[ $url ] );
			}
		}
		return $providers;
	}
}

/**
 * プラグイン起動
 */
function luminous_nexus_init(): void {
	Luminous_Nexus::instance();
}
add_action( 'plugins_loaded', 'luminous_nexus_init' );
