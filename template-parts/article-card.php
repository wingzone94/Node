<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . (has_post_thumbnail() ? 'm3-card--has-image' : 'm3-card--no-image')); ?>>
    
    <?php if (has_post_thumbnail()) : ?>
        <div class="m3-card__background">
            <?php the_post_thumbnail('large'); ?>
        </div>
        <div class="m3-card__overlay"></div>
    <?php endif; ?>

    <!-- 左上日付ラベル -->
    <div class="m3-label--date">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
            <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zM7 12h5v5H7z"/>
        </svg>
        <span><?php echo esc_html(node_get_relative_date(get_the_ID())); ?></span>
    </div>

    <!-- 右上バッジ (AI / SPONSOR) -->
    <?php node_the_post_badges(); ?>

    <div class="m3-card__content">
        <!-- カテゴリ表示 -->
        <?php node_the_category_labels(); ?>

        <h3 class="m3-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        
        <div class="m3-card__footer-meta">
            <div class="m3-card__writer">
                <?php echo get_avatar(get_the_author_meta('ID'), 24); ?>
                <span class="m3-card__writer-name"><?php the_author(); ?></span>
            </div>
            <div class="m3-card__meta-right">
                <?php if (get_comments_number() > 0) : ?>
                    <div class="m3-card__comment-count">
                        <span class="material-symbols-outlined">chat_bubble</span>
                        <span><?php echo get_comments_number(); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>
