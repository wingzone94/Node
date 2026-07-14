<?php
/**
 * Node Utility プラグインのPHPUnitテスト用ブートストラップ。
 *
 * WP本体は、開発用LocalWP環境（cybernode.local）の実際のコアファイルをそのまま使い、
 * DB接続先だけ専用のテストDB（wordpress_test）に向ける（tests/wp-tests-config.php参照）。
 */

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		$theme_dir = dirname( __DIR__ );

		add_filter( 'template_directory', static fn() => $theme_dir );
		add_filter( 'stylesheet_directory', static fn() => $theme_dir );
		add_filter( 'template_directory_uri', static fn() => 'http://example.org/wp-content/themes/node' );
		add_filter( 'stylesheet_directory_uri', static fn() => 'http://example.org/wp-content/themes/node' );
	}
);

require dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
