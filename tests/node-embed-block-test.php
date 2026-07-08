<?php
/**
 * plugins-embedded/luminous-blocks/includes/blocks/embed.php（node/embed ブロック）の自動テスト。
 *
 * @package Node
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/luminous-blocks/includes/blocks/embed.php';

class Node_Embed_Block_Test extends WP_UnitTestCase {

	public function test_x_url_renders_native_embed(): void {
		$html = node_render_embed_block( array( 'url' => 'https://x.com/jack/status/20' ) );

		$this->assertStringContainsString( 'node-embed--x', $html );
		$this->assertStringContainsString( 'twitter-tweet', $html );
		$this->assertStringContainsString( 'https://x.com/jack/status/20', $html );
	}

	public function test_youtube_url_renders_iframe(): void {
		$html = node_render_embed_block( array( 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );

		$this->assertStringContainsString( 'node-embed--video', $html );
		$this->assertStringContainsString( 'https://www.youtube.com/embed/dQw4w9WgXcQ', $html );
	}

	public function test_google_maps_url_renders_iframe(): void {
		$html = node_render_embed_block( array( 'url' => 'https://www.google.com/maps/place/%E6%9D%B1%E4%BA%AC%E9%A7%85/@35.681236,139.767125,15z' ) );

		$this->assertStringContainsString( 'node-embed--map', $html );
		$this->assertStringContainsString( 'output=embed', $html );
	}

	public function test_unsupported_url_falls_back_to_plain_link(): void {
		$html = node_render_embed_block( array( 'url' => 'https://example.com/article' ) );

		$this->assertStringContainsString( '<a href="https://example.com/article"', $html );
		$this->assertStringNotContainsString( 'node-embed', $html );
	}

	public function test_empty_url_renders_nothing(): void {
		$this->assertSame( '', node_render_embed_block( array() ) );
		$this->assertSame( '', node_render_embed_block( array( 'url' => '' ) ) );
	}
}
