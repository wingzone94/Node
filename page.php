<?php
/**
 * 固定ページ (Page) 用のテンプレートファイル
 */
get_header(); ?>

<main id="primary" class="site-main article-view">

    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article m3-page-template'); ?>>
            
            <?php if (has_post_thumbnail()) : ?>
                <div class="m3-article__hero" style="border-radius: var(--m3-radius-medium); overflow: hidden; margin-bottom: 24px;">
                    <?php the_post_thumbnail('full', ['style' => 'width: 100%; height: auto; display: block;']); ?>
                    <div class="m3-article__hero-overlay"></div>
                </div>
            <?php endif; ?>

            <header class="m3-article__header" style="text-align: center; margin-bottom: 48px;">
                <h1 class="m3-article__title" style="font-size: 2.2rem; font-weight: 900; letter-spacing: -0.02em;"><?php the_title(); ?></h1>
            </header>

            <div class="m3-article__body entry-content">
                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before'      => '<nav class="m3-navigation split-post-navigation"><div class="nav-links">',
                    'after'       => '</div></nav>',
                    'link_before' => '<span class="page-numbers">',
                    'link_after'  => '</span>',
                    'separator'   => '',
                ]); ?>
            </div>

        </article>

    <?php endwhile; ?>

</main>

<?php get_footer(); ?>
