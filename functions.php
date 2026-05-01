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

	// Google Fonts & Material Symbols
	wp_enqueue_style(
		'node-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
		array(),
		null
	);

	// Font Awesome (Brands) for Social Icons
	wp_enqueue_style(
		'font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);

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