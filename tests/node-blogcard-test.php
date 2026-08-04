<?php
/**
 * inc/blogcard.php の自動テスト。
 *
 * @package Node
 */

class Node_Blogcard_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'luminous_nexus_amazon_id' );
		parent::tear_down();
	}

	private function mock_ogp_response( string $url, string $title = 'External OGP Title', string $image = 'https://example.com/card.jpg', string $description = 'External OGP Description' ): callable {
		$callback = static function ( $preempt, $args, $request_url ) use ( $url, $title, $image, $description ) {
			if ( $request_url !== $url ) {
				return $preempt;
			}

			return array(
				'headers'  => array(
					'content-type' => 'text/html; charset=UTF-8',
				),
				'body'     => sprintf(
					'<!doctype html><html><head><title>%1$s</title><meta property="og:title" content="%1$s"><meta property="og:description" content="%3$s"><meta property="og:image" content="%2$s"><meta property="og:site_name" content="Example Site"></head><body></body></html>',
					esc_attr( $title ),
					esc_attr( $image ),
					esc_attr( $description )
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );
		return $callback;
	}

	public function test_excluded_oembed_provider_matches_domains_and_provider_name(): void {
		$data = (object) array( 'provider_name' => 'Example' );

		$this->assertTrue( node_is_excluded_oembed_provider( 'https://twitter.com/luminous/status/1', $data ) );
		$this->assertTrue( node_is_excluded_oembed_provider( 'https://x.com/luminous/status/1', $data ) );
		$this->assertTrue( node_is_excluded_oembed_provider( 'https://www.youtube.com/watch?v=abc', $data ) );
		$this->assertTrue( node_is_excluded_oembed_provider( 'https://youtu.be/abc', $data ) );
		$this->assertTrue( node_is_excluded_oembed_provider( 'https://example.com/watch', (object) array( 'provider_name' => 'YouTube' ) ) );
		$this->assertFalse( node_is_excluded_oembed_provider( 'https://example.com/article', $data ) );
		$this->assertFalse( node_is_excluded_oembed_provider( home_url( '/sample-post/' ), $data ) );
	}

	public function test_special_embed_builds_x_twitter_blockquote(): void {
		foreach ( array( 'https://twitter.com/jack/status/20', 'https://x.com/jack/status/20', 'https://mobile.twitter.com/jack/status/20' ) as $url ) {
			$embed = node_special_embed( $url );
			$this->assertStringContainsString( 'node-embed--x', $embed, $url );
			$this->assertStringContainsString( 'twitter-tweet', $embed, $url );
			$this->assertStringContainsString( esc_url( $url ), $embed, $url );
		}
	}

	public function test_special_embed_builds_youtube_iframe_for_short_and_shorts(): void {
		$this->assertStringContainsString( 'youtube.com/embed/dQw4w9WgXcQ', node_special_embed( 'https://youtu.be/dQw4w9WgXcQ' ) );
		$this->assertStringContainsString( 'youtube.com/embed/dQw4w9WgXcQ', node_special_embed( 'https://www.youtube.com/shorts/dQw4w9WgXcQ' ) );
		$this->assertStringContainsString( 'node-embed--video', node_special_embed( 'https://youtu.be/dQw4w9WgXcQ' ) );
	}

	public function test_special_embed_builds_google_maps_iframe(): void {
		$place = node_special_embed( 'https://www.google.com/maps/place/Tokyo+Tower/@35.6586,139.7454,17z/data=xxx' );
		$this->assertStringContainsString( 'node-embed--map', $place );
		$this->assertStringContainsString( 'maps.google.com/maps', $place );
		$this->assertStringContainsString( 'output=embed', $place );
		$this->assertStringContainsString( 'Tokyo', $place );

		$query = node_special_embed( 'https://www.google.com/maps?q=Tokyo+Tower' );
		$this->assertStringContainsString( 'node-embed--map', $query );
		$this->assertStringContainsString( 'output=embed', $query );
	}

	public function test_is_google_maps_url_matches_maps_and_short_links(): void {
		$this->assertTrue( node_is_google_maps_url( 'https://www.google.com/maps/place/Tokyo/@35.6,139.7,15z' ) );
		$this->assertTrue( node_is_google_maps_url( 'https://maps.google.com/?q=Tokyo' ) );
		$this->assertTrue( node_is_google_maps_url( 'https://maps.app.goo.gl/AbCdEf' ) );
		$this->assertFalse( node_is_google_maps_url( 'https://www.google.com/search?q=tokyo' ) );
		$this->assertFalse( node_is_google_maps_url( 'https://example.com/maps' ) );
	}

	public function test_maps_oembed_response_returns_rich_iframe_for_maps_url(): void {
		$request = new WP_REST_Request( 'GET', '/node/v1/maps-oembed' );
		$request->set_param( 'url', 'https://www.google.com/maps/place/Tokyo+Tower/@35.6586,139.7454,17z' );

		$data = node_maps_oembed_response( $request );

		$this->assertIsArray( $data );
		$this->assertSame( 'rich', $data['type'] );
		$this->assertSame( 'Google Maps', $data['provider_name'] );
		$this->assertStringContainsString( 'node-embed--map', $data['html'] );
		$this->assertStringContainsString( 'output=embed', $data['html'] );
	}

	public function test_maps_oembed_response_rejects_non_maps_url(): void {
		$request = new WP_REST_Request( 'GET', '/node/v1/maps-oembed' );
		$request->set_param( 'url', 'https://example.com/not-a-map' );

		$this->assertInstanceOf( WP_Error::class, node_maps_oembed_response( $request ) );
	}

	public function test_pre_oembed_result_short_circuits_google_maps(): void {
		$html = node_pre_oembed_maps_result( null, 'https://www.google.com/maps/place/Tokyo/@35.6,139.7,15z', array() );
		$this->assertIsString( $html );
		$this->assertStringContainsString( 'node-embed--map', (string) $html );

		// Non-maps URLs must pass through untouched.
		$this->assertNull( node_pre_oembed_maps_result( null, 'https://example.com/article', array() ) );
	}

	public function test_special_embed_returns_empty_for_ordinary_url(): void {
		$this->assertSame( '', node_special_embed( 'https://example.com/normal-article' ) );
		$this->assertSame( '', node_special_embed( 'https://twitter.com/jack' ) ); // profile, not a status
	}

	public function test_embed_maybe_make_link_prefers_special_embed_over_card(): void {
		$html = node_blogcard_hydrate( node_embed_maybe_make_link( '<a href="https://x.com/jack/status/20">x</a>', 'https://x.com/jack/status/20' ) );
		$this->assertStringContainsString( 'node-embed--x', $html );
		$this->assertStringNotContainsString( 'm3-blogcard__overlay', $html );
	}

	public function test_oembed_dataparse_returns_original_html_for_excluded_provider(): void {
		$return = '<blockquote class="twitter-tweet">Original embed</blockquote>';
		$data   = (object) array(
			'title'         => 'Tweet title',
			'provider_name' => 'Twitter',
		);

		$this->assertSame( $return, node_oembed_dataparse( $return, $data, 'https://twitter.com/luminous/status/1' ) );
	}

	public function test_oembed_dataparse_uses_internal_ogp_data_for_home_url(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Internal Blogcard Title',
				'post_excerpt' => 'Internal Blogcard Excerpt',
			)
		);
		$url     = get_permalink( $post_id );
		$html    = node_blogcard_hydrate(
			node_oembed_dataparse(
				'<div>Original embed</div>',
				(object) array(
					'title'         => 'oEmbed Title',
					'provider_name' => 'WordPress',
				),
				$url
			)
		);

		$this->assertStringContainsString( 'm3-blogcard__overlay', $html );
		$this->assertStringContainsString( 'm3-blogcard--internal', $html );
		$this->assertStringContainsString( 'Internal Blogcard Title', $html );
		$this->assertStringContainsString( 'Internal Blogcard Excerpt', $html );
		$this->assertStringNotContainsString( 'oEmbed Title', $html );
	}

	public function test_oembed_dataparse_builds_external_card_from_data(): void {
		$html = node_blogcard_hydrate(
			node_oembed_dataparse(
				'<div>Original embed</div>',
				(object) array(
					'title'         => 'External oEmbed Title',
					'thumbnail_url' => 'https://example.com/thumb.jpg',
					'provider_name' => 'Example Provider',
				),
				'https://example.com/article'
			)
		);

		$this->assertStringContainsString( 'm3-blogcard__overlay', $html );
		$this->assertStringContainsString( 'm3-blogcard--external', $html );
		$this->assertStringContainsString( 'External oEmbed Title', $html );
		$this->assertStringContainsString( 'https://example.com/thumb.jpg', $html );
		$this->assertStringContainsString( 'Example Provider', $html );
		$this->assertStringNotContainsString( 'm3-blogcard__description', $html );

		// コピー/シェアのアクションボタンがカードURL・タイトルを保持していること。
		$this->assertStringContainsString( 'm3-blogcard__action--copy', $html );
		$this->assertStringContainsString( 'm3-blogcard__action--share', $html );
		$this->assertStringContainsString( 'data-share-title="External oEmbed Title"', $html );
		$this->assertStringContainsString( 'data-url="https://example.com/article"', $html );
	}

	public function test_oembed_dataparse_builds_external_card_without_thumbnail(): void {
		$html = node_blogcard_hydrate(
			node_oembed_dataparse(
				'<div>Original embed</div>',
				(object) array(
					'title'         => 'No Thumbnail Title',
					'provider_name' => 'No Thumbnail Provider',
				),
				'https://example.net/no-thumbnail'
			)
		);

		$this->assertStringContainsString( 'No Thumbnail Title', $html );
		$this->assertStringNotContainsString( 'm3-blogcard__image', $html );
	}

	public function test_is_luminous_core_host_matches_apex_and_www_case_insensitive(): void {
		$this->assertTrue( node_is_luminous_core_host( 'luminous-core.net' ) );
		$this->assertTrue( node_is_luminous_core_host( 'www.luminous-core.net' ) );
		$this->assertTrue( node_is_luminous_core_host( 'LUMINOUS-CORE.NET' ) );
		$this->assertFalse( node_is_luminous_core_host( 'example.com' ) );
	}

	public function test_oembed_dataparse_keeps_real_image_for_luminous_core_host_by_default(): void {
		// 自動検出（単独行URL・core/embedブロック経由）は、本番側が生成済みの
		// 記事ごとのOGP画像をそのまま使う。ブランド画像で上書きしない。
		$html = node_blogcard_hydrate(
			node_oembed_dataparse(
				'<div>Original embed</div>',
				(object) array(
					'title'         => 'Luminous Core Article',
					'thumbnail_url' => 'https://luminous-core.net/wp-content/uploads/per-post-thumb.jpg',
					'provider_name' => 'Luminous Core',
				),
				'https://luminous-core.net/some-article'
			)
		);

		$this->assertStringContainsString( 'Luminous Core Article', $html );
		$this->assertStringContainsString( 'per-post-thumb.jpg', $html );
		$this->assertStringNotContainsString( 'luminous-core-card-image.png', $html );
	}

	public function test_shortcode_overrides_image_with_brand_card_for_luminous_core_host(): void {
		$url    = 'https://luminous-core.net/some-article';
		$filter = $this->mock_ogp_response( $url, 'Luminous Core Article', 'https://luminous-core.net/per-post-thumb.jpg' );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$html = node_blogcard_shortcode( array( 'url' => $url ) );

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertStringContainsString( 'Luminous Core Article', $html );
		$this->assertStringContainsString( 'luminous-core-card-image.png', $html );
		$this->assertStringNotContainsString( 'per-post-thumb.jpg', $html );
	}

	public function test_embed_maybe_make_link_keeps_real_image_for_luminous_core_host(): void {
		// embed_maybe_make_link（自動検出のOGPスクレイピング経路）もブランド画像で上書きしない。
		$url    = 'https://luminous-core.net/another-article';
		$filter = $this->mock_ogp_response( $url, 'Another Luminous Core Article', 'https://luminous-core.net/another-thumb.jpg' );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$html = node_blogcard_hydrate( node_embed_maybe_make_link( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $url ) );

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertStringContainsString( 'Another Luminous Core Article', $html );
		$this->assertStringContainsString( 'another-thumb.jpg', $html );
		$this->assertStringNotContainsString( 'luminous-core-card-image.png', $html );
	}

	public function test_embed_maybe_make_link_delegates_to_render_blogcard_with_amazon_tag(): void {
		$url      = 'https://www.amazon.co.jp/dp/B000000000';
		$filter   = $this->mock_ogp_response( $url, 'Amazon Product Title' );
		$original = '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';

		update_option( 'luminous_nexus_amazon_id', 'luminous-22' );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$html = node_blogcard_hydrate( node_embed_maybe_make_link( $original, $url ) );

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertStringContainsString( 'Amazon Product Title', $html );
		$this->assertStringContainsString( 'tag=luminous-22', $html );
		$this->assertStringNotContainsString( $original, $html );
	}

	public function test_deferred_card_survives_wpautop_and_hydrates_intact(): void {
		// autoembed 経路のカードは wpautop より前に挿入されるため、直接カード HTML を返すと
		// wpautop が <p>/<br> を差し込んで <a>×ブロック <div> 構造を破壊する。プレースホルダ→
		// wpautop→hydrate の順で処理され、最終 HTML にカードが崩れず復元されることを検証する。
		$placeholder = node_oembed_dataparse(
			'<div>Original embed</div>',
			(object) array(
				'title'         => 'Deferred Card Title',
				'thumbnail_url' => 'https://example.com/thumb.jpg',
				'provider_name' => 'Example Provider',
			),
			'https://example.com/deferred'
		);

		$this->assertStringContainsString( 'node-blogcard-slot', $placeholder );
		$this->assertStringNotContainsString( 'm3-blogcard__overlay', $placeholder );

		// 本番の the_content と同じ順序: wpautop(10) → hydrate(20)。
		$final = node_blogcard_hydrate( wpautop( "<h2>foo</h2>\n\n{$placeholder}\n\n<h2>bar</h2>" ) );

		$this->assertStringNotContainsString( 'node-blogcard-slot', $final );
		$this->assertStringContainsString( 'm3-blogcard__overlay', $final );
		$this->assertStringContainsString( 'Deferred Card Title', $final );
		// カード内部に wpautop 由来の破壊がないこと。
		$this->assertStringNotContainsString( '<br', $final );
		$this->assertStringNotContainsString( '<p></a>', $final );
		$this->assertStringNotContainsString( '</p>' . "\n" . '<div class="m3-blogcard__body"', $final );
	}

	public function test_auto_blogcard_is_passthrough_and_filter_registration_remains(): void {
		$content = "<p>Before</p>\nhttps://example.com/article\n<p>After</p>";

		$this->assertSame( $content, node_auto_blogcard( $content ) );
		$this->assertNotFalse( has_filter( 'the_content', 'node_auto_blogcard' ) );
	}

	public function test_shortcode_and_ogp_scraping_fallback_still_render_blogcard(): void {
		$url    = 'https://example.org/ogp-article';
		$filter = $this->mock_ogp_response( $url, 'Scraped OGP Title', description: 'Scraped OGP Description' );

		delete_transient( 'node_ogp_' . md5( $url ) );

		$ogp  = node_get_ogp_data( $url );
		$html = do_shortcode( '[blogcard url="' . esc_url( $url ) . '"]' );

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertIsArray( $ogp );
		$this->assertSame( 'Scraped OGP Title', $ogp['title'] );
		$this->assertStringContainsString( 'Scraped OGP Title', $html );
		$this->assertStringContainsString( 'Scraped OGP Description', $html );
		$this->assertStringContainsString( 'm3-blogcard__overlay', $html );
	}

	/**
	 * ストアのボット防御を模して 403 を返すモック。
	 *
	 * @param int $calls 呼び出し回数（参照渡し）。
	 * @return callable
	 */
	private function mock_blocked_response( int &$calls ): callable {
		$callback = static function ( $preempt, $args, $request_url ) use ( &$calls ) {
			++$calls;
			return array(
				'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
				'body'     => '<html><head><title>Access Denied</title></head><body></body></html>',
				'response' => array(
					'code'    => 403,
					'message' => 'Forbidden',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );
		return $callback;
	}

	public function test_store_provider_matches_game_stores_only(): void {
		$expected = array(
			'https://store-jp.nintendo.com/item/software/D70010000073404'                                => 'nintendo',
			'https://www.nintendo.com/jp/store/products/the-legend-of-zelda-tears-of-the-kingdom-switch/' => 'nintendo',
			'https://ec.nintendo.com/JP/ja/titles/70010000012332'                                        => 'nintendo',
			'https://www.nintendo.co.jp/software/switch/aaaaa/index.html'                                => 'nintendo',
			'https://store.playstation.com/ja-jp/product/JP0082-PPSA01284_00-ASTROSBGDELUXE01'           => 'playstation',
			'https://www.xbox.com/ja-JP/games/store/forza-horizon-5/9NKX70BBCDRN'                        => 'xbox',
			'https://apps.microsoft.com/detail/9NKX70BBCDRN'                                             => 'xbox',
			'https://store.steampowered.com/app/570/Dota_2/'                                             => 'steam',
		);

		foreach ( $expected as $url => $slug ) {
			$this->assertSame( $slug, node_store_provider( $url )['slug'] ?? '', $url );
		}

		// ストア以外のページ・別ドメインの偽装は対象外。
		$not_store = array(
			'https://www.nintendo.com/jp/topics/article/abc',
			'https://www.playstation.com/ja-jp/games/astro-bot/',
			'https://www.xbox.com/ja-JP/games/forza-horizon-5',
			'https://steamcommunity.com/app/570',
			'https://nintendo.com.evil.example.net/store/products/x',
			'https://example.com/article',
		);

		foreach ( $not_store as $url ) {
			$this->assertSame( array(), node_store_provider( $url ), $url );
		}
	}

	public function test_store_title_from_url_humanizes_slug_and_skips_product_ids(): void {
		$this->assertSame( 'Forza Horizon 5', node_store_title_from_url( 'https://www.xbox.com/ja-JP/games/store/forza-horizon-5/9NKX70BBCDRN', 'xbox' ) );
		$this->assertSame( 'Dota 2', node_store_title_from_url( 'https://store.steampowered.com/app/570/Dota_2/', 'steam' ) );

		// 商品 ID しか含まない URL からは題名を作らない。
		$this->assertSame( '', node_store_title_from_url( 'https://store-jp.nintendo.com/item/software/D70010000073404', 'nintendo' ) );
		$this->assertSame( '', node_store_title_from_url( 'https://store.playstation.com/ja-jp/product/JP0082-PPSA01284_00-ASTROSBGDELUXE01', 'playstation' ) );
	}

	public function test_store_urls_render_card_even_when_fetch_is_blocked(): void {
		$cases = array(
			'https://store-jp.nintendo.com/item/software/D70010000073404'                      => array( 'nintendo', 'ニンテンドーストア' ),
			'https://store.playstation.com/ja-jp/product/JP0082-PPSA01284_00-ASTROSBGDELUXE01' => array( 'playstation', 'PlayStation Store' ),
			'https://www.xbox.com/ja-JP/games/store/forza-horizon-5/9NKX70BBCDRN'              => array( 'xbox', 'Microsoft ストア' ),
			'https://store.steampowered.com/app/570/Dota_2/'                                   => array( 'steam', 'Steam' ),
		);

		$calls    = 0;
		$callback = $this->mock_blocked_response( $calls );

		foreach ( $cases as $url => list( $slug, $store_name ) ) {
			delete_transient( 'node_ogp_' . md5( $url ) );

			$html = node_blogcard_hydrate( node_embed_maybe_make_link( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $url ) );

			$this->assertStringContainsString( 'm3-blogcard--store-' . $slug, $html, $url );
			$this->assertStringContainsString( $store_name, $html, $url );
			$this->assertStringContainsString( 'm3-blogcard__overlay', $html, $url );
			$this->assertStringNotContainsString( 'm3-blogcard__fallback', $html, $url );
		}

		// 題名を復元できる URL ではスラッグ由来の題名を使う。
		$this->assertStringContainsString(
			'Forza Horizon 5',
			node_blogcard_hydrate( node_embed_maybe_make_link( '<a>x</a>', 'https://www.xbox.com/ja-JP/games/store/forza-horizon-5/9NKX70BBCDRN' ) )
		);

		remove_filter( 'pre_http_request', $callback, 10 );
	}

	public function test_non_store_url_still_falls_back_to_plain_link_when_blocked(): void {
		$url      = 'https://example.com/blocked-article';
		$calls    = 0;
		$callback = $this->mock_blocked_response( $calls );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$html = node_render_blogcard( $url );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertStringContainsString( 'm3-blogcard__fallback', $html );
	}

	public function test_failed_fetch_is_negatively_cached(): void {
		$url      = 'https://store-jp.nintendo.com/item/software/D70010000099999';
		$calls    = 0;
		$callback = $this->mock_blocked_response( $calls );
		delete_transient( 'node_ogp_' . md5( $url ) );

		node_render_blogcard( $url );
		node_render_blogcard( $url );
		node_render_blogcard( $url );

		remove_filter( 'pre_http_request', $callback, 10 );

		// 失敗レスポンスをキャッシュしないと、レンダーのたびに外部 HTTP が走り表示が遅延する。
		$this->assertSame( 1, $calls );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}

	public function test_ogp_request_uses_browser_user_agent(): void {
		$url        = 'https://store.playstation.com/ja-jp/product/UA-CHECK';
		$user_agent = '';
		$callback   = static function ( $preempt, $args, $request_url ) use ( &$user_agent ) {
			$user_agent = (string) ( $args['user-agent'] ?? '' );
			return new WP_Error( 'http_request_failed', 'stub' );
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );
		delete_transient( 'node_ogp_' . md5( $url ) );

		node_get_ogp_data( $url );

		remove_filter( 'pre_http_request', $callback, 10 );

		// ボット UA だとゲームストアの防御に 403 で弾かれる。
		$this->assertStringContainsString( 'Chrome/', $user_agent );
		$this->assertStringNotContainsString( 'LuminousCore', $user_agent );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}

	public function test_ogp_data_falls_back_to_json_ld_when_og_tags_are_missing(): void {
		$url      = 'https://store.steampowered.com/app/570/Dota_2/';
		$json_ld  = wp_json_encode(
			array(
				'@context'    => 'https://schema.org',
				'@graph'      => array(
					array( '@type' => 'BreadcrumbList' ),
					array(
						'@type'       => 'VideoGame',
						'name'        => 'JSON-LD Game Title',
						'description' => 'JSON-LD Game Description',
						'image'       => array( 'url' => 'https://cdn.example.com/header.jpg' ),
					),
				),
			)
		);
		$callback = static function ( $preempt, $args, $request_url ) use ( $url, $json_ld ) {
			if ( $request_url !== $url ) {
				return $preempt;
			}

			return array(
				'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
				'body'     => '<html><head><title>Steam</title><script type="application/ld+json">' . $json_ld . '</script></head><body></body></html>',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$ogp  = node_get_ogp_data( $url );
		$html = node_render_blogcard( $url );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertIsArray( $ogp );
		$this->assertSame( 'JSON-LD Game Title', $ogp['title'] );
		$this->assertSame( 'https://cdn.example.com/header.jpg', $ogp['image'] );
		$this->assertStringContainsString( 'JSON-LD Game Title', $html );
		$this->assertStringContainsString( 'JSON-LD Game Description', $html );
		$this->assertStringContainsString( 'm3-blogcard--store-steam', $html );
		// サイト名はストアの正式名へ正規化する。
		$this->assertStringContainsString( 'Steam', $html );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}

	public function test_ogp_data_reads_twitter_card_when_og_is_absent(): void {
		$url      = 'https://example.org/twitter-card-only';
		$callback = static function ( $preempt, $args, $request_url ) use ( $url ) {
			if ( $request_url !== $url ) {
				return $preempt;
			}

			return array(
				'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
				'body'     => '<html><head><meta name="twitter:title" content="Twitter Card Title"><meta name="twitter:description" content="Twitter Card Description"><meta name="twitter:image" content="/relative/card.jpg"></head><body></body></html>',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$ogp = node_get_ogp_data( $url );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertIsArray( $ogp );
		$this->assertSame( 'Twitter Card Title', $ogp['title'] );
		$this->assertSame( 'Twitter Card Description', $ogp['description'] );
		// 相対 URL の画像は絶対 URL へ補正する。
		$this->assertSame( 'https://example.org/relative/card.jpg', $ogp['image'] );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}

	public function test_store_card_uses_scraped_title_when_fetch_succeeds(): void {
		$url    = 'https://store-jp.nintendo.com/item/software/D70010000073404';
		$filter = $this->mock_ogp_response( $url, 'ゼルダの伝説', 'https://store-jp.nintendo.com/thumb.jpg' );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$html = node_render_blogcard( $url );

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertStringContainsString( 'ゼルダの伝説', $html );
		$this->assertStringContainsString( 'm3-blogcard--store-nintendo', $html );
		$this->assertStringContainsString( 'ニンテンドーストア', $html );
		// og:site_name（モックは Example Site）ではなくストア正式名を表示する。
		$this->assertStringNotContainsString( 'Example Site', $html );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}

	public function test_oembed_dataparse_marks_store_urls(): void {
		$html = node_blogcard_hydrate(
			node_oembed_dataparse(
				'<div>Original embed</div>',
				(object) array(
					'title'         => 'Store oEmbed Title',
					'provider_name' => 'Sony Interactive Entertainment',
				),
				'https://store.playstation.com/ja-jp/product/JP0082-PPSA01284_00-ASTROSBGDELUXE01'
			)
		);

		$this->assertStringContainsString( 'm3-blogcard--store-playstation', $html );
		$this->assertStringContainsString( 'PlayStation Store', $html );
		$this->assertStringContainsString( 'Store oEmbed Title', $html );
	}

	public function test_store_card_keeps_single_line_markup_through_wpautop(): void {
		$url      = 'https://store.steampowered.com/app/570/Dota_2/';
		$calls    = 0;
		$callback = $this->mock_blocked_response( $calls );
		delete_transient( 'node_ogp_' . md5( $url ) );

		$placeholder = node_embed_maybe_make_link( '<a>x</a>', $url );
		$final       = node_blogcard_hydrate( wpautop( "<h2>foo</h2>\n\n{$placeholder}\n\n<h2>bar</h2>" ) );

		remove_filter( 'pre_http_request', $callback, 10 );

		$this->assertStringContainsString( 'm3-blogcard--store-steam', $final );
		$this->assertStringNotContainsString( '<br', $final );
		$this->assertStringNotContainsString( 'node-blogcard-slot', $final );

		delete_transient( 'node_ogp_' . md5( $url ) );
	}
}
