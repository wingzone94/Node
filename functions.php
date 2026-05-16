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
require_once NODE_THEME_DIR . '/inc/media.php';
require_once NODE_THEME_DIR . '/inc/search.php';
require_once NODE_THEME_DIR . '/inc/utilities.php';
require_once NODE_THEME_DIR . '/inc/gemini-helper.php';
require_once NODE_THEME_DIR . '/inc/admin-settings.php';
require_once NODE_THEME_DIR . '/inc/seo.php';
require_once NODE_THEME_DIR . '/inc/scheduler.php';
require_once NODE_THEME_DIR . '/inc/ogp-generator.php';
require_once NODE_THEME_DIR . '/inc/toc-engine.php';

/**
 * -------------------------------------------------------
 * 2.5 埋め込みプラグインの読み込み (Plugins Embedded)
 * -------------------------------------------------------
 */
$embedded_plugins = [
	'node-signal/node-signal.php'           => 'node_signal_init',
	'luminous-blocks/luminous-blocks.php'   => 'luminous_blocks_init',
	'node-ai-tools/node-ai-tools.php'       => 'node_ai_core_init',
	'node-flow/node-flow.php'               => 'node_flow_init',
	'luminous-nexus/luminous-nexus.php'     => 'luminous_nexus_init',
	'luminous-interactivity/luminous-interactivity.php' => 'luminous_interactivity_init',
	'node-library/node-library.php'         => 'node_library_init',
];

foreach ( $embedded_plugins as $plugin_file => $init_func ) {
	$path = NODE_THEME_DIR . '/plugins-embedded/' . $plugin_file;
	
	if ( file_exists( $path ) ) {
		require_once $path;
		// 読み込んだ直後に初期化関数を直接実行（plugins_loaded フックを待たずに確実に起動）
		if ( function_exists( $init_func ) ) {
			$init_func();
		}
	}
}

/**
 * Vite manifest の import チェーンを依存順で登録（ES module）。
 *
 * @param array<string, mixed> $manifest
 * @param string               $key
 * @param array<string, bool>  $seen
 * @return string[] Script handles in load order.
 */
function node_register_vite_chain( array $manifest, string $key, array &$seen = array() ): array {
	if ( ! isset( $manifest[ $key ] ) || isset( $seen[ $key ] ) ) {
		return array();
	}

	$handles = array();

	if ( ! empty( $manifest[ $key ]['imports'] ) && is_array( $manifest[ $key ]['imports'] ) ) {
		foreach ( $manifest[ $key ]['imports'] as $import_key ) {
			$handles = array_merge( $handles, node_register_vite_chain( $manifest, $import_key, $seen ) );
		}
	}

	$slug   = sanitize_title( str_replace( array( '/', '_', '.' ), '-', $key ) );
	$handle = 'node-vite-' . $slug;
	$file   = $manifest[ $key ]['file'];
	$path   = NODE_THEME_DIR . '/assets/' . $file;

	wp_register_script(
		$handle,
		NODE_THEME_URI . '/assets/' . $file,
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : NODE_THEME_VERSION,
		true
	);
	wp_script_add_data( $handle, 'type', 'module' );

	$seen[ $key ]     = true;
	$handles[]        = $handle;

	return $handles;
}

/**
 * -------------------------------------------------------
 * 3. Vite アセット読み込み（CSS/JS）
 * -------------------------------------------------------
 */
function node_enqueue_assets() {

	$manifest_path = NODE_THEME_DIR . '/assets/.vite/manifest.json';

	if ( file_exists( $manifest_path ) ) {
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		// メイン JS（vendor チャンク → main の順、type=module）
		if ( isset( $manifest['src/main.js']['file'] ) ) {
			$seen    = array();
			$handles = node_register_vite_chain( $manifest, 'src/main.js', $seen );

			foreach ( $handles as $handle ) {
				wp_enqueue_script( $handle );
			}

			$main_handle = end( $handles );
			if ( $main_handle ) {
				wp_localize_script(
					$main_handle,
					'm3_ajax',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'home_url' => home_url( '/' ),
					)
				);
			}
		}

		// メイン CSS (main.js に紐づくもの)
		if ( isset( $manifest['src/main.js']['css'] ) ) {
			foreach ( $manifest['src/main.js']['css'] as $css_file ) {
				wp_enqueue_style(
					'node-main-css',
					NODE_THEME_URI . '/assets/' . $css_file,
					array(),
					time() // Force refresh
				);
			}
		}
		
		// メイン スタイルシート (src/styles/style.css)
		if ( isset( $manifest['src/styles/style.css']['file'] ) ) {
			wp_enqueue_style(
				'node-style-css',
				NODE_THEME_URI . '/assets/' . $manifest['src/styles/style.css']['file'],
				array(),
				time() // Force refresh
			);
		}
	}

	// Google Fonts & Material Symbols are now handled in header.php for performance.
}
add_action( 'wp_enqueue_scripts', 'node_enqueue_assets' );

/**
 * -------------------------------------------------------
 * 4. Service Worker の登録 (重複排除・最適化)
 * -------------------------------------------------------
 */
function node_register_service_worker() {
    // header.phpではなく、安全にフッターで遅延登録する
	echo '<script>
		window.addEventListener("load", () => {
			if ("serviceWorker" in navigator) {
				navigator.serviceWorker.register("' . esc_url( NODE_THEME_URI . '/sw.js' ) . '")
				.then(reg => console.log("Luminous Core SW registered"))
				.catch(err => console.error("SW registration failed: ", err));
			}
		});
	</script>';
}
add_action( 'wp_footer', 'node_register_service_worker' );

/**
 * -------------------------------------------------------
 * 5. Branding Normalization & DB Updates
 * -------------------------------------------------------
 */
function node_enforce_branding_update() {
    if ( ! is_admin() || (defined('REST_REQUEST') && REST_REQUEST) ) return;
    $current_name = get_option('blogname');
    if ($current_name === 'CyberNode' || $current_name === 'Node' || empty($current_name)) {
        update_option('blogname', 'Luminous Core');
    }
}
add_action('admin_init', 'node_enforce_branding_update');

function luminous_brand_normalize( $value ) {
    if ( is_string( $value ) ) {
        return str_replace( array( 'CyberNode', 'Node' ), 'Luminous Core', $value );
    }
    return $value;
}
add_filter( 'option_blogname', 'luminous_brand_normalize' );
add_filter( 'option_blogdescription', 'luminous_brand_normalize' );
add_filter( 'pre_get_document_title', 'luminous_brand_normalize', 999 );

add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( strpos( $translated, 'CyberNode' ) !== false || strpos( $translated, 'Node' ) !== false ) {
        $translated = str_replace( array( 'CyberNode', 'Node' ), 'Luminous Core', $translated );
    }
    return $translated;
}, 20, 3 );

/**
 * -------------------------------------------------------
 * 6. Slug Sanitization (日本語スラッグ自動回避ロジック)
 * 本番環境でのSEO・シェア時のURL文字化けを防ぎます。
 * -------------------------------------------------------
 */
function luminous_core_auto_post_slug( $slug, $post_ID, $post_status, $post_type ) {
    // カスタム投稿タイプなどを含め、日本語（URLエンコードされる文字）が含まれているかを判定
    if ( preg_match( '/(%[0-9a-f]{2})+/i', $slug ) || preg_match( '/[^a-z0-9\-]/i', $slug ) ) {
        // 日本語が含まれている場合、一律で「post-投稿ID」の形式に書き換える
        $slug = 'post-' . $post_ID;
    }
    return $slug;
}
add_filter( 'wp_unique_post_slug', 'luminous_core_auto_post_slug', 10, 4 );

/**
 * -------------------------------------------------------
 * 7. Font Preconnect (存在しないフォントファイルのプリロードは削除)
 * -------------------------------------------------------
 */
function node_preload_webfonts() {
    // Google Fonts preconnect のみ（ローカルフォントファイルは存在しないため削除）
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
add_action( 'wp_head', 'node_preload_webfonts', 1 );

/**
 * -------------------------------------------------------
 * 7.5. body 非表示フォールバック（JSが失敗した場合の保険）
 *
 * style.css の `body { opacity: 0; visibility: hidden }` は
 * JS が `body.is-loaded` を付与することで解除される設計だが、
 * CDN の GSAP 読み込み失敗などで JS がエラーになった場合に
 * ページが真っ白のままになる。
 * noscript タグと JS フォールバックで確実に表示させる。
 * -------------------------------------------------------
 */
function node_body_visibility_fallback() {
    echo '<noscript><style>body { opacity: 1 !important; visibility: visible !important; }</style></noscript>' . "\n";
    // JS が遅延しても最大2秒後には強制表示するフォールバック
    echo '<script>
        (function() {
            var timeout = setTimeout(function() {
                document.body.classList.add("is-loaded");
            }, 2000);
            document.addEventListener("DOMContentLoaded", function() {
                clearTimeout(timeout);
                // main.js が is-loaded を付与するが、失敗した場合のフォールバック
                setTimeout(function() {
                    if (!document.body.classList.contains("is-loaded")) {
                        document.body.classList.add("is-loaded");
                    }
                }, 500);
            });
        })();
    </script>' . "\n";
}
add_action( 'wp_head', 'node_body_visibility_fallback', 2 );

/**
 * -------------------------------------------------------
 * 8. Script Async & Module Loading (TBT Optimization)
 * -------------------------------------------------------
 */
function node_script_loader_tag($tag, $handle, $src) {
    // Vite handles: node-vite-*
    // Force module script so browser can parse `import/export` bundles.
    if (strpos($handle, 'node-vite-') === 0) {
        if (strpos($tag, 'type="module"') === false) {
            $tag = str_replace('<script ', '<script type="module" crossorigin ', $tag);
        }
    }
    return $tag;
}
add_filter('script_loader_tag', 'node_script_loader_tag', 10, 3);

/**
 * -------------------------------------------------------
 * 9. Payload Cleanup (Remove emojis, global-styles, etc.)
 * -------------------------------------------------------
 */
require_once NODE_THEME_DIR . '/inc/cleanup.php';

/**
 * -------------------------------------------------------
 * 10. カスタムライター情報（追加リンク枠最大5つ）
 * -------------------------------------------------------
 */
function node_user_contact_methods( $methods ) {
    $methods['custom_link_1'] = '追加リンク 1 (URL)';
    $methods['custom_link_2'] = '追加リンク 2 (URL)';
    $methods['custom_link_3'] = '追加リンク 3 (URL)';
    $methods['custom_link_4'] = '追加リンク 4 (URL)';
    $methods['custom_link_5'] = '追加リンク 5 (URL)';
    return $methods;
}
add_filter( 'user_contactmethods', 'node_user_contact_methods' );
/**
 * -------------------------------------------------------
 * 6. FOUC対策の修正 — オレンジフラッシュ除去 & PageSpeed最適化
 * -------------------------------------------------------
 * 旧実装: html bg=#f90 + body opacity:0 → JS で is-loaded 付与
 * 問題点: JS実行までオレンジ色しか表示されず、LCP を著しく遅延させていた
 * 新実装: html/body を最初から表示。アニメーション演出は is-loaded で行う（任意）
 */
function node_critical_inline_styles() {
    // フロントエンド: 最優先でレンダリングブロックを解除
    if ( ! is_admin() ) {
        echo '<style id="node-critical-fouc-fix">
            html {
                background-color: #FFF4E5 !important;
            }
            html[data-theme="dark"],
            body[data-theme="dark"] ~ html,
            [data-theme="dark"] {
                background-color: #1B1812 !important;
            }
            body {
                opacity: 1 !important;
                visibility: visible !important;
            }
        </style>';
    }
}
add_action( 'wp_head', 'node_critical_inline_styles', 1 );

/**
 * 管理画面 / エディタ用の表示保護
 */
function node_fix_admin_visibility() {
    echo '<style>
        body.wp-admin {
            opacity: 1 !important;
            visibility: visible !important;
            background-color: #f1f1f1 !important;
        }
        .editor-styles-wrapper {
            opacity: 1 !important;
            visibility: visible !important;
            background-color: #ffffff !important;
        }
        html.wp-toolbar {
            background-color: #f1f1f1 !important;
        }
    </style>';
}
add_action( 'admin_head', 'node_fix_admin_visibility' );
add_action( 'enqueue_block_editor_assets', 'node_fix_admin_visibility' );
