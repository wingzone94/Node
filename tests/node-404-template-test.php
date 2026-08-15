<?php
/**
 * 404テンプレートとrobots設定の自動テスト。
 *
 * @package Node
 */

class Node_404_Template_Test extends WP_UnitTestCase {
	private string $original_blog_public;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->original_blog_public = (string) get_option( 'blog_public', '1' );
		update_option( 'blog_public', '1' );
	}

	public function tear_down(): void {
		update_option( 'blog_public', $this->original_blog_public );
		parent::tear_down();
	}

	public function test_unknown_url_is_404(): void {
		$this->go_to( '/definitely-404-xyz/' );

		$this->assertTrue( is_404() );
	}

	public function test_wordpress_resolves_the_new_404_template(): void {
		$this->go_to( '/definitely-404-xyz/' );
		$template = get_404_template();

		$this->assertSame( realpath( dirname( __DIR__ ) . '/404.php' ), realpath( $template ) );
	}

	public function test_all_articles_fallback_uses_404_template_before_rendering_header(): void {
		$template = (string) file_get_contents( dirname( __DIR__ ) . '/template-parts/all-articles.php' );
		$include  = strpos( $template, 'include get_404_template();' );
		$header   = strpos( $template, 'get_header();' );

		$this->assertNotFalse( $include );
		$this->assertNotFalse( $header );
		$this->assertLessThan( $header, $include );
	}

	public function test_404_template_renders_search_links_and_breadcrumb_without_fatal(): void {
		$this->go_to( '/definitely-404-render-test/' );

		ob_start();
		include get_404_template();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'role="search"', $html );
		$this->assertStringContainsString( esc_url( home_url( '/' ) ), $html );
		$this->assertStringContainsString( 'ページが見つかりませんでした', $html );
	}

	public function test_404_robots_include_noindex_and_nofollow(): void {
		$this->go_to( '/definitely-404-xyz/' );
		$robots = apply_filters( 'wp_robots', array() );

		$this->assertArrayHasKey( 'noindex', $robots );
		$this->assertTrue( $robots['noindex'] );
		$this->assertArrayHasKey( 'nofollow', $robots );
		$this->assertTrue( $robots['nofollow'] );
	}

	public function test_normal_page_robots_do_not_include_noindex(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Ordinary Page',
			)
		);
		$this->go_to( get_permalink( $page_id ) );
		$robots = apply_filters( 'wp_robots', array() );

		$this->assertFalse( is_404() );
		$this->assertArrayNotHasKey( 'noindex', $robots );
	}
}
