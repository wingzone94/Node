<?php 
get_header(); 
global $wp_query; // 全〇〇件 を取得するために必須
?>

<main id="primary" class="site-main">

    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();
    ?>
    
    <section class="m3-archive-header m3-surface m3-section-spacing" aria-labelledby="archive-title">
        <div class="m3-headlines__header" style="border-bottom: none; margin-bottom: 0;">
            <h1 id="archive-title" class="m3-headlines__title m3-section-title" style="margin-bottom: 0;">
                <span class="material-symbols-outlined" aria-hidden="true" style="font-size: 1.2em; vertical-align: middle;">category</span>
                <?php single_cat_title(); ?> <span class="m3-section-title__sub">アーカイブ</span>
            </h1>
        </div>
        <?php if (category_description()) : ?>
            <div class="m3-archive-header__desc" style="margin-top: 16px; color: var(--md-sys-color-on-surface-variant);">
                <?php echo category_description(); ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container m3-post-grid--list">
                <?php
                while (have_posts()) : the_post();
                    // Homeと同様に最初の4件を Featured 扱いにする (Paged でない場合)
                    $card_class = ($wp_query->current_post < 4 && !is_paged()) ? 'card-featured' : 'card-standard';
                    
                    // テンプレートパーツの呼び出し (v0.7.0仕様)
                    get_template_part('template-parts/card', null, [
                        'card_class' => $card_class,
                        'show_ai'    => false
                    ]);
                endwhile;
                ?>
            </div>
        <?php else : ?>
            <div class="m3-no-results">
                <p>現在、該当する記事はありません。</p>
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