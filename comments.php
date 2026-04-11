<?php
/**
 * The template for displaying comments
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<span class="material-symbols-outlined">forum</span>
			<?php
			$node_comment_count = get_comments_number();
			printf(
				/* translators: %s: comment count number. */
				esc_html( _n( '%s 件のリアクション', '%s 件のリアクション', $node_comment_count, 'node' ) ),
				number_format_i18n( $node_comment_count )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	$node_commenter = wp_get_current_commenter();
	$node_req       = get_option( 'require_name_email' );
	$node_html_req  = ( $node_req ? " required='required'" : '' );
	$node_consent   = empty( $node_commenter['comment_author_email'] ) ? '' : ' checked="checked"';

	// ユーザー情報フィールド (未ログイン時に表示)
	$node_fields = array(
		'author'  => '<div class="m3-textfield">
                        <label for="author" class="m3-textfield__label">' . esc_html__( 'お名前', 'node' ) . ( $node_req ? ' <span class="required">*</span>' : ' (任意)' ) . '</label>
                        <input id="author" name="author" type="text" class="m3-textfield__input" placeholder="' . esc_attr__( '匿名希望', 'node' ) . '" value="' . esc_attr( $node_commenter['comment_author'] ) . '"' . $node_html_req . ' autocomplete="name">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
		'email'   => '<div class="m3-textfield">
                        <label for="email" class="m3-textfield__label">' . esc_html__( 'メールアドレス', 'node' ) . ( $node_req ? ' <span class="required">*</span>' : ' (任意)' ) . '</label>
                        <input id="email" name="email" type="email" class="m3-textfield__input" placeholder="example@domain.com" value="' . esc_attr( $node_commenter['comment_author_email'] ) . '"' . $node_html_req . ' autocomplete="email">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
		'url'     => '<div class="m3-textfield">
                        <label for="url" class="m3-textfield__label">' . esc_html__( 'ウェブサイト (URL)', 'node' ) . '</label>
                        <input id="url" name="url" type="url" class="m3-textfield__input" placeholder="https://" value="' . esc_attr( $node_commenter['comment_author_url'] ) . '" autocomplete="url">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
		'cookies' => '<div class="comment-form-cookies-consent">
                        <input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $node_consent . '>
                        <label for="wp-comment-cookies-consent">' . esc_html__( '次回のコメントで使用するためブラウザーに自分の名前、メールアドレス、サイトを保存する。', 'node' ) . '</label>
                    </div>',
	);

	// コメント入力欄
	$node_comment_field = '<div class="m3-textfield m3-textfield--textarea">
                        <label for="comment" class="m3-textfield__label">' . _x( 'コメント', 'noun', 'node' ) . ' <span class="required">*</span></label>
                        <div class="comment-toolbar">
                            <button type="button" class="toolbar-button" data-tag="b" title="太字"><span class="material-symbols-outlined">format_bold</span></button>
                            <button type="button" class="toolbar-button" data-tag="i" title="斜体"><span class="material-symbols-outlined">format_italic</span></button>
                            <button type="button" class="toolbar-button" data-tag="u" title="下線"><span class="material-symbols-outlined">format_underlined</span></button>
                            <button type="button" class="toolbar-button" data-tag="a" title="リンク"><span class="material-symbols-outlined">link</span></button>
                        </div>
                        <textarea id="comment" name="comment" class="m3-textfield__input" required aria-required="true"></textarea>
                        <div class="m3-textfield__indicator"></div>
                    </div>';

	// フィールドの順序を制御（名前・メアド・URLを先に、コメントを後に）
	add_filter( 'comment_form_fields', function( $fields ) {
		$comment_field = $fields['comment'];
		unset( $fields['comment'] );
		$fields['comment'] = $comment_field;
		return $fields;
	} );

	comment_form( array(
		'title_reply'    => esc_html__( 'コメントを投稿する', 'node' ),
		'title_reply_to' => esc_html__( '%s への返信', 'node' ),
		'class_form'     => 'm3-comment-form',
		'format'         => 'html5',
		'fields'         => $node_fields,
		'comment_field'  => $node_comment_field,
		'submit_button'  => '<button name="%1$s" type="submit" id="%2$s" class="m3-button m3-button--filled"><span class="material-symbols-outlined">send</span>' . esc_html__( 'コメントを送信', 'node' ) . '</button>',
	) );
	?>

</div>