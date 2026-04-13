<?php get_header(); ?>

<main id="primary" class="site-main">

    <?php if (is_home() && !is_paged()) : ?>
        <!-- ミニマルな SPOTLIGHT セクション -->
        <section class="m3-featured-abstract">
            <div class="m3-featured-abstract__header">
                <h2 class="m3-featured-abstract__title">SPOTLIGHT</h2>
            </div>
            <div class="m3-featured-abstract__container">
                <?php
                $parent_cat = get_category_by_slug('spotlight') ?: get_category_by_slug('Spotlight');
                if ($parent_cat) {
                    $sub_cats = get_categories(['parent' => $parent_cat->term_id]);
                    $sub_cat_ids = wp_list_pluck($sub_cats, 'term_id');
                    if (!empty($sub_cat_ids)) {
                        $featured_query = new WP_Query([
                            'category__in' => $sub_cat_ids,
                            'posts_per_page' => 4,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ]);
                        $m3_colors = ['var(--md-sys-color-primary)', '#6750A4', '#006A6A', '#914C00'];
                        $color_index = 0;
                        if ($featured_query->have_posts()) :
                            while ($featured_query->have_posts()) : $featured_query->the_post(); 
                                $color = $m3_colors[$color_index % count($m3_colors)];
                                ?>
                                <a href="<?php the_permalink(); ?>" class="m3-spotlight-item" style="--spotlight-color: <?php echo $color; ?>">
                                    <div class="m3-spotlight-item__content">
                                        <h3 class="m3-spotlight-item__title"><?php the_title(); ?></h3>
                                    </div>
                                </a>
                                <?php 
                                $color_index++;
                            endwhile;
                            wp_reset_postdata();
                        endif;
                    }
                }
                ?>
            </div>
        </section>
    <?php endif; ?>

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