<?php
/**
 * Node Connect X投稿メタボックス。
 *
 * 記事編集画面に、テンプレート置換後の投稿文プレビュー・Web Intent での手動投稿・
 * 自動投稿の除外チェック・投稿済み状態を表示する。
 *
 * @package Node_Connect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box(
			'node_connect_x_post',
			'X投稿（Node Connect）',
			'node_connect_render_x_meta_box',
			'post',
			'side',
			'default'
		);
	}
);

/**
 * メタボックス描画。
 */
function node_connect_render_x_meta_box( WP_Post $post ): void {
	$skip      = '1' === (string) get_post_meta( $post->ID, Node_Connect_X_Poster::SKIP_META, true );
	$posted_at = (int) get_post_meta( $post->ID, Node_Connect_X_Poster::POSTED_META, true );
	$preview   = Node_Connect_X_Poster::render_template( Node_Connect_X_Poster::get_template(), $post );

	$intent_url = 'https://twitter.com/intent/tweet?text=' . rawurlencode( $preview );

	wp_nonce_field( 'node_connect_x_meta', 'node_connect_x_meta_nonce' );
	?>
	<?php if ( $posted_at > 0 ) : ?>
		<p style="margin-top: 0;">自動投稿済み（<?php echo esc_html( wp_date( 'Y-m-d H:i', $posted_at ) ); ?>）</p>
	<?php elseif ( Node_Connect_X_Poster::is_enabled() ) : ?>
		<p style="margin-top: 0;">この記事の新規公開時に、下のプレビュー内容で自動投稿されます。</p>
	<?php else : ?>
		<p style="margin-top: 0;">自動投稿は無効です（設定 → 外部連携で有効化できます）。下のボタンから手動投稿できます。</p>
	<?php endif; ?>

	<p style="margin-bottom: 4px;"><strong>投稿文プレビュー</strong></p>
	<textarea readonly rows="7" style="width: 100%; font-size: 12px;" onclick="this.select();"><?php echo esc_textarea( $preview ); ?></textarea>

	<p>
		<a href="<?php echo esc_url( $intent_url ); ?>" target="_blank" rel="noopener noreferrer" class="button">Xで投稿画面を開く</a>
	</p>

	<label>
		<input type="checkbox" name="node_connect_x_skip" value="1" <?php checked( $skip ); ?> />
		この記事は自動投稿しない
	</label>
	<p class="description" style="margin-bottom: 0;">公開の約<?php echo (int) Node_Connect_X_Poster::DELIVERY_DELAY; ?>秒後に送信されます。それまでにチェックを保存すれば自動投稿を止められます。自動投稿は記事1件につき1回だけ行われます。</p>
	<?php
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['node_connect_x_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( (string) $_POST['node_connect_x_meta_nonce'], 'node_connect_x_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['node_connect_x_skip'] ) ) {
			update_post_meta( $post_id, Node_Connect_X_Poster::SKIP_META, '1' );
		} else {
			delete_post_meta( $post_id, Node_Connect_X_Poster::SKIP_META );
		}
	}
);
