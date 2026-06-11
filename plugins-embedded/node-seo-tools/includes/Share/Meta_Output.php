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
		$ogp_url = $this->resolve_ogp_image_url( $post_id );
		if ( '' === $ogp_url ) {
			return;
		}

		$title          = get_the_title( $post_id );
		$desc           = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		$url            = get_permalink( $post_id );
		$twitter_site   = (string) apply_filters( 'node_seo_twitter_site', '@Luminous_Core_' );
		$twitter_domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$card_type      = 'summary_large_image';

		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:locale" content="ja_JP" />' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
		if ( str_starts_with( $ogp_url, 'https://' ) ) {
			echo '<meta property="og:image:secure_url" content="' . esc_url( $ogp_url ) . '" />' . "\n";
		}
		echo '<meta property="og:image:width" content="1200" />' . "\n";
		echo '<meta property="og:image:height" content="630" />' . "\n";
		echo '<meta property="og:image:type" content="image/png" />' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
		echo '<meta property="twitter:card" content="' . esc_attr( $card_type ) . '" />' . "\n";
		if ( '' !== $twitter_site ) {
			echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '" />' . "\n";
		}
		if ( is_string( $twitter_domain ) && '' !== $twitter_domain ) {
			echo '<meta name="twitter:domain" content="' . esc_attr( $twitter_domain ) . '" />' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		echo '<meta name="twitter:image" content="' . esc_url( $ogp_url ) . '" />' . "\n";
		echo '<meta name="twitter:image:src" content="' . esc_url( $ogp_url ) . '" />' . "\n";
		echo '<meta name="twitter:image:alt" content="' . esc_attr( $title ) . '" />' . "\n";
	}

	/**
	 * Resolve a cache-busted HTTPS OGP image URL for SNS crawlers.
	 */
	private function resolve_ogp_image_url( int $post_id ): string {
		$url = get_post_meta( $post_id, '_node_ogp_image_url', true );
		if ( ! is_string( $url ) || '' === $url ) {
			$upload_dir = wp_upload_dir();
			$filepath   = trailingslashit( $upload_dir['basedir'] ) . 'ogp/ogp-' . $post_id . '.png';
			if ( ! is_file( $filepath ) ) {
				return '';
			}
			$url = trailingslashit( $upload_dir['baseurl'] ) . 'ogp/ogp-' . $post_id . '.png';
		}

		$url = set_url_scheme( $url, 'https' );

		$mtime = (int) get_post_meta( $post_id, '_node_ogp_image_mtime', true );
		if ( $mtime <= 0 ) {
			$path = $this->url_to_upload_path( $url );
			if ( '' !== $path && is_file( $path ) ) {
				$mtime = (int) filemtime( $path );
			}
		}

		if ( $mtime > 0 ) {
			$url = add_query_arg( 'v', (string) $mtime, $url );
		}

		return $url;
	}

	private function url_to_upload_path( string $url ): string {
		$upload_dir = wp_upload_dir();
		$baseurl    = trailingslashit( $upload_dir['baseurl'] );
		$basedir    = trailingslashit( $upload_dir['basedir'] );

		if ( ! str_starts_with( $url, $baseurl ) ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $path ) || '' === $path ) {
				return '';
			}
			$relative = ltrim( $path, '/' );
			$uploads  = ltrim( wp_parse_url( $baseurl, PHP_URL_PATH ) ?? '', '/' );
			if ( '' !== $uploads && str_starts_with( $relative, $uploads ) ) {
				$relative = ltrim( substr( $relative, strlen( $uploads ) ), '/' );
			}
			return $basedir . $relative;
		}

		return $basedir . ltrim( substr( $url, strlen( $baseurl ) ), '/' );
	}
}
