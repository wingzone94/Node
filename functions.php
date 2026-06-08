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
define( 'NODE_ALL_ARTICLES_SLUG', 'all-articles' );
define( 'NODE_ALL_ARTICLES_PER_PAGE', 24 );
define( 'NODE_ALL_ARTICLES_TOTAL_LIMIT', 240 );

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
require_once NODE_THEME_DIR . '/inc/archive-helpers.php';
require_once NODE_THEME_DIR . '/inc/media.php';
require_once NODE_THEME_DIR . '/inc/search.php';
require_once NODE_THEME_DIR . '/inc/utilities.php';
require_once NODE_THEME_DIR . '/inc/gemini-helper.php';
require_once NODE_THEME_DIR . '/inc/gemini-models.php';
require_once NODE_THEME_DIR . '/inc/gemini-user-settings.php';
require_once NODE_THEME_DIR . '/inc/admin-settings.php';
require_once NODE_THEME_DIR . '/inc/seo.php';
require_once NODE_THEME_DIR . '/inc/scheduler.php';
require_once NODE_THEME_DIR . '/inc/ogp-generator.php';
require_once NODE_THEME_DIR . '/inc/toc-engine.php';
require_once NODE_THEME_DIR . '/inc/blogcard.php';

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

	if ( function_exists( $init_func ) ) {
		continue;
	}
	
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
	$asset_version = $manifest[ $key ]['file'] ?? (string) time();

	wp_register_script(
		$handle,
		NODE_THEME_URI . '/assets/' . $file,
		array(),
		$asset_version,
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
							'all_articles_url' => node_get_all_articles_url(),
						)
					);
				}
			}

		// メイン CSS (main.js に紐づくもの)
		if ( isset( $manifest['src/main.js']['css'] ) ) {
			foreach ( $manifest['src/main.js']['css'] as $css_file ) {
				$css_path    = NODE_THEME_DIR . '/assets/' . $css_file;
				$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : NODE_THEME_VERSION;

				wp_enqueue_style(
					'node-main-css',
					NODE_THEME_URI . '/assets/' . $css_file,
					array(),
					$css_version
				);
			}
		}
		
		// メイン スタイルシート (src/styles/style.css)
		if ( isset( $manifest['src/styles/style.css']['file'] ) ) {
			$style_file    = $manifest['src/styles/style.css']['file'];
			$style_path    = NODE_THEME_DIR . '/assets/' . $style_file;
			$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : NODE_THEME_VERSION;

			wp_enqueue_style(
				'node-style-css',
				NODE_THEME_URI . '/assets/' . $style_file,
				array(),
				$style_version
			);
		}
	}

	// Google Fonts & Material Symbols are now handled in header.php for performance.
	luminous_enqueue_plugin_scripts();
}
add_action( 'wp_enqueue_scripts', 'node_enqueue_assets' );

/**
 * 全記事一覧ページ（上限付き）のURLを返す。
 */
function node_get_all_articles_url() {
	return home_url( '/' . trim( NODE_ALL_ARTICLES_SLUG, '/' ) . '/' );
}

/**
 * ヘッドライン一覧ページのURLを返す。
 */
function node_get_headlines_url() {
	return home_url( '/headlines/' );
}

/**
 * 全記事一覧専用のリライトルールを登録する。
 */
function node_register_all_articles_rewrite_rule() {
	add_rewrite_tag( '%node_all_articles%', '1' );
	add_rewrite_rule(
		'^' . preg_quote( NODE_ALL_ARTICLES_SLUG, '/' ) . '/?$',
		'index.php?node_all_articles=1',
		'top'
	);
	add_rewrite_rule(
		'^' . preg_quote( NODE_ALL_ARTICLES_SLUG, '/' ) . '/page/([0-9]{1,})/?$',
		'index.php?node_all_articles=1&paged=$matches[1]',
		'top'
	);

	// ヘッドライン専用のリライトルール
	add_rewrite_tag( '%node_headlines%', '1' );
	add_rewrite_rule(
		'^headlines/?$',
		'index.php?node_headlines=1',
		'top'
	);
	add_rewrite_rule(
		'^headlines/page/([0-9]{1,})/?$',
		'index.php?node_headlines=1&paged=$matches[1]',
		'top'
	);
}
add_action( 'init', 'node_register_all_articles_rewrite_rule' );

/**
 * クエリ変数を明示的に公開する。
 */
function node_add_all_articles_query_var( $vars ) {
	$vars[] = 'node_all_articles';
	$vars[] = 'node_headlines';
	return $vars;
}
add_filter( 'query_vars', 'node_add_all_articles_query_var' );

/**
 * 専用一覧テンプレートに差し替える。
 */
function node_use_all_articles_template( $template ) {
	if ( get_query_var( 'node_all_articles' ) ) {
		$custom_template = NODE_THEME_DIR . '/template-parts/all-articles.php';
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}

	if ( get_query_var( 'node_headlines' ) ) {
		$custom_template = NODE_THEME_DIR . '/template-parts/headlines.php';
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'node_use_all_articles_template', 99 );

/**
 * リライトルールを一度だけフラッシュする（本番運用向け）。
 */
function node_maybe_flush_rewrite_rules_for_all_articles() {
	$rewrite_version = 'node_all_articles_v2';
	if ( get_option( 'node_rewrite_rules_version' ) === $rewrite_version ) {
		return;
	}

	if ( wp_installing() ) {
		return;
	}

	node_register_all_articles_rewrite_rule();
	flush_rewrite_rules( false );
	update_option( 'node_rewrite_rules_version', $rewrite_version );
}
add_action( 'after_switch_theme', 'node_maybe_flush_rewrite_rules_for_all_articles' );
add_action( 'init', 'node_maybe_flush_rewrite_rules_for_all_articles', 20 );

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
				.then(() => {})
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
        $trimmed_value = trim( $value );
        if ( in_array( $trimmed_value, array( 'CyberNode', 'Node' ), true ) ) {
            return 'Luminous Core';
        }

        return str_replace( 'CyberNode', 'Luminous Core', $value );
    }
    return $value;
}
add_filter( 'option_blogname', 'luminous_brand_normalize' );
add_filter( 'option_blogdescription', 'luminous_brand_normalize' );
add_filter( 'pre_get_document_title', 'luminous_brand_normalize', 999 );

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

/**
 * -------------------------------------------------------
 * 11. 投稿保存時のデフォルトステータス制御
 * -------------------------------------------------------
 * 新規投稿や下書き系からの保存時は「レビュー待ち」に固定する。
 * 公開済み投稿の更新には影響を与えない。
 */
function node_is_auto_draft_placeholder_title( $title ) {
    $normalized_title = trim( wp_strip_all_tags( (string) $title ) );
    return in_array( $normalized_title, array( 'Auto Draft', '自動下書き' ), true );
}

function node_force_default_post_status_on_save( $data, $postarr ) {
    if ( ! isset( $data['post_type'] ) || 'post' !== $data['post_type'] ) {
        return $data;
    }

    if ( isset( $data['post_title'] ) && node_is_auto_draft_placeholder_title( $data['post_title'] ) ) {
        $data['post_title'] = '';
    }

    $incoming_status = $data['post_status'] ?? '';
    if ( 'auto-draft' === $incoming_status ) {
        return $data;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $data;
    }

    if ( isset( $postarr['ID'] ) && wp_is_post_revision( (int) $postarr['ID'] ) ) {
        return $data;
    }

    // 新規作成時や下書き保存時のみ「レビュー待ち（pending）」に強制する
    // publish（公開）等のその他のステータス変更には干渉せず、WordPressコアの権限チェックに委ねる
    if ( in_array( $incoming_status, array( '', 'draft' ), true ) ) {
        $data['post_status'] = 'pending';
    }

    return $data;
}
add_filter( 'wp_insert_post_data', 'node_force_default_post_status_on_save', 99, 2 );

/**
 * RSS GUID を常に正規パーマリンクにそろえる。
 * これにより localhost / staging / 一時ドメイン由来の GUID 残存を防ぐ。
 */
function node_normalize_feed_guid( $guid, $post_id ) {
    if ( ! is_feed() ) {
        return $guid;
    }

    $post_id = absint( $post_id );
    if ( $post_id <= 0 ) {
        return $guid;
    }

    $permalink = get_permalink( $post_id );
    if ( ! $permalink ) {
        return $guid;
    }

    return $permalink;
}
add_filter( 'the_guid', 'node_normalize_feed_guid', 10, 2 );

/**
 * フッターメニュー内に残っている仮URLを正規URLへ補正する。
 */
function node_fix_footer_menu_placeholder_urls( $items, $args ) {
    if ( empty( $args->theme_location ) || 'footer' !== $args->theme_location ) {
        return $items;
    }

    foreach ( $items as $item ) {
        if ( ! isset( $item->url ) ) {
            continue;
        }

        if ( false !== strpos( $item->url, '/sample-page-2/' ) ) {
            $item->url = home_url( '/privacy-policy/' );
            continue;
        }

        if ( false !== strpos( $item->url, '/post-0-2/' ) ) {
            $item->url = home_url( '/contact/' );
        }
    }

    return $items;
}
add_filter( 'wp_nav_menu_objects', 'node_fix_footer_menu_placeholder_urls', 10, 2 );

/**
 * Aboutページの公開文面を正規表記へ補正する。
 * ※ 固定ページ本文が管理画面で未更新でも、公開画面では適切な文面を表示する。
 */
function node_normalize_about_page_content( $content ) {
    if ( is_admin() || ! is_page() ) {
        return $content;
    }

    $about_page = get_page_by_path( 'about' );
    if ( ! $about_page || get_queried_object_id() !== (int) $about_page->ID ) {
        return $content;
    }

    $keep_first_paragraph = static function( string $html, string $text ): string {
        $pattern = '#<p>\s*' . preg_quote( $text, '#' ) . '\s*</p>#u';
        $seen    = 0;
        return (string) preg_replace_callback(
            $pattern,
            static function( $matches ) use ( &$seen ) {
                $seen++;
                return 1 === $seen ? $matches[0] : '';
            },
            $html
        );
    };

    $duplicate_targets = array(
        'Luminous Coreは、ガジェット、ゲーム、Webサービス、AIに関する最新情報や商品レビューなどをお伝えするブログメディアです。',
        'ブログのロゴはガジェット、ゲーム、AI・Webサービスを構成する３つの光が交差し、その交点を表す部分を切り取り、作成したものです。',
        'ブログ名のLuminous Coreはこれらを構成するイメージカラーから着想を経て、命名したものであり、赤が「ゲーム」、青が「ガジェット」、緑が「Webサービス・AI」を司ることを意味しています。',
    );
    foreach ( $duplicate_targets as $target ) {
        $content = $keep_first_paragraph( $content, $target );
    }

    $replacements = array(
        'wingzone94を中心とする複数のメンバーで複製され、日々、記事制作に取り組んでいます。'
            => 'wingzone94を中心とする複数のメンバーで構成され、日々、記事制作に取り組んでいます。',
        '<strong>2026年5月下旬</strong>：正式サービス開始（予定）Luminous Coreは、ガジェット、ゲーム、Webサービス、AIに関する最新情報や商品レビューなどをお伝えするブログメディアです。'
            => '<strong>2026年5月下旬</strong>：正式サービス開始（予定）。Luminous Coreは、ガジェット、ゲーム、Webサービス、AIに関する最新情報や商品レビューなどをお伝えするブログメディアです。',
        '略称（才能）の響きが自分たちのスタイルに対して少し主張が強すぎると感じたため、'
            => '旧ブログ名の略称の響きが自分たちのスタイルに対して少し主張が強すぎると感じたため、',
        'Server：Conoha Wing'
            => 'Server: Conoha Wing',
        'Server：'
            => 'Server: ',
    );
    $content = str_replace( array_keys( $replacements ), array_values( $replacements ), $content );

    $content = (string) preg_replace(
        '#<td>\s*X(?:\s*<br\s*/?>\s*Threads)?\s*</td>#u',
        '<td><a href="https://x.com/LuminousCoreJP" target="_blank" rel="noopener noreferrer">X</a></td>',
        $content
    );

    // 「Threads」を案内文や運営情報表から完全に除去する。
    $content = str_replace(
        array( '公式SNS：X / Threads', '公式SNS: X / Threads', 'X / Threads', 'Threads' ),
        array( '公式SNS：X', '公式SNS: X', 'X', '' ),
        $content
    );

    return $content;
}
add_filter( 'the_content', 'node_normalize_about_page_content', 30 );

/**
 * Disable default inline HTML margin injection by the WordPress Admin Bar
 */
add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );

/**
 * -------------------------------------------------------
 * 12. 記事表示数の制御 (トップ/アーカイブ)
 * -------------------------------------------------------
 */
function node_custom_posts_per_page( $query ) {
    if ( ! is_admin() && $query->is_main_query() ) {
        if ( is_home() || is_front_page() ) {
            $query->set( 'posts_per_page', 12 );
        } elseif ( is_archive() || is_search() || $query->is_paged() ) {
            $query->set( 'posts_per_page', 24 );
        }
    }
}
add_action( 'pre_get_posts', 'node_custom_posts_per_page' );

/**
 * -------------------------------------------------------
 * 13. HEADLINE専用ページのメインクエリ制御
 * -------------------------------------------------------
 */
function node_headlines_pre_get_posts( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->get( 'node_headlines' ) ) {
        $news_cat = get_term_by( 'name', 'ニュース', 'category' );
        if ( $news_cat ) {
            $query->set( 'cat', $news_cat->term_id );
        }
        $query->set( 'posts_per_page', 24 );
    }
}
add_action( 'pre_get_posts', 'node_headlines_pre_get_posts' );
