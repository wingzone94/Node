<?php
/**
 * Archive template (Ver 0.7.0)
 * Handles categories, tags, authors, and dates.
 */
get_header(); ?>

<main id="primary" class="site-main">

    <?php 
    // SEO: パンくずリスト
    if ( function_exists('node_the_breadcrumbs') ) {
        node_the_breadcrumbs();
    }
    ?>

    <header class="m3-archive-header m3-surface m3-surface--variant">
        <h1 class="m3-section-title">
            <?php 
            if ( function_exists('node_get_archive_title') ) {
                echo esc_html( node_get_archive_title() );
            } else {
                the_archive_title();
            }
            ?>
        </h1>
        <?php if ( get_the_archive_description() ) : ?>
            <div class="m3-archive-description"><?php the_archive_description(); ?></div>
        <?php endif; ?>
    </header>

    <section class="m3-post-grid" aria-label="アーカイブ記事一覧">
        <div class="m3-surface m3-surface--articles m3-section-spacing">
            <div class="m3-post-grid__container is-articles-grid">
                <?php if (have_posts()) : ?>
                    <?php
                    while (have_posts()) : the_post();
                        get_template_part('template-parts/card', null, [
                            'card_class' => 'card-standard',
                            'show_ai'    => false
                        ]);
                    endwhile;
                    ?>
                <?php else : ?>
                    <div class="m3-empty-state">
                        <h2 class="m3-empty-title">記事が見つかりませんでした</h2>
                        <p class="m3-empty-description">お探しのカテゴリやタグにはまだ記事が投稿されていないようです。</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

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
