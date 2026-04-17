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
            
            <div class="m3-comment-card">
                <header class="m3-comment-card__header">
                    <span class="m3-comment-card__author"><?php comment_author_link(); ?></span>
                    <time class="m3-comment-card__date" datetime="<?php comment_time('c'); ?>">
                        <?php printf(esc_html__('%1$s at %2$s', 'node'), get_comment_date(), get_comment_time()); ?>
                    </time>
                </header>
                
                <div class="m3-comment-card__content entry-content">
                    <?php if ($comment->comment_approved == '0') : ?>
                        <p class="m3-comment-card__moderation">承認待ちです</p>
                    <?php endif; ?>
                    <?php comment_text(); ?>
                </div>
                
                <div class="m3-comment-card__actions">
                    <?php comment_reply_link(array_merge($args, array(
                        'depth'     => $depth,
                        'max_depth' => $args['max_depth']
                    ))); ?>
                </div>
            </div>
        </div>
    </li>
    <?php
}

// comment_form_fields フィルターで表示順を強制する
function node_reorder_comment_fields( $fields ) {
    $order = array( 'author', 'email', 'url', 'comment' );
    $reordered = array();

    foreach ( $order as $key ) {
        if ( isset( $fields[ $key ] ) ) {
            $reordered[ $key ] = $fields[ $key ];
        }
    }

    foreach ( $fields as $key => $value ) {
        if ( ! isset( $reordered[ $key ] ) ) {
            $reordered[ $key ] = $value;
        }
    }

    return $reordered;
}
add_filter( 'comment_form_fields', 'node_reorder_comment_fields' );
?>

<div id="comments" class="comments-area m3-comments-section">

    <?php if ( have_comments() ) : ?>
        <h2 class="comments-title">
            <span class="material-symbols-outlined">forum</span>
            <?php
            $node_comment_count = get_comments_number();
            printf(
                esc_html( _n( '%s 件のコメント', '%s 件のコメント', $node_comment_count, 'node' ) ),
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

    <?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
        <p class="no-comments"><?php esc_html_e( 'コメントは受け付けていません', 'node' ); ?></p>
    <?php endif; ?>

    <?php
    // フォームの設定
    $node_commenter = wp_get_current_commenter();
    $node_req       = get_option( 'require_name_email' );
    $node_html_req  = ( $node_req ? " required='required'" : '' );

    $node_fields = array(
        'author' => '<div class="m3-textfield">
                        <label for="author" class="m3-textfield__label">' . esc_html__( 'お名前', 'node' ) . ( $node_req ? ' *' : ' (任意)' ) . '</label>
                        <input id="author" name="author" type="text" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author'] ) . '"' . $node_html_req . ' placeholder="ななしさん" maxlength="100">
                    </div>',
        'email'  => '<div class="m3-textfield">
                        <label for="email" class="m3-textfield__label">' . esc_html__( 'メールアドレス', 'node' ) . ' (任意)</label>
                        <input id="email" name="email" type="email" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author_email'] ) . '" placeholder="user@example.com" maxlength="200">
                    </div>',
        'url'    => '<div class="m3-textfield">
                        <label for="url" class="m3-textfield__label">' . esc_html__( 'ウェブサイト', 'node' ) . '</label>
                        <input id="url" name="url" type="url" class="m3-textfield__input" value="' . esc_attr( $node_commenter['comment_author_url'] ) . '" placeholder="https://..." maxlength="200">
                    </div>',
        'comment' => '
            <div class="m3-textfield m3-textfield--textarea">
                <label for="comment" class="m3-textfield__label">' . _x( 'コメント内容', 'noun', 'node' ) . ' *</label>
                <div class="comment-toolbar">
                    <button type="button" class="toolbar-button" data-tag="bold" title="太字"><span class="material-symbols-outlined">format_bold</span></button>
                    <button type="button" class="toolbar-button" data-tag="italic" title="斜体"><span class="material-symbols-outlined">format_italic</span></button>
                    <button type="button" class="toolbar-button" data-tag="underline" title="下線"><span class="material-symbols-outlined">format_underlined</span></button>
                    <button type="button" class="toolbar-button" data-tag="link" title="リンク"><span class="material-symbols-outlined">link</span></button>
                </div>
                <textarea id="comment" name="comment" class="m3-textfield__input" placeholder="温かいコメントをお待ちしております..." required aria-required="true" minlength="2" maxlength="5000"></textarea>
            </div>',
    );

    comment_form( array(
        'title_reply'          => esc_html__( 'コメントを投稿する', 'node' ),
        'title_reply_to'       => esc_html__( '%s への返信', 'node' ),
        'cancel_reply_link'    => esc_html__( 'キャンセル', 'node' ),
        'label_submit'         => esc_html__( '送信する', 'node' ),
        'fields'               => $node_fields,
        'comment_field'        => '',
        'class_form'           => 'm3-comment-form',
        'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="m3-button m3-button--filled"><span class="material-symbols-outlined">send</span>%4$s</button>',
        'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title m3-title-medium">',
        'title_reply_after'    => '</h3>',
    ) );
    ?>

</div>
