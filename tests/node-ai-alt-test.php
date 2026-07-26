<?php
/**
 * アイキャッチ alt 自動付与の自動テスト
 *
 * @package Node_AI_Tools
 */

class Node_AI_Alt_Test extends WP_UnitTestCase {

	private $post_id;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'node_ai_set_image_alt' ) ) {
			require_once dirname( __DIR__ ) . '/plugins-embedded/node-ai-tools/includes/alt-text.php';
		}

		$this->post_id = $this->factory->post->create();
	}





	// --- 1.3: アイキャッチ alt の自動付与 ---

	public function test_set_image_alt_does_not_overwrite_existing() {
		$attachment_id = $this->factory->attachment->create_object(
			array( 'file' => 'a.jpg', 'post_mime_type' => 'image/jpeg' )
		);

		$this->assertTrue( node_ai_set_image_alt( $attachment_id, '最初の説明' ) );
		$this->assertSame( '最初の説明', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		// 人が書いた alt を勝手に書き換えない
		$again = node_ai_set_image_alt( $attachment_id, '別の説明' );
		$this->assertWPError( $again );
		$this->assertSame( 'alt_exists', $again->get_error_code() );
		$this->assertSame( '最初の説明', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		// 明示的な force のときだけ上書きできる
		$this->assertTrue( node_ai_set_image_alt( $attachment_id, '上書き', true ) );
		$this->assertSame( '上書き', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	public function test_set_image_alt_rejects_invalid_input() {
		$this->assertWPError( node_ai_set_image_alt( 0, 'text' ) );

		$attachment_id = $this->factory->attachment->create_object(
			array( 'file' => 'b.jpg', 'post_mime_type' => 'image/jpeg' )
		);
		$this->assertWPError( node_ai_set_image_alt( $attachment_id, '   ' ) );
	}

	public function test_auto_alt_is_scheduled_only_when_alt_is_missing() {
		update_option( 'node_ai_auto_alt', '1' );

		$attachment_id = $this->factory->attachment->create_object(
			array( 'file' => 'hero.jpg', 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $this->post_id, $attachment_id );

		$post = get_post( $this->post_id );

		// alt が無い → 予約される
		node_ai_maybe_schedule_alt_generation( $this->post_id, $post );
		$this->assertNotFalse( wp_next_scheduled( 'node_ai_auto_alt', array( $attachment_id ) ) );

		// 予約を消し、alt を入れると予約されない
		wp_clear_scheduled_hook( 'node_ai_auto_alt', array( $attachment_id ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', '人が書いた説明' );

		node_ai_maybe_schedule_alt_generation( $this->post_id, $post );
		$this->assertFalse( wp_next_scheduled( 'node_ai_auto_alt', array( $attachment_id ) ) );
	}

	public function test_auto_alt_is_not_scheduled_when_disabled() {
		update_option( 'node_ai_auto_alt', '0' );

		$attachment_id = $this->factory->attachment->create_object(
			array( 'file' => 'hero2.jpg', 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $this->post_id, $attachment_id );

		node_ai_maybe_schedule_alt_generation( $this->post_id, get_post( $this->post_id ) );
		$this->assertFalse( wp_next_scheduled( 'node_ai_auto_alt', array( $attachment_id ) ) );

		update_option( 'node_ai_auto_alt', '1' );
	}


}
