<?php
/**
 * Plugin Name:  Node Library
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  ゲーム・アプリ情報の管理と表示。カスタム投稿タイプによるリスト管理と、記事への紐付け機能を提供。
 * Version:      1.3.2
 * Author:       Luminous Core Teams
 * License:      MIT
 * Text Domain:  node-library
 *
 * @package Node_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NODE_LIBRARY_VERSION', '1.3.2' );
define( 'NODE_LIBRARY_DIR', plugin_dir_path( __FILE__ ) );

$node_library_embedded_dir = get_template_directory() . '/plugins-embedded/node-library/';
define(
	'NODE_LIBRARY_URL',
	is_dir( $node_library_embedded_dir )
		? get_template_directory_uri() . '/plugins-embedded/node-library/'
		: content_url( '/plugins/node-library/' )
);

/**
 * Node Library Main Class
 */
final class Node_Library {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_boxes' ] );
		
		// テーマのフックに応答（タグの下に表示）
		add_action( 'luminous_after_tags', [ $this, 'render_library_card_on_post' ] );

		// 管理画面用アセット
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Gutenberg ブロックの登録
		add_action( 'init', [ $this, 'register_gutenberg_block' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_assets' ] );

		// REST API Endpoint 登録
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * REST API Routes
	 */
	public function register_rest_routes(): void {
		register_rest_route( 'node-library/v1', '/fetch-ogp', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_fetch_ogp' ],
			'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
			'args'                => [
				'url' => [ 'required' => true, 'sanitize_callback' => 'esc_url_raw' ],
			],
		] );

		register_rest_route( 'node-library/v1', '/items', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list_items' ],
			'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		] );

		register_rest_route( 'node-library/v1', '/generate-game-info', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_generate_game_info' ],
			'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
			'args'                => [
				'title' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'type' => [
					'default'           => 'game',
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );
	}

	/**
	 * ライブラリ項目一覧（ブロックエディタ用）
	 */
	public function handle_list_items( $request ) {
		$posts = get_posts(
			array(
				'post_type'      => 'node_library',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$items = array_map(
			static function ( $post ) {
				$summary = (string) get_post_meta( $post->ID, '_node_library_summary', true );
				$links   = get_post_meta( $post->ID, '_node_library_links', true );
				$link_count = is_array( $links ) ? count( $links ) : 0;

				return array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'summary'    => $summary,
					'link_count' => $link_count,
				);
			},
			$posts
		);

		return rest_ensure_response( $items );
	}

	/**
	 * Gemini API からゲーム・アプリ紹介文の下書きを取得する。
	 */
	public function handle_generate_game_info( $request ) {
		$title = trim( (string) $request->get_param( 'title' ) );
		$type  = 'app' === $request->get_param( 'type' ) ? 'app' : 'game';
		if ( '' === $title ) {
			return new WP_Error( 'missing_title', 'ゲームまたはアプリのタイトルを入力してください。', [ 'status' => 400 ] );
		}

		if ( ! function_exists( 'node_get_user_gemini_api_key' ) || ! function_exists( 'node_get_user_gemini_model' ) || ! function_exists( 'node_is_valid_gemini_model_id' ) ) {
			return new WP_Error( 'gemini_unavailable', 'Gemini API 設定を読み込めませんでした。', [ 'status' => 500 ] );
		}

		$api_key = node_get_user_gemini_api_key();
		$model   = node_get_user_gemini_model();

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', 'ユーザープロフィールで Gemini API キーを設定してください。', [ 'status' => 400 ] );
		}

		if ( ! node_is_valid_gemini_model_id( $model ) ) {
			return new WP_Error( 'invalid_model', 'Geminiモデルの設定が無効です。', [ 'status' => 400 ] );
		}

		$prompt = sprintf(
			'%1$s「%2$s」の情報をGoogle検索で確認してください。記事内カード用の日本語紹介文と、配信中の公式ストアページを取得してください。紹介文はジャンルまたは用途、提供元、主な対応プラットフォーム、特徴を180〜260文字で簡潔にまとめてください。リンクはSteam、Nintendo eShop、PlayStation Store、Microsoft Store、Microsoft Store（Xbox）、App Store、Google Play、Epic Games Storeなど、実際に確認できた公式ストアの商品ページだけにしてください。Xbox向けリンクのプラットフォーム名は「Microsoft Store（Xbox）」としてください。推測したURL、検索結果ページ、攻略サイト、ニュース記事、公式トップページは含めないでください。返答はMarkdownを使わず、必ず {"summary":"紹介文","links":[{"platform":"プラットフォーム名","url":"https://..."}]} 形式のJSONだけにしてください。',
			'app' === $type ? 'アプリ' : 'ゲーム',
			$title
		);

		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
			rawurlencode( $model )
		);
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 45,
				'headers' => [
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				],
				'body'    => wp_json_encode(
					[
						'contents'         => [
							[
								'parts' => [ [ 'text' => $prompt ] ],
							],
						],
						'tools'            => [
							[ 'google_search' => (object) [] ],
						],
						'generationConfig' => [
							'maxOutputTokens' => 4096,
						],
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'gemini_request_failed', 'Gemini APIへの接続に失敗しました。', [ 'status' => 502 ] );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			return new WP_Error(
				'gemini_response_failed',
				$message ? 'Gemini APIエラー: ' . sanitize_text_field( $message ) : 'Gemini APIから情報を取得できませんでした。',
				[ 'status' => 502 ]
			);
		}

		$parts = $data['candidates'][0]['content']['parts'] ?? [];
		$text  = '';
		foreach ( (array) $parts as $part ) {
			if ( is_array( $part ) && isset( $part['text'] ) ) {
				$text .= (string) $part['text'];
			}
		}

		$json_text = trim( $text );
		$json_text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $json_text );
		$result    = json_decode( (string) $json_text, true );

		if ( ! is_array( $result ) ) {
			$start = strpos( $json_text, '{' );
			$end   = strrpos( $json_text, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$result = json_decode( substr( $json_text, $start, $end - $start + 1 ), true );
			}
		}

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'invalid_gemini_response', 'Gemini APIの応答をゲーム・アプリ情報として解析できませんでした。', [ 'status' => 502 ] );
		}

		$summary = sanitize_textarea_field( trim( (string) ( $result['summary'] ?? '' ) ) );
		if ( '' === $summary ) {
			return new WP_Error( 'empty_gemini_response', 'Gemini APIから紹介文が返されませんでした。', [ 'status' => 502 ] );
		}

		$links = [];
		$allowed_store_domains = [
			'apps.apple.com',
			'apps.microsoft.com',
			'epicgames.com',
			'gog.com',
			'itch.io',
			'microsoft.com',
			'nintendo.com',
			'play.google.com',
			'playstation.com',
			'steamcommunity.com',
			'steampowered.com',
			'xbox.com',
		];
		foreach ( (array) ( $result['links'] ?? [] ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$platform = sanitize_text_field( (string) ( $link['platform'] ?? '' ) );
			if ( false !== stripos( $platform, 'xbox' ) ) {
				$platform = 'Microsoft Store（Xbox）';
			}
			$url      = esc_url_raw( (string) ( $link['url'] ?? '' ), [ 'https' ] );
			$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$is_store = false;
			foreach ( $allowed_store_domains as $domain ) {
				if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
					$is_store = true;
					break;
				}
			}

			if ( '' === $platform || '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! $is_store ) {
				continue;
			}

			$links[] = [
				'platform' => $platform,
				'url'      => $url,
			];

			if ( count( $links ) >= 10 ) {
				break;
			}
		}

		return rest_ensure_response(
			[
				'summary' => $summary,
				'links'   => $links,
				'model'   => $model,
			]
		);
	}

	/**
	 * OGP 取得ハンドラ (自己完結型)
	 */
	public function handle_fetch_ogp( $request ) {
		$url = $request->get_param( 'url' );
		
		// 外部サイトからの取得ロジックを統合
		$transient_key = 'node_lib_ogp_' . md5( $url );
		$data = get_transient( $transient_key );

		if ( false === $data ) {
			$response = wp_safe_remote_get( $url, [
				'timeout'    => 15,
				'sslverify'  => false,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
			] );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
				return new WP_Error( 'fetch_failed', 'Failed to fetch OGP data: ' . $error_msg, [ 'status' => 404 ] );
			}

			$html = wp_remote_retrieve_body( $response );
			if ( empty( $html ) ) {
				return new WP_Error( 'empty_body', 'Fetched content is empty.', [ 'status' => 404 ] );
			}

			// 文字化け対策
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			if ( str_contains( $content_type, 'shift_jis' ) || str_contains( $content_type, 'sjis' ) ) {
				$html = mb_convert_encoding( $html, 'UTF-8', 'SJIS' );
			}

			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			libxml_clear_errors();
			
			$xpath = new DOMXPath( $dom );
			$data = [
				'title' => trim( $xpath->evaluate( 'string(//meta[@property="og:title"]/@content)' ) ?: $xpath->evaluate( 'string(//title)' ) ),
				'image' => $xpath->evaluate( 'string(//meta[@property="og:image"]/@content)' ),
			];

			set_transient( $transient_key, $data, WEEK_IN_SECONDS );
		}

		// Amazon URL の場合は ASIN を抽出
		if ( strpos( $url, 'amazon.co.jp' ) !== false ) {
			if ( preg_match( '/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/', $url, $matches ) ) {
				$data['asin'] = $matches[1];
			}
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Gutenberg ブロックの登録
	 */
	public function register_gutenberg_block(): void {
		register_block_type( 'node-library/item-card', [
			'attributes'      => [
				'libraryId' => [
					'type'    => 'number',
					'default' => 0,
				],
			],
			'render_callback' => [ $this, 'render_block' ],
		] );

		register_block_type( 'node-library/blog-card', [
			'attributes'      => [
				'url' => [
					'type'    => 'string',
					'default' => '',
				],
			],
			'render_callback' => [ $this, 'render_blog_card_block' ],
		] );

		register_block_type( 'node-library/product-card', [
			'attributes'      => [
				'title'                 => [ 'type' => 'string', 'default' => '' ],
				'price'                 => [ 'type' => 'string', 'default' => '' ],
				'imageUrl'              => [ 'type' => 'string', 'default' => '' ],
				'amazonUrl'             => [ 'type' => 'string', 'default' => '' ],
				'asin'                  => [ 'type' => 'string', 'default' => '' ],
				'rakutenUrl'            => [ 'type' => 'string', 'default' => '' ],
				'showAmazonDisclosure'  => [ 'type' => 'boolean', 'default' => true ],
				'showRakutenDisclosure' => [ 'type' => 'boolean', 'default' => true ],
			],
			'render_callback' => [ $this, 'render_product_card_block' ],
		] );
	}

	/**
	 * ブロックのレンダリング (Product Card - 自己完結型)
	 */
	public function render_product_card_block( $attributes ): string {
		$title                   = $attributes['title'] ?? '';
		$price                   = $attributes['price'] ?? '';
		$image_url               = $attributes['imageUrl'] ?? '';
		$amazon_url              = $attributes['amazonUrl'] ?? '';
		$asin                    = $attributes['asin'] ?? '';
		$rakuten_url             = $attributes['rakutenUrl'] ?? '';
		$show_amazon_disclosure  = $attributes['showAmazonDisclosure'] ?? true;
		$show_rakuten_disclosure = $attributes['showRakutenDisclosure'] ?? true;

		$amazon_id   = get_option('luminous_nexus_amazon_id');
		$rakuten_id  = get_option('luminous_nexus_rakuten_id');
		$disc_amazon = get_option('luminous_nexus_disclosure_amazon', 'Amazonのアソシエイトとして、当メディアは適格販売により収入を得ています。');
		$disc_rakuten = get_option('luminous_nexus_disclosure_rakuten', '当メディアは、楽天アフィリエイト・プログラムの参加者です。');

		// Amazon URL の構築 (ASIN があれば優先)
		if (!empty($asin)) {
			$amazon_url = "https://www.amazon.co.jp/dp/{$asin}";
			if ($amazon_id) {
				$amazon_url .= "?tag={$amazon_id}";
			}
		} elseif (!empty($amazon_url) && $amazon_id && !str_contains($amazon_url, 'tag=')) {
			$separator = str_contains($amazon_url, '?') ? '&' : '?';
			$amazon_url .= "{$separator}tag={$amazon_id}";
		}

		$has_amazon  = !empty($amazon_url);
		$has_rakuten = !empty($rakuten_url);

		if (!$has_amazon && !$has_rakuten) return '';

		ob_start();
		?>
		<div class="m3-product-card-container m3-reveal">
			<div class="m3-product-card">
				<?php if (!empty($image_url)) : ?>
				<div class="m3-product-card__image">
					<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
				</div>
				<?php endif; ?>

				<div class="m3-product-card__content">
					<?php if (!empty($title)) : ?>
					<h4 class="m3-product-card__title"><?php echo esc_html($title); ?></h4>
					<?php endif; ?>

					<?php if (!empty($price)) : ?>
					<div class="m3-product-card__price-wrapper">
						<span class="m3-product-card__price-label">参考価格:</span>
						<span class="m3-product-card__price"><?php echo esc_html($price); ?></span>
					</div>
					<?php endif; ?>

					<div class="m3-product-card__actions">
						<?php if ($has_amazon) : ?>
						<a href="<?php echo esc_url($amazon_url); ?>" class="m3-product-btn m3-product-btn--amazon m3-ripple-host" target="_blank" rel="noopener noreferrer sponsored">
							<img src="https://www.google.com/s2/favicons?domain=amazon.co.jp&sz=32" alt="" class="m3-product-btn__icon">
							Amazonで見る
						</a>
						<?php endif; ?>

						<?php if ($has_rakuten) : ?>
						<a href="<?php echo esc_url($rakuten_url); ?>" class="m3-product-btn m3-product-btn--rakuten m3-ripple-host" target="_blank" rel="noopener noreferrer sponsored">
							<img src="https://www.google.com/s2/favicons?domain=rakuten.co.jp&sz=32" alt="" class="m3-product-btn__icon">
							楽天市場で見る
						</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			
			<div class="m3-product-card__disclosures">
				<?php if ($show_amazon_disclosure && !empty($disc_amazon)) : ?>
					<div class="m3-product-card__disclosure">
						<span class="material-symbols-outlined">info</span>
						<?php echo esc_html($disc_amazon); ?>
					</div>
				<?php endif; ?>
				
				<?php if ($show_rakuten_disclosure && !empty($disc_rakuten)) : ?>
					<div class="m3-product-card__disclosure">
						<span class="material-symbols-outlined">info</span>
						<?php echo esc_html($disc_rakuten); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * ブロックのレンダリング (Blog Card)
	 */
	public function render_blog_card_block( $attributes ): string {
		$url = $attributes['url'] ?? '';
		if ( empty( $url ) ) return '';

		if ( function_exists( 'node_render_blogcard' ) ) {
			return node_render_blogcard( $url );
		}

		if ( function_exists( 'luminous_nexus_blogcard_shortcode' ) ) {
			return luminous_nexus_blogcard_shortcode( [ 'url' => $url ] );
		}

		return '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
	}

	/**
	 * ブロックエディタ用アセットの読み込み
	 */
	public function enqueue_block_assets(): void {
		wp_enqueue_script(
			'node-library-block-editor',
			NODE_LIBRARY_URL . 'assets/js/block-editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-block-editor' ],
			NODE_LIBRARY_VERSION,
			true
		);

		wp_localize_script(
			'node-library-block-editor',
			'nodeLibraryEditor',
			array(
				'adminNewUrl'  => admin_url( 'post-new.php?post_type=node_library' ),
				'adminListUrl' => admin_url( 'edit.php?post_type=node_library' ),
			)
		);
	}

	/**
	 * 管理画面用スクリプト（ライブラリ編集・投稿紐付け）
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'node_library', 'post' );
		if ( ! in_array( $screen->post_type, $allowed, true ) ) {
			return;
		}

		wp_enqueue_script(
			'node-library-admin-meta',
			NODE_LIBRARY_URL . 'assets/js/admin-meta.js',
			array( 'wp-api-fetch' ),
			NODE_LIBRARY_VERSION,
			true
		);

		wp_localize_script(
			'node-library-admin-meta',
			'nodeLibraryAdmin',
			array(
				'generatePath' => '/node-library/v1/generate-game-info',
			)
		);

		wp_add_inline_style(
			'wp-admin',
			'.node-library-admin-fields .link-row.is-hidden{display:none}
			.node-library-metabox-help{color:#646970;font-size:12px;line-height:1.6;margin:8px 0 0}
			.node-library-metabox-preview{margin-top:10px;padding:10px 12px;background:#f6f7f7;border-radius:8px;border:1px solid #dcdcde}
			.node-library-metabox-links{margin-top:8px;display:flex;flex-wrap:wrap;gap:8px}
			.node-library-metabox-links a{font-size:12px}'
		);
	}

	/**
	 * ブロックのレンダリング (サーバーサイド)
	 */
	public function render_block( $attributes ): string {
		$lib_id = $attributes['libraryId'] ?? 0;
		if ( ! $lib_id ) return '';

		$lib_post = get_post( $lib_id );
		if ( ! $lib_post || $lib_post->post_type !== 'node_library' ) return '';

		$game_info = [
			'title'   => $lib_post->post_title,
			'type'    => get_post_meta( $lib_id, '_node_library_type', true ) ?: 'game',
			'summary' => get_post_meta( $lib_id, '_node_library_summary', true ),
			'links'   => get_post_meta( $lib_id, '_node_library_links', true ),
		];

		ob_start();
		include NODE_LIBRARY_DIR . 'templates/card-library.php';
		return ob_get_clean();
	}

	/**
	 * カスタム投稿タイプ 'node_library' の登録
	 */
	public function register_cpt(): void {
		$labels = [
			'name'               => 'Node Library',
			'singular_name'      => 'ライブラリ項目',
			'menu_name'          => 'Node Library',
			'add_new'            => '新規追加',
			'add_new_item'       => '新しいゲーム・アプリを追加',
			'edit_item'          => '項目を編集',
			'new_item'           => '新規項目',
			'view_item'          => '項目を表示',
			'search_items'       => 'ライブラリを検索',
			'not_found'          => '見つかりませんでした',
			'not_found_in_trash' => 'ゴミ箱内にありません',
		];

		$args = [
			'labels'              => $labels,
			'public'              => false, 
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true, 
			'query_var'           => true,
			'rewrite'             => [ 'slug' => 'node-library' ],
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-database-add',
			'supports'            => [ 'title' ],
		];

		register_post_type( 'node_library', $args );
	}

	/**
	 * メタボックスの登録
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'node_library_details',
			'ゲーム・アプリ詳細情報',
			[ $this, 'render_details_meta_box' ],
			'node_library',
			'normal',
			'high'
		);

		add_meta_box(
			'node_post_library_select',
			'ゲーム・アプリ情報の紐付け',
			[ $this, 'render_select_meta_box' ],
			'post',
			'side',
			'default'
		);
	}

	/**
	 * メタボックスの描画
	 */
	public function render_details_meta_box( $post ): void {
		wp_nonce_field( 'node_library_save_details', 'node_library_details_nonce' );
		
		$type    = get_post_meta( $post->ID, '_node_library_type', true );
		$type    = in_array( $type, [ 'game', 'app' ], true ) ? $type : 'game';
		$summary = get_post_meta( $post->ID, '_node_library_summary', true );
		$links   = get_post_meta( $post->ID, '_node_library_links', true );
		if ( ! is_array( $links ) ) $links = [];

		?>
		<div class="node-library-admin-fields">
			<p>
				<label for="node-library-type"><strong>種類:</strong></label><br>
				<select name="node_library_type" id="node-library-type">
					<option value="game" <?php selected( $type, 'game' ); ?>>ゲーム</option>
					<option value="app" <?php selected( $type, 'app' ); ?>>アプリ</option>
				</select>
			</p>
			<p>
				<label><strong>紹介文・レビュー:</strong></label><br>
				<textarea name="node_library_summary" rows="4" style="width:100%;" placeholder="作品の魅力やレビューを自由に記載してください。"><?php echo esc_textarea( $summary ); ?></textarea>
				<span style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;">
					<button type="button" class="button button-secondary" id="node-library-generate-info">
						<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
						ゲーム（アプリ）情報を取得
					</button>
					<span id="node-library-generate-status" role="status" aria-live="polite"></span>
				</span>
				<span class="description">Geminiが作成した下書きを確認・修正してから保存してください。</span>
			</p>
			
			<div id="node-library-links-editor">
				<label><strong>ストア・配信ページリンク:</strong></label>
				<div class="links-container" style="margin-top:10px;" data-max-rows="10">
					<?php
					$filled_count = is_array( $links ) ? count( $links ) : 0;
					$visible_rows = max( 2, min( 10, $filled_count + 1 ) );
					for ( $i = 0; $i < 10; $i++ ) :
						$p       = $links[ $i ]['platform'] ?? '';
						$u       = $links[ $i ]['url'] ?? '';
						$hidden  = $i >= $visible_rows ? ' is-hidden' : '';
					?>
						<div class="link-row<?php echo esc_attr( $hidden ); ?>" style="display:flex; gap:10px; margin-bottom:8px; align-items:center;">
							<span style="min-width:20px; font-weight:bold; color:#666;"><?php echo $i + 1; ?>.</span>
							<input type="text" name="node_library_links[<?php echo $i; ?>][platform]" value="<?php echo esc_attr( $p ); ?>" placeholder="ストア名 (例: Steam, Nintendo Switch)" style="flex:1;">
							<input type="text" name="node_library_links[<?php echo $i; ?>][url]" value="<?php echo esc_url( $u ); ?>" placeholder="https://..." style="flex:2;">
						</div>
					<?php endfor; ?>
				</div>
				<p style="margin-top:8px;">
					<button type="button" class="button button-secondary" id="node-library-add-link">リンク行を追加</button>
				</p>
				<p class="description">ストア名に応じてボタン色・アイコンが自動選択されます。（例: Steam, PlayStation, Microsoft Store（Xbox）, Nintendo Switch, App Store）</p>
			</div>
		</div>
		<?php
	}

	/**
	 * 投稿側選択メタボックス
	 */
	public function render_select_meta_box( $post ): void {
		wp_nonce_field( 'node_post_library_save', 'node_post_library_nonce' );
		$selected_id = get_post_meta( $post->ID, '_node_linked_library_id', true );

		$libraries = get_posts([
			'post_type'      => 'node_library',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC'
		]);

		$preview_summary = '';
		if ( $selected_id ) {
			$preview_summary = (string) get_post_meta( (int) $selected_id, '_node_library_summary', true );
		}
		?>
		<p class="node-library-metabox-help">
			紐付けると、記事フッター（タグの下）にゲーム・アプリカードが<strong>自動表示</strong>されます。<br>
			本文の任意位置に入れたい場合は、ブロック追加 → <strong>Node</strong> カテゴリ →「ライブラリカード」を使ってください。
		</p>
		<select name="node_linked_library_id" id="node-linked-library-select" style="width:100%; margin-top:8px;">
			<option value="">— 連携しない —</option>
			<?php foreach ( $libraries as $lib ) :
				$lib_summary = (string) get_post_meta( $lib->ID, '_node_library_summary', true );
			?>
				<option value="<?php echo esc_attr( (string) $lib->ID ); ?>" data-summary="<?php echo esc_attr( wp_trim_words( $lib_summary, 40 ) ); ?>" <?php selected( (string) $selected_id, (string) $lib->ID ); ?>>
					<?php echo esc_html( $lib->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( empty( $libraries ) ) : ?>
			<p class="node-library-metabox-help" style="margin-top:10px;">
				ライブラリ項目がまだありません。先に項目を登録してください。
			</p>
		<?php endif; ?>
		<div id="node-library-metabox-preview" class="node-library-metabox-preview" <?php echo $selected_id ? '' : 'style="display:none;"'; ?>>
			<strong>選択中の紹介文:</strong>
			<span id="node-library-metabox-preview-text"><?php echo esc_html( $preview_summary ? wp_trim_words( $preview_summary, 40 ) : '（紹介文なし）' ); ?></span>
		</div>
		<div class="node-library-metabox-links">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=node_library' ) ); ?>">ライブラリ一覧</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=node_library' ) ); ?>">新規項目を追加</a>
		</div>
		<?php
	}

	/**
	 * 保存処理
	 */
	public function save_meta_boxes( int $post_id ): void {
		if ( isset( $_POST['node_library_details_nonce'] ) && wp_verify_nonce( $_POST['node_library_details_nonce'], 'node_library_save_details' ) ) {
			if ( isset( $_POST['node_library_type'] ) ) {
				$type = sanitize_key( wp_unslash( $_POST['node_library_type'] ) );
				update_post_meta( $post_id, '_node_library_type', in_array( $type, [ 'game', 'app' ], true ) ? $type : 'game' );
			}
			if ( isset( $_POST['node_library_summary'] ) ) {
				update_post_meta( $post_id, '_node_library_summary', sanitize_textarea_field( $_POST['node_library_summary'] ) );
			}
			if ( isset( $_POST['node_library_links'] ) ) {
				$links = [];
				foreach ( $_POST['node_library_links'] as $link ) {
					if ( ! empty( $link['platform'] ) && ! empty( $link['url'] ) ) {
						$links[] = [
							'platform' => sanitize_text_field( $link['platform'] ),
							'url'      => esc_url_raw( $link['url'] )
						];
					}
				}
				update_post_meta( $post_id, '_node_library_links', $links );
			}
		}

		if ( isset( $_POST['node_post_library_nonce'] ) && wp_verify_nonce( $_POST['node_post_library_nonce'], 'node_post_library_save' ) ) {
			if ( isset( $_POST['node_linked_library_id'] ) ) {
				update_post_meta( $post_id, '_node_linked_library_id', sanitize_text_field( $_POST['node_linked_library_id'] ) );
			}
		}
	}

	/**
	 * 表示処理 (自動挿入)
	 */
	public function render_library_card_on_post( int $post_id ): void {
		// 自動挿入が無効化されている場合は何もしない
		if ( get_option( 'node_library_auto_insert', '1' ) !== '1' ) {
			return;
		}

		$lib_id = get_post_meta( $post_id, '_node_linked_library_id', true );
		if ( empty( $lib_id ) ) {
			$game_info = get_post_meta( $post_id, '_node_game_info', true );
			if ( is_array( $game_info ) && ! empty( $game_info['title'] ) ) {
				include NODE_LIBRARY_DIR . 'templates/card-library.php';
			}
			return;
		}

		$lib_post = get_post( $lib_id );
		if ( ! $lib_post || $lib_post->post_type !== 'node_library' ) return;

		$game_info = [
			'title'   => $lib_post->post_title,
			'type'    => get_post_meta( $lib_id, '_node_library_type', true ) ?: 'game',
			'summary' => get_post_meta( $lib_id, '_node_library_summary', true ),
			'links'   => get_post_meta( $lib_id, '_node_library_links', true ),
		];

		include NODE_LIBRARY_DIR . 'templates/card-library.php';
	}

}

/**
 * 起動
 */
function node_library_init() {
	Node_Library::instance();
}
add_action( 'plugins_loaded', 'node_library_init' );
