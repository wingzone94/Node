<?php
/**
 * フッター固定ページリンクの404抑止テスト。
 *
 * @package Node
 */

class Node_Footer_Links_Test extends WP_UnitTestCase {

	private function make_page( string $slug, string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => $title,
			)
		);
	}

	public function test_existing_page_url_returns_only_published_page(): void {
		$this->assertSame( '', node_get_existing_page_url( '/privacy-policy/' ) );

		$this->make_page( 'privacy-policy', 'プライバシーポリシー' );

		$this->assertSame( home_url( '/privacy-policy/' ), node_get_existing_page_url( '/privacy-policy/' ) );
	}

	public function test_footer_fallback_omits_missing_fixed_pages(): void {
		ob_start();
		node_footer_menu_fallback();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( home_url( '/' ), $html );
		$this->assertStringNotContainsString( home_url( '/privacy-policy/' ), $html );
		$this->assertStringNotContainsString( home_url( '/contact/' ), $html );
	}

	public function test_footer_fallback_outputs_existing_fixed_pages(): void {
		$this->make_page( 'privacy-policy', 'プライバシーポリシー' );
		$this->make_page( 'contact', 'お問い合わせ' );

		ob_start();
		node_footer_menu_fallback();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( home_url( '/privacy-policy/' ), $html );
		$this->assertStringContainsString( home_url( '/contact/' ), $html );
	}

	public function test_placeholder_menu_items_are_removed_when_fixed_pages_are_missing(): void {
		$items = array(
			(object) array(
				'url'   => home_url( '/sample-page/' ),
				'title' => 'プライバシーポリシー',
			),
			(object) array(
				'url'   => home_url( '/post-0-2/' ),
				'title' => 'お問い合わせ',
			),
		);

		$filtered = node_fix_footer_menu_placeholder_urls( $items, (object) array( 'theme_location' => 'footer' ) );

		$this->assertSame( array(), $filtered );
	}

	public function test_placeholder_menu_items_are_rewritten_when_fixed_pages_exist(): void {
		$this->make_page( 'privacy-policy', 'プライバシーポリシー' );
		$this->make_page( 'contact', 'お問い合わせ' );

		$items = array(
			(object) array(
				'url'   => home_url( '/sample-page/' ),
				'title' => 'プライバシーポリシー',
			),
			(object) array(
				'url'   => home_url( '/post-0-2/' ),
				'title' => 'お問い合わせ',
			),
		);

		$filtered = node_fix_footer_menu_placeholder_urls( $items, (object) array( 'theme_location' => 'footer' ) );

		$this->assertCount( 2, $filtered );
		$this->assertSame( home_url( '/privacy-policy/' ), $filtered[0]->url );
		$this->assertSame( home_url( '/contact/' ), $filtered[1]->url );
	}
}
