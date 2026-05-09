<?php
/**
 * Luminous Core Theme Functions
 *
 * このファイルは「ローダー + 最低限の初期化」に徹し、
 * 実際のロジックは inc/ ディレクトリに委譲します。
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -------------------------------------------------------
 * 1. 定数定義（テーマパス / バージョン）
 * -------------------------------------------------------
 */
define( 'NODE_THEME_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'NODE_THEME_DIR', get_template_directory() );
define( 'NODE_THEME_URI', get_template_directory_uri() );

/**
 * -------------------------------------------------------
 * 2. inc/ の読み込み（責務ごとに分離）
 * -------------------------------------------------------
 */
require_once NODE_THEME_DIR . '/inc/hooks.php';
require_once NODE_THEME_DIR . '/inc/theme-setup.php';
require_once NODE_THEME_DIR . '/inc/meta-boxes.php';
require_once NODE_THEME_DIR . '/inc/category-meta.php';
require_once NODE_THEME_DIR . '/inc/ajax.php';
require_once NODE_THEME_DIR . '/inc/spotlight.php';
require_once NODE_THEME_DIR . '/inc/ogp-generator.php';
require_once NODE_THEME_DIR . '/inc/media.php';
require_once NODE_THEME_DIR . '/inc/search.php';
require_once NODE_THEME_DIR . '/inc/utilities.php';
require_once NODE_THEME_DIR . '/inc/gemini-helper.php';
require_once NODE_THEME_DIR . '/inc/admin-settings.php';
require_once NODE_THEME_DIR . '/inc/seo.php';

/**
 * -------------------------------------------------------
 * 3. Vite アセット読み込み（CSS/JS）
 * -------------------------------------------------------
 */
function node_enqueue_assets() {

	$manifest_path = NODE_THEME_DIR . '/assets/.vite/manifest.json';

	if ( file_exists( $manifest_path ) ) {
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		// メイン JS
		if ( isset( $manifest['src/main.js']['file'] ) ) {
			wp_enqueue_script(
				'node-main-js',
				NODE_THEME_URI . '/assets/' . $manifest['src/main.js']['file'],
				array( 'node-gsap', 'node-gsap-scroll' ),
				NODE_THEME_VERSION,
				true
			);
			
			// AJAX用URLなどをJSに渡す
			wp_localize_script('node-main-js', 'm3_ajax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'home_url' => home_url('/')
			]);
		}

		// メイン CSS (main.js に紐づくもの)
		if ( isset( $manifest['src/main.js']['css'] ) ) {
			foreach ( $manifest['src/main.js']['css'] as $css_file ) {
				wp_enqueue_style(
					'node-main-css',
					NODE_THEME_URI . '/assets/' . $css_file,
					array(),
					NODE_THEME_VERSION
				);
			}
		}

		// メイン スタイルシート (src/styles/style.css)
		if ( isset( $manifest['src/styles/style.css']['file'] ) ) {
			wp_enqueue_style(
				'node-style-css',
				NODE_THEME_URI . '/assets/' . $manifest['src/styles/style.css']['file'],
				array(),
				NODE_THEME_VERSION
			);
		}
	}

	// Google Fonts & Material Symbols are now handled in header.php for performance.


	// GSAP (CDN)
	wp_register_script(
		'node-gsap',
		'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
		array(),
		'3.12.5',
		true
	);
	
	// ScrollToPlugin (CDN) - if needed by scrollTo logic in main.js
	wp_register_script(
		'node-gsap-scroll',
		'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js',
		array('node-gsap'),
		'3.12.5',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'node_enqueue_assets' );

/**
 * -------------------------------------------------------
 * 4. Service Worker の登録
 * -------------------------------------------------------
 */
function node_register_service_worker() {
	echo '<script>
		if ("serviceWorker" in navigator) {
			navigator.serviceWorker.register("' . esc_url( NODE_THEME_URI . '/sw.js' ) . '");
		}
	</script>';
}
add_action( 'wp_footer', 'node_register_service_worker' );
/**
 * -------------------------------------------------------
 * 5. Branding Normalization & DB Updates
 * -------------------------------------------------------
 */

/**
 * Force update DB options if they still contain old branding.
 */
function node_enforce_branding_update() {
    if ( ! is_admin() || (defined('REST_REQUEST') && REST_REQUEST) ) return;
    $current_name = get_option('blogname');
    if ($current_name === 'CyberNode' || $current_name === 'Node' || empty($current_name)) {
        update_option('blogname', 'Luminous Core');
    }
}
add_action('admin_init', 'node_enforce_branding_update');

/**
 * Filter frontend output to ensure branding consistency.
 */
function luminous_brand_normalize( $value ) {
    if ( is_string( $value ) ) {
        return str_replace( array( 'CyberNode', 'Node' ), 'Luminous Core', $value );
    }
    return $value;
}
add_filter( 'option_blogname', 'luminous_brand_normalize' );
add_filter( 'option_blogdescription', 'luminous_brand_normalize' );
add_filter( 'pre_get_document_title', 'luminous_brand_normalize', 999 );

/**
 * Handle translation strings and admin branding.
 */
add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( strpos( $translated, 'CyberNode' ) !== false || strpos( $translated, 'Node' ) !== false ) {
        $translated = str_replace( array( 'CyberNode', 'Node' ), 'Luminous Core', $translated );
    }
    return $translated;
}, 20, 3 );
