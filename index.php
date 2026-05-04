<?php get_header(); ?>

<main id="primary" class="site-main">

    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();

    // SEO: ページタイトル (h1)
    if ( ! is_home() || is_paged() ) :
    ?>
        <header class="m3-archive-header">
            <h1 class="m3-section-title"><?php echo esc_html( node_get_archive_title() ); ?></h1>
            <?php if ( get_the_archive_description() ) : ?>
                <div class="m3-archive-description"><?php the_archive_description(); ?></div>
            <?php endif; ?>
        </header>
    <?php 
    endif;

    if ((is_home() || is_front_page()) && !is_paged()) : 
        $spotlight_cats = function_exists('node_get_spotlight_categories') ? node_get_spotlight_categories() : [];
        if (!empty($spotlight_cats)) :
    ?>
        <!-- リファクタリングされた SPOTLIGHT セクション -->
        <section class="special-features">
            <h2 class="m3-section-title">🔥 SPOTLIGHT</h2>
            <div class="special-features__pills">
                <?php foreach ($spotlight_cats as $cat) : ?>
                    <a href="<?php echo esc_url($cat['url']); ?>" 
                       class="m3-spotlight-badge m3-ripple-host" 
                       style="background-color: <?php echo esc_attr($cat['color']); ?>; color: #ffffff;">
                       <span class="material-symbols-outlined">auto_awesome</span> 
                       <?php echo esc_html($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php 
        endif; // !empty
    endif; // is_home
    ?>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <?php
            $is_first_page = (is_home() && !is_paged());
            if ($is_first_page) {
                echo '<h2 class="m3-section-title">⚡ LATEST</h2>';
            }
            ?>
            <div class="m3-post-grid__container">
                <?php
                global $wp_query;
                while (have_posts()) : the_post();
                    if ($is_first_page && $wp_query->current_post === 4) {
                        echo '</div>'; // Close latest container
                        echo '<hr class="m3-section-divider">';
                        echo '<h2 class="m3-section-title">🕒 ARTICLES</h2>';
                        echo '<div class="m3-post-grid__container is-articles-grid">'; // Open standard container
                    }
                    
                    $card_class = ($is_first_page && $wp_query->current_post < 4) ? 'card-featured' : 'card-standard';
                    get_template_part('template-parts/card', null, ['card_class' => $card_class, 'show_ai' => false]);


                endwhile; ?>
            </div>

            <!-- Archive Link Pill Button -->
            <?php if (get_next_posts_link()) : ?>
                <div class="m3-archive-pill-wrapper">
                    <a href="<?php echo next_posts(0, false); ?>" class="m3-archive-pill-button m3-ripple-host">
                        <span class="m3-archive-pill-button__text">もっと過去の記事を見る</span>
                        <div class="m3-archive-pill-button__icon">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>



</main>

<?php get_footer(); ?>