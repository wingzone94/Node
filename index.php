<?php get_header(); ?>

<main id="primary" class="site-main">

    <?php if (is_home() && !is_paged()) : ?>
        <!-- リファクタリングされた SPOTLIGHT セクション -->
        <section class="special-features">
            <div class="special-features__header">
                <h2 class="special-features__title">🔥SPOTLIGHT</h2>
            </div>
            <div class="special-features__grid">
                <?php
                $spotlight_posts = node_get_spotlight_posts(6);
                $m3_colors = ['var(--md-sys-color-primary)', '#6750A4', '#006A6A', '#914C00', '#BF360C', '#311B92'];
                $index = 0;
                foreach ($spotlight_posts as $item) : 
                    $color = $m3_colors[$index % count($m3_colors)];
                    ?>
                    <a href="<?php echo esc_url($item['url']); ?>" 
                       class="special-features__item <?php echo $item['thumbnail'] ? 'has-image' : ''; ?>" 
                       style="--spotlight-color: <?php echo $color; ?>;">
                        <?php if ($item['thumbnail']) : ?>
                            <div class="special-features__image">
                                <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="">
                            </div>
                            <div class="special-features__overlay"></div>
                        <?php endif; ?>
                        <div class="special-features__content">
                            <h3 class="special-features__item-title"><?php echo esc_html($item['title']); ?></h3>
                        </div>
                    </a>
                <?php 
                    $index++;
                endforeach; 
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