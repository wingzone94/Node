<?php get_header(); ?>

<main id="primary" class="site-main m3-home-layout">

    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();
    ?>

    <?php if ( is_home() || is_archive() || is_search() ) : ?>
        <header class="m3-archive-header m3-surface m3-surface--variant m3-reveal-group">
            <h1 class="m3-section-title">
                <?php 
                if ( is_home() && ! is_paged() ) {
                    echo 'Luminous Core';
                } else {
                    echo esc_html( node_get_archive_title() );
                }
                ?>
            </h1>
            <?php if ( ( ! is_home() || is_paged() ) && get_the_archive_description() ) : ?>
                <div class="m3-archive-description"><?php the_archive_description(); ?></div>
            <?php endif; ?>
        </header>
    <?php endif; ?>

    <?php if ((is_home() || is_front_page()) && !is_paged()) : 
        $spotlight_cats = function_exists('node_get_spotlight_categories') ? node_get_spotlight_categories() : [];
        if (!empty($spotlight_cats)) :
    ?>
        <section class="special-features m3-surface m3-section-spacing" aria-labelledby="spotlight-title">
            <h2 id="spotlight-title" class="m3-section-title">
                <span class="material-symbols-outlined" aria-hidden="true">local_fire_department</span>
                SPOTLIGHT <span class="m3-section-title__sub">特集</span>
            </h2>
            <div class="special-features__pills">
                <?php foreach ($spotlight_cats as $cat) : ?>
                    <a href="<?php echo esc_url($cat['url']); ?>" 
                       class="m3-spotlight-badge m3-ripple-host" 
                       style="background-color: <?php echo esc_attr($cat['color']); ?>; color: #ffffff;"
                       aria-label="<?php echo esc_attr($cat['name']); ?>特集へ">
                       <span class="material-symbols-outlined" aria-hidden="true">auto_awesome</span> 
                       <?php echo esc_html($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php 
        endif;

        // HEADLINES
        $headline_query = new WP_Query([
            'category_name'  => 'news',
            'posts_per_page' => 5,
            'ignore_sticky_posts' => true
        ]);
        if ($headline_query->have_posts()) :
    ?>
        <section class="m3-headlines m3-surface m3-section-spacing" aria-labelledby="headlines-title">
            <div class="m3-headlines__header">
                <h2 id="headlines-title" class="m3-headlines__title m3-section-title">
                    <span class="material-symbols-outlined" aria-hidden="true">campaign</span>
                    HEADLINES
                    <span class="m3-section-title__sub">速報</span>
                </h2>
                <a href="<?php echo esc_url(get_category_link(get_category_by_slug('news'))); ?>" class="m3-headlines__more m3-button m3-button--text">
                    すべて見る
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
            <div class="m3-headlines__list" role="list">
                <?php 
                while ($headline_query->have_posts()) : $headline_query->the_post();
                    get_template_part('template-parts/card-headline');
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        </section>
    <?php 
        endif; 
    endif;
    ?>

    <?php if (have_posts()) : ?>
        <?php
        $is_first_page = (is_home() && !is_paged());
        ?>
        <section class="m3-post-grid" aria-labelledby="<?php echo $is_first_page ? 'latest-title' : 'articles-title'; ?>">
            <?php if ($is_first_page) : ?>
                <div class="m3-surface m3-surface--latest m3-section-spacing">
                    <h2 id="latest-title" class="m3-section-title">
                        <span class="material-symbols-outlined" aria-hidden="true">bolt</span>
                        LATEST <span class="m3-section-title__sub">最新記事</span>
                    </h2>
                    <div class="m3-post-grid__container m3-post-grid__container--featured">
            <?php else : ?>
                <div class="m3-surface m3-surface--articles m3-section-spacing">
                    <h2 id="articles-title" class="m3-section-title">
                        <span class="material-symbols-outlined" aria-hidden="true">article</span>
                        ARTICLES <span class="m3-section-title__sub">記事一覧</span>
                    </h2>
                    <div class="m3-post-grid__container is-articles-grid">
            <?php endif; ?>

            <?php
            global $wp_query;
            $post_count = $wp_query->post_count;
            $has_featured_split = ($is_first_page && $post_count > 4);
            $switched = false;

            while (have_posts()) : the_post();
                // Switch from Featured to Articles section after the 4th post
                if ($is_first_page && $wp_query->current_post === 4) {
                    echo '</div>'; // Close featured container
                    echo '</div>'; // Close latest surface
                    
                    // Material 3 Expressive Divider
                    echo '<div class="m3-divider-wrapper"><hr class="m3-divider m3-divider--expressive" aria-hidden="true"></div>';
                    
                    echo '<div class="m3-surface m3-surface--articles m3-section-spacing">';
                    echo '<h2 id="articles-title" class="m3-section-title"><span class="material-symbols-outlined" aria-hidden="true">schedule</span> ARTICLES <span class="m3-section-title__sub">記事一覧</span></h2>';
                    echo '<div class="m3-post-grid__container is-articles-grid">'; // Open standard container
                    $switched = true;
                }
                
                $card_class = ($is_first_page && $wp_query->current_post < 4) ? 'card-featured' : 'card-standard';
                get_template_part('template-parts/card', null, ['card_class' => $card_class, 'show_ai' => false]);
            endwhile;
            ?>
                </div> <!-- Close container -->
            </div> <!-- Close surface -->

            <?php if (get_next_posts_link()) : ?>
                <div class="m3-archive-pill-wrapper m3-section-spacing">
                    <a href="<?php echo next_posts(0, false); ?>" class="m3-archive-pill-button m3-ripple-host" aria-label="過去の記事をさらに読み込む">
                        <span class="m3-archive-pill-button__text">もっと過去の記事を見る</span>
                        <div class="m3-archive-pill-button__icon">
                            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
