<?php get_header(); ?>

<main id="primary" class="site-main">

    <?php 
    if (is_home() && !is_paged()) : 
        $spotlight_cats = function_exists('node_get_spotlight_categories') ? node_get_spotlight_categories() : [];
        if (!empty($spotlight_cats)) :
    ?>
        <!-- リファクタリングされた SPOTLIGHT セクション -->
        <section class="special-features">
            <div class="special-features__header">
                <h2 class="special-features__title">🔥SPOTLIGHT</h2>
            </div>
            <div class="special-features__pills" style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 32px;">
                <?php foreach ($spotlight_cats as $cat) : ?>
                    <a href="<?php echo esc_url($cat['url']); ?>" 
                       class="m3-spotlight-badge" 
                       style="background-color: <?php echo esc_attr($cat['color']); ?>; color: #ffffff;">
                       <span class="material-symbols-outlined" style="font-size: 1.2rem;">auto_awesome</span> 
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
            <div class="m3-post-grid__container">
                <?php
                while (have_posts()) : the_post();
                    $card_class = ($wp_query->current_post < 4 && !is_paged()) ? 'card-featured' : 'card-standard';
                    get_template_part('card', null, ['card_class' => $card_class]);
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