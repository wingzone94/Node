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
		require dirname( __DIR__ ) . '/inc/blogcard.php';
		require dirname( __DIR__ ) . '/plugins-embedded/node-series/node-series.php';
	}
);

require dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
