<?php
/**
 * 未設定の広告ひな形が空の破線ボックスとして残らないことのテスト。
 *
 * template-parts/ad-*.php は「ここに AdSense のコードを貼る」用のひな形で、
 * 貼る前は HTML コメントしか入っていない。それでも .m3-ad-area の破線ボックスは
 * 描画されるため、フッター直前に空枠だけが残っていた（2026-08-21 実機録画で確認）。
 * 読み終えた読者が次の行き先を探す位置なので、回遊の妨げになる。
 *
 * @package Node
 */

if ( ! function_exists( 'node_the_ad_template' ) ) {
	require_once dirname( __DIR__ ) . '/inc/utilities.php';
}

class Node_Ad_Placeholder_Test extends WP_UnitTestCase {

	/**
	 * テーマ側のひな形を差し替えるための一時ディレクトリ。
	 */
	private ?string $stylesheet_dir = null;

	public function tear_down() {
		if ( null !== $this->stylesheet_dir ) {
			remove_all_filters( 'stylesheet_directory' );
			remove_all_filters( 'template_directory' );
			$this->stylesheet_dir = null;
		}
		parent::tear_down();
	}

	/**
	 * 任意の中身を持つ template-parts/ad-{$slug}.php を用意し、テーマとして見せる。
	 */
	private function stub_ad_template( string $slug, string $body ): void {
		$dir = get_temp_dir() . 'node-ad-' . wp_generate_password( 8, false );
		wp_mkdir_p( $dir . '/template-parts' );
		file_put_contents( $dir . '/template-parts/ad-' . $slug . '.php', $body );

		$this->stylesheet_dir = $dir;
		$to_dir               = static function () use ( $dir ) {
			return $dir;
		};
		add_filter( 'stylesheet_directory', $to_dir );
		add_filter( 'template_directory', $to_dir );
	}

	private function render( string $slug ): string {
		ob_start();
		node_the_ad_template( $slug );
		return (string) ob_get_clean();
	}

	public function test_placeholder_without_ad_code_outputs_nothing() {
		$this->stub_ad_template(
			'article',
			'<div class="m3-ad-area m3-ad-area--article">' .
			'<div class="m3-ad-placeholder"><!-- ここに AdSense のコードを貼り付けてください。 --></div>' .
			'</div>'
		);

		$this->assertSame( '', $this->render( 'article' ) );
	}

	public function test_pasted_adsense_tag_is_still_output() {
		$this->stub_ad_template(
			'article',
			'<div class="m3-ad-area m3-ad-area--article">' .
			'<ins class="adsbygoogle" data-ad-client="ca-pub-000"></ins>' .
			'</div>'
		);

		$markup = $this->render( 'article' );

		$this->assertStringContainsString( 'adsbygoogle', $markup );
		$this->assertStringContainsString( 'm3-ad-area--article', $markup );
	}

	public function test_visible_text_is_still_output() {
		$this->stub_ad_template(
			'infeed',
			'<div class="m3-ad-area m3-ad-area--infeed">PR</div>'
		);

		$this->assertStringContainsString( 'PR', $this->render( 'infeed' ) );
	}

	public function test_missing_template_outputs_nothing() {
		$this->stub_ad_template( 'article', '<!-- 未設定 -->' );

		$this->assertSame( '', $this->render( 'does-not-exist' ) );
	}

	/**
	 * 同梱のひな形そのものが「空」と判定されること。
	 * ひな形にうっかり見えるテキストを足すと空枠が復活するので、それを防ぐ。
	 */
	public function test_shipped_templates_are_treated_as_empty() {
		foreach ( array( 'article', 'infeed' ) as $slug ) {
			$this->assertSame(
				'',
				$this->render_shipped( $slug ),
				"同梱の ad-{$slug}.php が空判定になっていない"
			);
		}
	}

	private function render_shipped( string $slug ): string {
		$file = dirname( __DIR__ ) . '/template-parts/ad-' . $slug . '.php';
		$this->assertFileExists( $file );

		$this->stub_ad_template( $slug, (string) file_get_contents( $file ) );

		return $this->render( $slug );
	}
}
