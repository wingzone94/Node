<?php
/**
 * Theme-level action and filter registration.
 *
 * @package NodeTheme
 */

declare(strict_types=1);

namespace NodeTheme\Hooks;

/**
 * Registers custom hooks that are independent from template rendering.
 */
final class CustomHooks {
	private const DEFAULT_SEED_COLOR = '#FF9900';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_filter( 'locale', array( self::class, 'forceFrontendLocaleToJapanese' ) );
		add_action( 'after_setup_theme', array( self::class, 'initializeSeedColor' ) );
	}

	/**
	 * Keep the public theme locale Japanese while respecting wp-admin preferences.
	 *
	 * @param string $locale Current WordPress locale.
	 */
	public static function forceFrontendLocaleToJapanese( string $locale ): string {
		if ( is_admin() ) {
			return $locale;
		}

		return 'ja';
	}

	/**
	 * Initialize the dynamic-color seed when no value has been stored yet.
	 */
	public static function initializeSeedColor(): void {
		if ( get_option( '_node_seed_color' ) ) {
			return;
		}

		update_option( '_node_seed_color', self::extractSeedColor() );
	}

	/**
	 * Resolve the configured dynamic-color seed.
	 */
	private static function extractSeedColor(): string {
		$seed = get_option( '_node_seed_color' );

		return is_string( $seed ) && '' !== $seed ? $seed : self::DEFAULT_SEED_COLOR;
	}
}
