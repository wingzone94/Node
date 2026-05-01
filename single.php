<?php get_header(); ?>

<main id="primary" class="site-main article-view">
    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <?php get_template_part('template-parts/single/hero'); ?>

            <?php
            $ai_summary = apply_filters( 'luminous_get_ai_summary', '', get_the_ID() );
            if ( $ai_summary ) {
                get_template_part( 'template-parts/ai-summary', null, [ 'summary' => $ai_summary, 'mode' => 'single' ] );
            }
            ?>

            <?php get_template_part('template-parts/single/content'); ?>

            <?php get_template_part('template-parts/single/footer'); ?>
            
        </article>

        <?php get_template_part('template-parts/single/related'); ?>

        <section id="comments" class="m3-comments-section">
            <?php if (comments_open() || get_comments_number()) :
                comments_template();
            endif; ?>
        </section>

        <?php get_template_part('template-parts/single/toc'); ?>

<?php endwhile; ?>
</main>

<?php get_footer(); ?>
