<?php 
/**
 * 特集（SPOTLIGHT）カテゴリ専用アーカイブテンプレート
 */
get_header(); 
global $wp_query;
?>

<main id="primary" class="site-main m3-home-layout">
    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();
    ?>

    <section class="m3-archive-header m3-surface m3-section-spacing" aria-labelledby="spotlight-archive-title">
        <div class="m3-headlines__header" style="border-bottom: none; margin-bottom: 0;">
            <h1 id="spotlight-archive-title" class="m3-headlines__title m3-section-title" style="margin-bottom: 0;">
                <span class="material-symbols-outlined" aria-hidden="true" style="font-size: 1.2em; vertical-align: middle;">local_fire_department</span>
                SPOTLIGHT <span class="m3-section-title__sub">特集</span>
            </h1>
        </div>
        <?php if (category_description()) : ?>
            <div class="m3-archive-header__desc" style="margin-top: 16px; color: var(--md-sys-color-on-surface-variant);">
                <?php echo category_description(); ?>
            </div>
        <?php endif; ?>
    </section>

    <?php 
    // サブカテゴリ（特集）をピルとして表示
    $spotlight_cats = function_exists('node_get_spotlight_categories') ? node_get_spotlight_categories() : [];
    if (!empty($spotlight_cats)) :
    ?>
    <section class="special-features m3-surface m3-section-spacing" style="margin-bottom: 24px;">
        <h2 class="m3-section-title" style="font-size: 1.2rem; margin-bottom: 16px;">特集一覧</h2>
        <div class="special-features__pills" style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($spotlight_cats as $cat) : ?>
                <a href="<?php echo esc_url($cat['url']); ?>" 
                   class="m3-spotlight-badge m3-ripple-host" 
                   style="background-color: <?php echo esc_attr($cat['color']); ?>; color: #ffffff; padding: 8px 16px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; font-size: 0.9rem; font-weight: bold;"
                   aria-label="<?php echo esc_attr($cat['name']); ?>特集へ">
                   <span class="material-symbols-outlined" aria-hidden="true" style="margin-right: 4px; font-size: 1.2em;">auto_awesome</span> 
                   <?php echo esc_html($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container m3-post-grid--list">
                <?php
                while (have_posts()) : the_post();
                    // SPOTLIGHTの記事は標準カードで表示
                    get_template_part('template-parts/card', null, [
                        'card_class' => 'card-standard',
                        'show_ai'    => false
                    ]);
                endwhile;
                ?>
            </div>
        <?php else : ?>
            <div class="m3-no-results m3-surface m3-section-spacing" style="padding: 24px;">
                <p>現在、特集記事はありません。</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (have_posts()) : ?>
        <div class="m3-navigation">
            <?php 
            the_posts_pagination([
                'mid_size'  => 2,
                'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
                'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
            ]); 
            ?>
        </div>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
