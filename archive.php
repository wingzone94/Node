<?php get_header(); ?>

<main id="primary" class="site-main">

    <header class="m3-archive-header" style="text-align: center; padding: var(--m3-spacing-xxxl) var(--m3-spacing-m) var(--m3-spacing-xl);">
        <h1 class="m3-archive-title" style="display: flex; align-items: center; justify-content: center; gap: var(--m3-spacing-m); flex-wrap: wrap; font-size: 2.2rem; font-weight: 900; color: var(--md-sys-color-on-surface);">
            <?php the_archive_title(); ?>
            <span class="m3-badge">全 <?php echo $wp_query->found_posts; ?> 件</span>
        </h1>
        <?php the_archive_description('<div class="m3-archive-description" style="font-size: 1.1rem; color: var(--md-sys-color-outline); margin-top: var(--m3-spacing-m);">', '</div>'); ?>
    </header>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container">
                <?php while (have_posts()) : the_post(); ?>
                    <?php get_template_part('card'); ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="m3-navigation">
        <?php the_posts_pagination(['mid_size' => 2]); ?>
    </div>

</main>

<?php get_footer(); ?>