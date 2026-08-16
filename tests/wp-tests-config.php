<?php
/**
 * WPテストスイート用のDB設定。
 *
 * 既定は開発用の LocalWP（cybernode.local）が起動する MySQL ソケット。
 * ソケットパスは環境変数 NODE_TEST_DB_SOCKET で上書きできる。
 *
 * CI（GitHub Actions）のようにサービスコンテナへ TCP で接続する環境では
 * NODE_TEST_DB_HOST に `127.0.0.1:3306` 形式を渡す。こちらが指定された場合は
 * ソケット探索を行わない。Linux では DB_HOST が `localhost` だと mysqli が
 * UNIX ソケットへ接続しにいき、サービスコンテナに繋がらないため。
 */

$node_test_db_host   = getenv( 'NODE_TEST_DB_HOST' );
$node_test_db_socket = '';

if ( ! $node_test_db_host ) {
	$node_test_db_socket = getenv( 'NODE_TEST_DB_SOCKET' );

	if ( ! $node_test_db_socket ) {
		$matches             = glob( getenv( 'HOME' ) . '/Library/Application Support/Local/run/*/mysql/mysqld.sock' );
		$node_test_db_socket = $matches[0] ?? '';
	}
}

define( 'DB_NAME', getenv( 'NODE_TEST_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'NODE_TEST_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'NODE_TEST_DB_PASSWORD' ) ?: 'root' );
if ( $node_test_db_host ) {
	define( 'DB_HOST', $node_test_db_host );
} else {
	define( 'DB_HOST', $node_test_db_socket ? ( 'localhost:' . $node_test_db_socket ) : 'localhost' );
}
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'cybernode.test' );
define( 'WP_TESTS_EMAIL', 'admin@cybernode.test' );
define( 'WP_TESTS_TITLE', 'Node Theme Test Suite' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define(
	'ABSPATH',
	rtrim(
		getenv( 'NODE_TEST_WP_CORE_DIR' ) ?: ( getenv( 'HOME' ) . '/Local Sites/cybernode/app/public' ),
		'/'
	) . '/'
);
