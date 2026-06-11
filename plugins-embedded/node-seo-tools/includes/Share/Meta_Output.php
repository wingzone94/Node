<?php
/**
 * OGP / Twitter Card meta output.
 *
 * @package Node_SEO_Tools
 */

namespace Node\SEO\Tools\Share;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta_Output {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'inject_ogp_tags' ), 5 );
	}

	public function inject_ogp_tags(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$ogp_url = get_post_meta( $post_id, '_node_ogp_image_url', true );
		if ( ! $ogp_url ) {
			return;
		}

		$title = get_the_title( $post_id );
		$desc  = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		$url   = get_permalink( $post_id );

		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
		echo '<meta property="og:image:width" content="1200" />' . "\n";
		echo '<meta property="og:image:height" content="630" />' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		echo '<meta name="twitter:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
	}
}
