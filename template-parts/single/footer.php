<?php
/**
 * Template part for displaying the article footer in single.php
 */
?>
<footer class="m3-article__footer">
    <?php
    $post_tags = get_the_tags();
    if ($post_tags) :
    ?>
    <div class="m3-article__tags">
        <?php foreach ($post_tags as $tag) : ?>
            <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="m3-filter-chip">#<?php echo esc_html($tag->name); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php get_template_part('template-parts/social-share'); ?>
    
    <?php get_template_part('template-parts/card-writer'); ?>

    <!-- 前後の記事ナビゲーション -->
    <?php
    $prev_post = get_previous_post();
    $next_post = get_next_post();
    if ($prev_post || $next_post) :
    ?>
    <nav class="m3-post-navigation">
        <?php if ($prev_post) : ?>
        <a href="<?php echo get_permalink($prev_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host">
            <div class="m3-elevated-nav-card__content">
                <span class="m3-elevated-nav-card__label">PREVIOUS</span>
                <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></h4>
            </div>
        </a>
        <?php endif; ?>
        
        <?php if ($next_post) : ?>
        <a href="<?php echo get_permalink($next_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host" style="text-align: right;">
            <div class="m3-elevated-nav-card__content" style="align-items: flex-end;">
                <span class="m3-elevated-nav-card__label">NEXT</span>
                <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($next_post->ID)); ?></h4>
            </div>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</footer>
