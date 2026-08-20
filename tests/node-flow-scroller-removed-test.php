<?php
/**
 * 無限スクロール（Node Flow のハイブリッド・スクローラー）廃止の回帰網。
 *
 * トップ・アーカイブ・検索で標準のページ送りを隠し、スクロール末尾で次ページを
 * 追記していたため、モバイルでは一覧が終わらず目的の記事へ辿り着けなかった。
 * 1.3.1 で同梱プラグインから削除し、テーマ側でもスクリプトを落とすようにしたので、
 * どちらかが戻っていないかをここで見張る。
 *
 * @package Node
 */

if ( ! function_exists( 'node_disable_infinite_scroll' ) ) {
	require_once dirname( __DIR__ ) . '/inc/hooks.php';
}

class Node_Flow_Scroller_Removed_Test extends WP_UnitTestCase {

	private function theme_path( string $relative ): string {
		return dirname( __DIR__ ) . '/' . $relative;
	}

	public function test_scroller_class_is_gone(): void {
		$this->assertFileDoesNotExist(
			$this->theme_path( 'plugins-embedded/node-flow/includes/Frontend/Scroller.php' ),
			'無限スクロールの実装（Scroller）は 1.3.1 で削除済み'
		);
	}

	public function test_scroller_script_is_gone(): void {
		$this->assertFileDoesNotExist(
			$this->theme_path( 'plugins-embedded/node-flow/assets/js/scroller.js' ),
			'無限スクロールのフロント側スクリプトは 1.3.1 で削除済み'
		);
	}

	public function test_node_flow_plugin_no_longer_boots_the_scroller(): void {
		$plugin = file_get_contents( $this->theme_path( 'plugins-embedded/node-flow/includes/Core/Plugin.php' ) );

		$this->assertStringNotContainsString(
			'Scroller',
			$plugin,
			'Node Flow の起動処理からスクローラーが外れている'
		);
	}

	/**
	 * 単体プラグインとして旧版の node-flow が有効な環境でも、
	 * テーマ側でスクリプトを落として無限スクロールを止められること。
	 */
	public function test_theme_drops_a_registered_scroller_handle(): void {
		wp_register_script( 'node-flow-scroller', 'http://example.org/scroller.js', array(), '1.2.0', true );
		wp_enqueue_script( 'node-flow-scroller' );

		$this->assertTrue( wp_script_is( 'node-flow-scroller', 'enqueued' ), '前提: いったんは積まれている' );

		node_disable_infinite_scroll();

		$this->assertFalse( wp_script_is( 'node-flow-scroller', 'enqueued' ), 'enqueue が解除される' );
		$this->assertFalse( wp_script_is( 'node-flow-scroller', 'registered' ), '登録ごと落ちる' );
	}
}
