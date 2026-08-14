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

	if ( $hex && function_exists( 'node_is_default_category_color' ) && node_is_default_category_color( $hex ) ) {
		return '';
	}

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
		<?php node_category_color_preview_markup( '' ); ?>
		<p>カテゴリのベースカラーを16進数で指定します（例: #FF9900、#10A37F、#078EFA）。空欄または「auto」の場合はアイキャッチ画像から自動抽出します。</p>
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
			<?php node_category_color_preview_markup( $color ); ?>
			<p class="description">カテゴリのベースカラー（例: #FF9900、#E60012、#D97757）。空欄または「auto」で自動抽出に戻します。</p>
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

/**
 * カテゴリラベルの簡易プレビュー + コントラスト注意文のマークアップを出力する。
 * 文字色は常に白（#fff）で固定する。
 */
function node_category_color_preview_markup( $color ) {
	$preview_color = sanitize_hex_color( $color );
	if ( ! $preview_color ) {
		$preview_color = '#FF9900';
	}
	?>
	<div class="node-cat-preview" aria-hidden="true">
		<span class="node-cat-preview__label" style="background-color:<?php echo esc_attr( $preview_color ); ?>;color:#fff;">プレビュー</span>
		<p class="node-cat-preview__warning" style="display:none;">この色は明るすぎて白文字とのコントラストが不足する可能性があります。</p>
	</div>
	<?php
}

/**
 * カテゴリ編集画面でのみ、カラーピッカーとプレビュー用スクリプトを読み込む。
 * フロントエンドには出力しない。
 */
function node_category_meta_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}

	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';
	if ( 'category' !== $taxonomy ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	$css = '.node-cat-preview{margin:8px 0 4px;}'
		. '.node-cat-preview__label{display:inline-flex;align-items:center;padding:6px 16px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.4;letter-spacing:.02em;color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.15);}'
		. '.node-cat-preview__warning{margin:6px 0 0;color:#b32d2e;font-size:12px;}';
	wp_add_inline_style( 'wp-color-picker', $css );

	$js = <<<'JS'
( function ( $ ) {
	function relLuminance( hex ) {
		var c = hex.replace( '#', '' );
		if ( c.length === 3 ) {
			c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
		}
		var rgb = [ parseInt( c.slice( 0, 2 ), 16 ), parseInt( c.slice( 2, 4 ), 16 ), parseInt( c.slice( 4, 6 ), 16 ) ];
		var lin = rgb.map( function ( v ) {
			v = v / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
	}

	function update( $input ) {
		var $field = $input.closest( '.form-field, td' );
		var $label = $field.find( '.node-cat-preview__label' );
		var $warn = $field.find( '.node-cat-preview__warning' );
		if ( ! $label.length ) {
			return;
		}
		var val = ( $input.val() || '' ).trim();
		if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( val ) ) {
			val = $input.data( 'default-color' ) || '#FF9900';
		}
		$label.css( { 'background-color': val, 'color': '#fff' } );
		// 白文字とのコントラスト比が低い（明るすぎる）場合に注意文を表示する。
		var ratio = 1.05 / ( relLuminance( val ) + 0.05 );
		$warn.toggle( ratio < 2.5 );
	}

	$( function () {
		$( '.node-color-picker' ).each( function () {
			var $input = $( this );
			$input.wpColorPicker( {
				change: function ( event, ui ) {
					$input.val( ui.color.toString() );
					update( $input );
				},
				clear: function () {
					update( $input );
				}
			} );
			$input.on( 'input change keyup', function () {
				update( $input );
			} );
			update( $input );
		} );
	} );
}( jQuery ) );
JS;
	wp_add_inline_script( 'wp-color-picker', $js );
}
add_action( 'admin_enqueue_scripts', 'node_category_meta_admin_assets' );
