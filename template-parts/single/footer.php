<?php
/**
 * Template part for displaying the article footer in single.php
 */
?>
<footer class="m3-article__footer">
    <?php node_the_tag_labels(); ?>

    <?php get_template_part('template-parts/social-share'); ?>
    
    <?php get_template_part('template-parts/card-writer'); ?>
    
    <?php 
    // ライター情報の後にコンテンツを挿入するアクション（Node Library等）
    do_action('luminous_after_writer', get_the_ID()); 
    ?>

    <!-- 前後の記事ナビゲーション -->
    <?php
    $prev_post = get_previous_post();
    $next_post = get_next_post();
    if ($prev_post || $next_post) :
    ?>
    <nav class="m3-post-navigation">
        <?php if ($prev_post) : ?>
        <?php $prev_thumb = get_the_post_thumbnail_url($prev_post->ID, 'medium_large'); ?>
        <a href="<?php echo get_permalink($prev_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host<?php echo $prev_thumb ? '' : ' m3-elevated-nav-card--plain'; ?>">
            <?php if ($prev_thumb) : ?>
                <div class="m3-elevated-nav-card__bg" style="background-image: url('<?php echo esc_url($prev_thumb); ?>');"></div>
                <div class="m3-elevated-nav-card__overlay"></div>
            <?php endif; ?>
            <div class="m3-elevated-nav-card__content">
                <span class="m3-elevated-nav-card__label">
                    <span class="material-symbols-outlined">arrow_back</span>
                    前の記事
                </span>
                <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></h4>
            </div>
        </a>
        <?php endif; ?>
        
        <?php if ($next_post) : ?>
        <?php $next_thumb = get_the_post_thumbnail_url($next_post->ID, 'medium_large'); ?>
        <a href="<?php echo get_permalink($next_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host<?php echo $next_thumb ? '' : ' m3-elevated-nav-card--plain'; ?>" style="text-align: right;">
            <?php if ($next_thumb) : ?>
                <div class="m3-elevated-nav-card__bg" style="background-image: url('<?php echo esc_url($next_thumb); ?>');"></div>
                <div class="m3-elevated-nav-card__overlay"></div>
            <?php endif; ?>
            <div class="m3-elevated-nav-card__content" style="align-items: flex-end;">
                <span class="m3-elevated-nav-card__label">
                    次の記事
                    <span class="material-symbols-outlined">arrow_forward</span>
                </span>
                <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($next_post->ID)); ?></h4>
            </div>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</footer>
