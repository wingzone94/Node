<?php get_header(); ?>

<main id="primary" class="site-main article-view">

    <?php if (is_home() && !is_paged()) : ?>
        <!-- ミニマルな SPOTLIGHT セクション (v0.1.2 最終調整版) -->
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

                        $m3_colors = [
                            'var(--md-sys-color-primary)',
                            '#6750A4', 
                            '#006A6A',
                            '#914C00'
                        ];
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
            <div class="m3-post-grid__header">
                <h2 class="m3-post-grid__title">LATEST STORIES</h2>
            </div>
            <div class="m3-post-grid__container">
                <?php while (have_posts()) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class('m3-card m3-card--article'); ?>>
                        <a href="<?php the_permalink(); ?>" class="m3-card__image-link">
                            <?php if (has_post_thumbnail()) : the_post_thumbnail('medium_large'); 
                                  else : echo '<div class="m3-card__placeholder"></div>'; endif; ?>
                            
                            <div class="m3-card__labels">
                                <?php if (get_post_meta(get_the_ID(), '_node_is_ai_generated', true)) : ?>
                                    <span class="m3-label m3-label--ai">
                                        <span class="material-symbols-outlined">auto_awesome</span>
                                        生成されたメディアを含む
                                    </span>
                                <?php endif; ?>
                                <?php if (get_post_meta(get_the_ID(), '_node_is_sponsor', true)) : ?>
                                    <span class="m3-label m3-label--sponsor">SPONSORED</span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="m3-card__content">
                            <div class="m3-card__meta">
                                <span class="m3-card__date"><?php echo get_the_date(); ?></span>
                            </div>
                            <h3 class="m3-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <div class="m3-card__excerpt">
                                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                            </div>
                            <div class="m3-card__footer">
                                <div class="m3-card__category">
                                    <?php
                                    $categories = get_the_category();
                                    if (!empty($categories)) {
                                        echo esc_html($categories[0]->name);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="m3-navigation">
        <?php the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '<span class="material-symbols-outlined">arrow_back</span>',
            'next_text' => '<span class="material-symbols-outlined">arrow_forward</span>',
        ]); ?>
    </div>

</main>

<?php get_footer(); ?>