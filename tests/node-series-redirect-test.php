<?php
/**
 * 旧カテゴリ/タグからシリーズへの移行301テスト。
 *
 * @package Node_Series
 */

class Node_Series_Redirect_Test extends WP_UnitTestCase {
	private string $original_request_uri = '';

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		delete_option( 'node_series_redirect_map' );
	}

	public function tear_down(): void {
		delete_option( 'node_series_redirect_map' );
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function create_term( string $taxonomy, string $name, string $slug ): WP_Term {
		$term_id = self::factory()->term->create(
			[
				'taxonomy' => $taxonomy,
				'name'     => $name,
				'slug'     => $slug,
			]
		);

		return get_term( $term_id, $taxonomy );
	}

	private function configure_redirect( WP_Term $legacy_term, WP_Term $series_term ): void {
		update_option(
			'node_series_redirect_map',
			[
				$legacy_term->taxonomy . ':' . $legacy_term->slug => $series_term->slug,
			]
		);
	}

	public function test_category_redirects_to_series_base(): void {
		$legacy = $this->create_term( 'category', '遊戯王旧', 'yugioh-old' );
		$series = $this->create_term( 'node_series', '遊戯王', 'yugioh' );
		$this->configure_redirect( $legacy, $series );

		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( $legacy, '/category/yugioh-old/' )
		);
	}

	public function test_paged_category_redirects_to_series_base_without_page_number(): void {
		$legacy = $this->create_term( 'category', '旧カテゴリ', 'legacy-category' );
		$series = $this->create_term( 'node_series', '移行先', 'target-series' );
		$this->configure_redirect( $legacy, $series );

		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( null, '/category/legacy-category/page/2/' )
		);
	}

	public function test_category_feed_redirects_to_series_feed(): void {
		$legacy = $this->create_term( 'category', '旧feed', 'legacy-feed' );
		$series = $this->create_term( 'node_series', '移行先feed', 'target-feed' );
		$this->configure_redirect( $legacy, $series );

		$this->assertSame(
			get_term_feed_link( $series->term_id, 'node_series' ),
			Node_Series::resolve_legacy_redirect( $legacy, '/category/legacy-feed/feed/' )
		);
	}

	public function test_tag_redirects_to_series_base(): void {
		$legacy = $this->create_term( 'post_tag', '旧タグ', 'legacy-tag' );
		$series = $this->create_term( 'node_series', 'タグ移行先', 'tag-series' );
		$this->configure_redirect( $legacy, $series );

		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( $legacy, '/tag/legacy-tag/' )
		);
	}

	public function test_encoded_japanese_category_slug_redirects(): void {
		$legacy = $this->create_term( 'category', '日本語旧カテゴリ', '遊戯王旧' );
		$series = $this->create_term( 'node_series', '日本語シリーズ', '遊戯王' );
		$this->configure_redirect( $legacy, $series );

		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( null, '/category/' . rawurlencode( '遊戯王旧' ) . '/' )
		);
	}

	public function test_query_string_is_preserved(): void {
		$legacy = $this->create_term( 'category', '計測元', 'tracking-source' );
		$series = $this->create_term( 'node_series', '計測先', 'tracking-target' );
		$this->configure_redirect( $legacy, $series );
		$target = get_term_link( $series );

		$this->assertSame(
			$target . ( str_contains( $target, '?' ) ? '&' : '?' ) . 'utm_source=migration&utm_medium=archive',
			Node_Series::resolve_legacy_redirect(
				$legacy,
				'/category/tracking-source/?utm_source=migration&utm_medium=archive'
			)
		);
	}

	public function test_deleted_legacy_term_still_redirects_via_map(): void {
		$series = $this->create_term( 'node_series', '移行先', 'orphan-target-series' );
		update_option(
			'node_series_redirect_map',
			[ 'category:removed-legacy' => $series->slug ]
		);

		// 旧termは作成しない＝削除済みと同じ状態。queried objectも無い（404）。
		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( null, '/category/removed-legacy/' )
		);
		$this->assertSame(
			get_term_link( $series ),
			Node_Series::resolve_legacy_redirect( null, '/category/removed-legacy/page/3/' )
		);
	}

	public function test_unmapped_term_is_not_redirected(): void {
		$legacy = $this->create_term( 'category', '未登録', 'unmapped-category' );

		$this->assertNull( Node_Series::resolve_legacy_redirect( $legacy, '/category/unmapped-category/' ) );
	}

	public function test_missing_target_series_is_not_redirected(): void {
		$legacy = $this->create_term( 'category', '削除済み移行先', 'missing-target-source' );
		update_option(
			'node_series_redirect_map',
			[ 'category:' . $legacy->slug => 'deleted-series' ]
		);

		$this->assertNull( Node_Series::resolve_legacy_redirect( $legacy, '/category/missing-target-source/' ) );
	}

	public function test_non_legacy_taxonomy_and_non_term_are_not_redirected(): void {
		$series = $this->create_term( 'node_series', '自身', 'self-series' );
		update_option( 'node_series_redirect_map', [ 'node_series:self-series' => 'self-series' ] );

		$this->assertNull( Node_Series::resolve_legacy_redirect( $series, '/series/self-series/' ) );
		$this->assertNull( Node_Series::resolve_legacy_redirect( (object) [ 'slug' => 'legacy' ], '/legacy/' ) );
	}

	public function test_admin_context_does_not_read_map_or_redirect(): void {
		set_current_screen( 'dashboard' );
		$option_reads = 0;
		$filter       = static function ( $value ) use ( &$option_reads ) {
			++$option_reads;
			return $value;
		};
		add_filter( 'pre_option_node_series_redirect_map', $filter );

		Node_Series::instance()->handle_legacy_redirect();

		remove_filter( 'pre_option_node_series_redirect_map', $filter );
		$this->assertSame( 0, $option_reads );
	}

	public function test_hook_runs_after_spotlight_redirect(): void {
		$this->assertSame( 11, has_action( 'template_redirect', [ Node_Series::instance(), 'handle_legacy_redirect' ] ) );
		$this->assertSame( 10, has_action( 'template_redirect', 'node_redirect_spotlight_category_archive' ) );
	}
}
