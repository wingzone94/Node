<?php
/**
 * The template for displaying comments
 */

if ( post_password_required() ) {
	return;
}

// カスタムコメントコールバック (吹き出しデザイン)
function node_comment_callback($comment, $args, $depth) {
    ?>
    <li <?php comment_class('m3-comment-item'); ?> id="comment-<?php comment_ID(); ?>">
        <div class="m3-comment-bubble-container">
            <div class="m3-comment-avatar-wrap">
                <?php echo get_avatar($comment, 48); ?>
            </div>
            
            <div class="m3-comment-bubble">
                <header class="m3-comment-bubble__header">
                    <span class="m3-comment-bubble__author"><?php comment_author_link(); ?></span>
                    <time class="m3-comment-bubble__date" datetime="<?php comment_time('c'); ?>">
                        <?php printf(esc_html__('%1$s at %2$s', 'node'), get_comment_date(), get_comment_time()); ?>
                    </time>
                </header>
                
                <div class="m3-comment-bubble__content entry-content">
                    <?php if ($comment->comment_approved == '0') : ?>
                        <p class="m3-comment-bubble__moderation">承認待ちです</p>
                    <?php endif; ?>
                    <?php comment_text(); ?>
                </div>
                
                <div class="m3-comment-bubble__actions">
                    <?php comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth']))); ?>
                </div>
            </div>
        </div>
    <?php
}
?>

<div id="comments" class="comments-area m3-comments-section">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<span class="material-symbols-outlined">forum</span>
			<?php
			$node_comment_count = get_comments_number();
			printf(
				esc_html( _n( '%s 件のリアクション', '%s 件のリアクション', $node_comment_count, 'node' ) ),
				number_format_i18n( $node_comment_count )
			);
			?>
		</h2>

		<ol class="comment-list m3-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
                    'callback'    => 'node_comment_callback'
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

	$node_fields = array(
		'author'  => '<div class="m3-textfield">
                        <label for="author" class="m3-textfield__label">' . esc_html__( 'お名前', 'node' ) . ( $node_req ? ' *' : ' (任意)' ) . '</label>
                        <input id="author" name="author" type="text" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author'] ) . '"' . $node_html_req . '>
                    </div>',
		'email'   => '<div class="m3-textfield">
                        <label for="email" class="m3-textfield__label">' . esc_html__( 'メールアドレス', 'node' ) . ( $node_req ? ' *' : ' (任意)' ) . '</label>
                        <input id="email" name="email" type="email" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author_email'] ) . '"' . $node_html_req . '>
                    </div>',
		'url'     => '<div class="m3-textfield">
                        <label for="url" class="m3-textfield__label">' . esc_html__( 'サイト', 'node' ) . '</label>
                        <input id="url" name="url" type="url" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author_url'] ) . '">
                    </div>',
	);

	$node_comment_field = '<div class="m3-textfield m3-textfield--textarea">
                        <label for="comment" class="m3-textfield__label">' . _x( 'コメント', 'noun', 'node' ) . ' *</label>
                        <div class="comment-toolbar">
                            <button type="button" class="toolbar-button" data-tag="bold"><span class="material-symbols-outlined">format_bold</span></button>
                            <button type="button" class="toolbar-button" data-tag="italic"><span class="material-symbols-outlined">format_italic</span></button>
                            <button type="button" class="toolbar-button" data-tag="underline"><span class="material-symbols-outlined">format_underlined</span></button>
                            <button type="button" class="toolbar-button" data-tag="link"><span class="material-symbols-outlined">link</span></button>
                        </div>
                        <textarea id="comment" name="comment" class="m3-textfield__input" required aria-required="true"></textarea>
                    </div>';

	comment_form( array(
		'title_reply'    => esc_html__( 'コメントを投稿する', 'node' ),
		'fields'         => $node_fields,
		'comment_field'  => $node_comment_field,
		'class_form'     => 'm3-comment-form',
		'submit_button'  => '<button name="%1$s" type="submit" id="%2$s" class="m3-button m3-button--filled"><span class="material-symbols-outlined">send</span>' . esc_html__( '送信', 'node' ) . '</button>',
	) );
	?>

</div>