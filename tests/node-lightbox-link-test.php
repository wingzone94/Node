<?php
/**
 * Tests for automatic lightbox image linking.
 *
 * @package Node
 */

require_once dirname( __DIR__ ) . '/inc/hooks.php';

class Node_Lightbox_Link_Test extends WP_UnitTestCase {

	public function test_upload_image_is_eligible_for_auto_lightbox(): void {
		$uploads = wp_upload_dir( null, false );
		$url     = trailingslashit( $uploads['baseurl'] ) . '2026/08/example.jpg';

		$this->assertTrue( luminous_should_auto_lightbox_image( $url ) );
	}

	public function test_external_image_is_not_eligible_for_auto_lightbox(): void {
		$this->assertFalse( luminous_should_auto_lightbox_image( 'https://luminous-core.net/wp-content/uploads/2026/08/example.jpg' ) );
		$this->assertFalse( luminous_should_auto_lightbox_image( 'https://example.com/example.jpg' ) );
	}

	public function test_auto_lightbox_leaves_external_images_unwrapped(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$image = '<img src="https://luminous-core.net/wp-content/uploads/2026/08/example.jpg" alt="">';

		$this->assertSame( $image, luminous_auto_image_lightbox_link( $image ) );
	}

	public function test_auto_lightbox_wraps_local_upload_images(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$uploads = wp_upload_dir( null, false );
		$url     = trailingslashit( $uploads['baseurl'] ) . '2026/08/example.jpg';
		$image   = '<img src="' . esc_url( $url ) . '" alt="">';

		$this->assertSame(
			'<a href="' . esc_url( $url ) . '" class="m3-lightbox-link">' . $image . '</a>',
			luminous_auto_image_lightbox_link( $image )
		);
	}
}
