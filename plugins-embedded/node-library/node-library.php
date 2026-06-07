<?php
/**
 * Plugin Name:  Node Library
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  ゲーム・アプリ情報の管理と表示。カスタム投稿タイプによるリスト管理と、記事への紐付け機能を提供。
 * Version:      1.3.0
 * Author:       Luminous Core Teams
 * License:      MIT
 * Text Domain:  node-library
 *
 * @package Node_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NODE_LIBRARY_VERSION', '1.3.0' );
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
		
		// テーマのフックに応答（ライター情報の下に表示）
		add_action( 'luminous_after_writer', [ $this, 'render_library_card_on_post' ] );

		// 管理画面用スタイル
		add_action( 'admin_head', [ $this, 'admin_styles' ] );

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

		// Luminous Nexus のショートコード関数を呼び出す
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
		
		$summary = get_post_meta( $post->ID, '_node_library_summary', true );
		$links   = get_post_meta( $post->ID, '_node_library_links', true );
		if ( ! is_array( $links ) ) $links = [];

		?>
		<div class="node-library-admin-fields">
			<p>
				<label><strong>紹介文・レビュー:</strong></label><br>
				<textarea name="node_library_summary" rows="4" style="width:100%;" placeholder="作品の魅力やレビューを自由に記載してください。"><?php echo esc_textarea( $summary ); ?></textarea>
			</p>
			
			<div id="node-library-links-editor">
				<label><strong>ストア・配信ページリンク:</strong></label>
				<div class="links-container" style="margin-top:10px;">
					<?php for ( $i = 0; $i < 10; $i++ ) : 
						$p = $links[$i]['platform'] ?? '';
						$u = $links[$i]['url'] ?? '';
					?>
						<div class="link-row" style="display:flex; gap:10px; margin-bottom:8px; align-items:center;">
							<span style="min-width:20px; font-weight:bold; color:#666;"><?php echo $i + 1; ?>.</span>
							<input type="text" name="node_library_links[<?php echo $i; ?>][platform]" value="<?php echo esc_attr($p); ?>" placeholder="ストア名 (例: Steam, App Store)" style="flex:1;">
							<input type="text" name="node_library_links[<?php echo $i; ?>][url]" value="<?php echo esc_url($u); ?>" placeholder="https://..." style="flex:2;">
						</div>
					<?php endfor; ?>
				</div>
				<p class="description">※ 入力されたストア名に基づいて、アイコンが自動的に選択されます。</p>
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

		?>
		<select name="node_linked_library_id" style="width:100%;">
			<option value="">-- 連携しない --</option>
			<?php foreach ( $libraries as $lib ) : ?>
				<option value="<?php echo $lib->ID; ?>" <?php selected( $selected_id, $lib->ID ); ?>>
					<?php echo esc_html( $lib->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * 保存処理
	 */
	public function save_meta_boxes( int $post_id ): void {
		if ( isset( $_POST['node_library_details_nonce'] ) && wp_verify_nonce( $_POST['node_library_details_nonce'], 'node_library_save_details' ) ) {
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
			'summary' => get_post_meta( $lib_id, '_node_library_summary', true ),
			'links'   => get_post_meta( $lib_id, '_node_library_links', true ),
		];

		include NODE_LIBRARY_DIR . 'templates/card-library.php';
	}

	public function admin_styles(): void {
		echo '<style>
			.node-library-admin-fields label { display: block; margin-bottom: 5px; }
		</style>';
	}
}

/**
 * 起動
 */
function node_library_init() {
	Node_Library::instance();
}
add_action( 'plugins_loaded', 'node_library_init' );
