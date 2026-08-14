<?php
/**
 * 印刷機能（inc/print.php）の自動テスト。Node 1.3 第5段階。
 *
 * @package Luminous_Core
 */

class Node_Print_Test extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( NODE_PRINT_OPTION_ENABLED );
		delete_option( NODE_PRINT_OPTION_POSITION );
		delete_option( NODE_PRINT_OPTION_SHOW_META );
		parent::tear_down();
	}

	// --- 設定の既定値と検証 ---------------------------------------------------

	public function test_enabled_by_default(): void {
		$this->assertTrue( node_print_is_enabled() );
	}

	public function test_can_be_disabled(): void {
		update_option( NODE_PRINT_OPTION_ENABLED, '0' );
		$this->assertFalse( node_print_is_enabled() );
	}

	public function test_button_position_defaults_to_end(): void {
		$this->assertSame( 'end', node_print_button_position() );
	}

	public function test_button_position_rejects_unknown_value(): void {
		update_option( NODE_PRINT_OPTION_POSITION, 'sidebar' );
		$this->assertSame( 'end', node_print_button_position() );

		update_option( NODE_PRINT_OPTION_POSITION, 'start' );
		$this->assertSame( 'start', node_print_button_position() );
	}

	public function test_show_meta_defaults_to_on(): void {
		$this->assertTrue( node_print_show_meta() );

		update_option( NODE_PRINT_OPTION_SHOW_META, '0' );
		$this->assertFalse( node_print_show_meta() );
	}

	// --- 印刷ボタン -----------------------------------------------------------

	public function test_button_html_calls_window_print(): void {
		$html = node_print_get_button_html();

		$this->assertStringContainsString( 'window.print()', $html );
		$this->assertStringContainsString( 'm3-share-btn--print', $html );
		$this->assertStringContainsString( 'type="button"', $html );
	}

	// --- 印刷専用要素（ヘッダー / フッター） ----------------------------------

	public function test_print_header_outputs_blog_name(): void {
		ob_start();
		node_print_the_header();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'm3-print-only', $html );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $html );
	}

	public function test_print_header_suppressed_when_disabled(): void {
		update_option( NODE_PRINT_OPTION_ENABLED, '0' );

		ob_start();
		node_print_the_header();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test_print_footer_outputs_permalink_and_copyright(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		node_print_the_footer();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'm3-print-footer', $html );
		$this->assertStringContainsString( get_permalink( $post_id ), $html );
		$this->assertStringContainsString( '&copy;', $html );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $html );
	}

	public function test_print_footer_suppressed_when_meta_off(): void {
		update_option( NODE_PRINT_OPTION_SHOW_META, '0' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		node_print_the_footer();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test_print_footer_suppressed_when_disabled(): void {
		update_option( NODE_PRINT_OPTION_ENABLED, '0' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		node_print_the_footer();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}
}
