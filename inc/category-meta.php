<?php
/**
 * Category color metadata.
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function node_user_can_edit_category_meta( $term_id = 0 ) {
	if ( current_user_can( 'manage_categories' ) ) {
		return true;
	}

	if ( $term_id && current_user_can( 'edit_term', $term_id ) ) {
		return true;
	}

	return false;
}

function node_sanitize_category_color( $color ) {
	$color = trim( sanitize_text_field( wp_unslash( $color ) ) );

	if ( '' === $color || 'auto' === strtolower( $color ) ) {
		return '';
	}

	$hex = sanitize_hex_color( $color );

	return $hex ? $hex : '';
}

function node_category_add_form_fields() {
	if ( ! node_user_can_edit_category_meta() ) {
		return;
	}

	wp_nonce_field( 'node_save_category_meta', 'node_category_meta_nonce' );
	?>
	<div class="form-field">
		<label for="m3_color">テーマカラー (Hex)</label>
		<input name="m3_color" id="m3_color" type="text" value="" class="node-color-picker" data-default-color="#FF9900">
		<p>カテゴリのベースカラーを16進数で指定します（例: #FF9900）。空欄または「auto」の場合はアイキャッチ画像から自動抽出します。</p>
	</div>
	<?php
}
add_action( 'category_add_form_fields', 'node_category_add_form_fields' );

function node_category_edit_form_fields( $term ) {
	if ( ! node_user_can_edit_category_meta( $term->term_id ) ) {
		return;
	}

	$color = get_term_meta( $term->term_id, '_m3_color', true ) ?: '#FF9900';
	?>
	<tr class="form-field">
		<th scope="row"><label for="m3_color">テーマカラー (Hex)</label></th>
		<td>
			<?php wp_nonce_field( 'node_save_category_meta', 'node_category_meta_nonce' ); ?>
			<input name="m3_color" id="m3_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="node-color-picker" data-default-color="#FF9900">
			<p class="description">カテゴリのベースカラー（例: #FF9900）。空欄または「auto」で自動抽出に戻します。</p>
		</td>
	</tr>
	<?php
}
add_action( 'category_edit_form_fields', 'node_category_edit_form_fields' );

function node_save_category_meta( $term_id ) {
	if ( ! isset( $_POST['node_category_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['node_category_meta_nonce'] ) ), 'node_save_category_meta' ) ) {
		return;
	}

	if ( ! node_user_can_edit_category_meta( $term_id ) ) {
		return;
	}

	if ( ! isset( $_POST['m3_color'] ) ) {
		return;
	}

	$color = node_sanitize_category_color( $_POST['m3_color'] );

	if ( '' === $color ) {
		delete_term_meta( $term_id, '_m3_color' );
		return;
	}

	update_term_meta( $term_id, '_m3_color', $color );
}
add_action( 'edited_category', 'node_save_category_meta' );
add_action( 'create_category', 'node_save_category_meta' );

function node_ajax_save_category_color() {
	check_ajax_referer( 'wp_rest', 'nonce' );

	$term_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

	if ( ! $term_id || ! node_user_can_edit_category_meta( $term_id ) ) {
		wp_send_json_error( 'Permission denied', 403 );
	}

	$color = isset( $_POST['color'] ) ? node_sanitize_category_color( $_POST['color'] ) : '';

	if ( '' === $color ) {
		delete_term_meta( $term_id, '_m3_color' );
	} else {
		update_term_meta( $term_id, '_m3_color', $color );
	}

	wp_send_json_success(
		array(
			'category_id' => $term_id,
			'color'       => $color,
		)
	);
}
add_action( 'wp_ajax_save_category_color', 'node_ajax_save_category_color' );
