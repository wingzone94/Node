<?php 
/**
 * ニュース（HEADLINE）カテゴリ専用アーカイブテンプレート
 */
get_header(); 
global $wp_query;
?>

<main id="primary" class="site-main m3-home-layout">
    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();
    ?>

    <section class="m3-archive-header m3-surface m3-section-spacing" aria-labelledby="headline-archive-title">
        <div class="m3-headlines__header" style="border-bottom: none; margin-bottom: 0;">
            <h1 id="headline-archive-title" class="m3-headlines__title m3-section-title" style="margin-bottom: 0;">
                <span class="material-symbols-outlined" aria-hidden="true" style="font-size: 1.2em; vertical-align: middle;">campaign</span>
                HEADLINE <span class="m3-section-title__sub">速報</span>
            </h1>
        </div>
        <?php if (category_description()) : ?>
            <div class="m3-archive-header__desc" style="margin-top: 16px; color: var(--md-sys-color-on-surface-variant);">
                <?php echo category_description(); ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="m3-headlines m3-surface m3-section-spacing" style="margin-bottom: 24px;">
        <div class="m3-headlines__list" role="list">
        <?php if (have_posts()) : ?>
            <?php
            while (have_posts()) : the_post();
                get_template_part('template-parts/card-headline');
            endwhile;
            ?>
        <?php else : ?>
            <div class="m3-no-results" style="padding: 24px;">
                <p>現在、ニュース記事はありません。</p>
            </div>
        <?php endif; ?>
        </div>
    </section>

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
