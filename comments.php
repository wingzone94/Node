<?php
/**
 * The template for displaying comments
 */
if ( post_password_required() ) return;
?>

<div id="comments" class="comments-area">

    <?php if ( have_comments() ) : ?>
        <h2 class="comments-title">
            <span class="material-symbols-outlined">forum</span>
            <?php echo get_comments_number() . ' 件のリアクション'; ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments([
                'style'      => 'ol',
                'short_ping' => true,
                'avatar_size' => 48,
            ]);
            ?>
        </ol>

        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php
    // フィールドの定義 (未ログインユーザー用)
    $fields = [
        'author' => '<div class="m3-textfield">
                        <label for="author" class="m3-textfield__label">お名前 (任意)</label>
                        <input id="author" name="author" type="text" class="m3-textfield__input" placeholder="匿名希望" value="' . esc_attr( $commenter['comment_author'] ) . '">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
        'email'  => '<div class="m3-textfield">
                        <label for="email" class="m3-textfield__label">メールアドレス (必須)</label>
                        <input id="email" name="email" type="email" class="m3-textfield__input" required placeholder="example@domain.com" value="' . esc_attr( $commenter['comment_author_email'] ) . '">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
        'url'    => '<div class="m3-textfield">
                        <label for="url" class="m3-textfield__label">ウェブサイト (URL)</label>
                        <input id="url" name="url" type="url" class="m3-textfield__input" placeholder="https://" value="' . esc_attr( $commenter['comment_author_url'] ) . '">
                        <div class="m3-textfield__indicator"></div>
                    </div>',
    ];

    comment_form([
        'title_reply'    => 'コメントを投稿する',
        'title_reply_to' => '%s への返信',
        'class_form'     => 'm3-comment-form',
        'format'         => 'html5',
        'fields'         => $fields,
        'comment_field'  => '<div class="m3-textfield m3-textfield--textarea">
                                <label for="comment" class="m3-textfield__label">コメント</label>
                                <div class="comment-toolbar">
                                    <button type="button" class="toolbar-button" data-tag="b" title="太字"><span class="material-symbols-outlined">format_bold</span></button>
                                    <button type="button" class="toolbar-button" data-tag="i" title="斜体"><span class="material-symbols-outlined">format_italic</span></button>
                                    <button type="button" class="toolbar-button" data-tag="u" title="下線"><span class="material-symbols-outlined">format_underlined</span></button>
                                    <button type="button" class="toolbar-button" data-tag="a" title="リンク"><span class="material-symbols-outlined">link</span></button>
                                </div>
                                <textarea id="comment" name="comment" class="m3-textfield__input" required></textarea>
                                <div class="m3-textfield__indicator"></div>
                            </div>',
        'submit_button'  => '<button name="%1$s" type="submit" id="%2$s" class="m3-button m3-button--filled"><span class="material-symbols-outlined">send</span>コメントを送信</button>',
    ]);
    ?>

</div>