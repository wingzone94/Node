<?php get_header(); ?>

<main id="primary" class="site-main">

    <header class="m3-archive-header">
        <h1 class="m3-archive-title">
            <?php the_archive_title(); ?>
            <span class="m3-badge">全 <?php echo $wp_query->found_posts; ?> 件</span>
        </h1>
        <?php the_archive_description('<div class="m3-archive-description">', '</div>'); ?>
    </header>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container">
                <?php
                while (have_posts()) : the_post();
                    // Homeと同様に最初の4件を Featured 扱いにする (Paged でない場合)
                    $card_class = ($wp_query->current_post < 4 && !is_paged()) ? 'card-featured' : 'card-standard';
                    get_template_part('template-parts/card', null, ['card_class' => $card_class]);
                endwhile;
                ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="m3-navigation">
        <?php 
        the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
            'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
        ]); 
        ?>
    </div>

</main>

<?php get_footer(); ?>