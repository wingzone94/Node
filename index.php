<?php get_header(); ?>

<main style="max-width: var(--max-width); margin: 2rem auto; padding: 0 1rem;">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article style="background: white; padding: 2rem; border-radius: var(--m3-radius-large); margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <?php the_excerpt(); ?>
        </article>
    <?php endwhile; else : ?>
        <p>投稿が見つかりませんでした。</p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>