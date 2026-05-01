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
                        echo '<div class="m3-post-grid__container">'; // Open standard container
                    }
                    
                    $card_class = ($is_first_page && $wp_query->current_post < 4) ? 'card-featured' : 'card-standard';
                    get_template_part('template-parts/card', null, ['card_class' => $card_class]);
                endwhile;
                
                // 次のページがある場合、アーカイブへの矢印カードを表示
                if (get_next_posts_link()) :
                ?>
                <a href="<?php echo next_posts(0, false); ?>" class="m3-card m3-card--archive-link m3-ripple-host">
                    <div class="m3-card__content">
                        <span class="m3-card__archive-label">VIEW ARCHIVES</span>
                        <h3 class="m3-card__title">もっと過去の記事を見る</h3>
                        <div class="m3-card__archive-icon">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
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