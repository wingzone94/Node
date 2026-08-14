<?php

declare(strict_types=1);
/**
 * SPOTLIGHT表示データのEngine APIブリッジ。
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SPOTLIGHTカテゴリの表示データを返す。
 *
 * @return array<int, array{name: string, slug: string, url: string, color: string, count: int, description: string}>
 */
function node_get_spotlight_categories(): array {
	$engine_api = 'LuminousCore\\Engine\\get_spotlight_categories';

	if ( ! function_exists( $engine_api ) ) {
		return array();
	}

	return $engine_api();
}
