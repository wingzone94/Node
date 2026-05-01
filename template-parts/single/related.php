<?php
/**
 * Template part for displaying related posts in single.php
 */
$categories = get_the_category(get_the_ID());
if ($categories) :
    $cat_ids = array();
    foreach($categories as $cat) $cat_ids[] = $cat->term_id;

    $related_query = new WP_Query(array(
        'category__in'   => $cat_ids,
        'post__not_in'   => array(get_the_ID()),
        'posts_per_page' => 4,
        'orderby'        => 'rand',
    ));

    if ($related_query->have_posts()) :
?>
<section class="m3-related-posts m3-related-posts--simple">
    <h3 class="m3-related-posts__title">RELATED POSTS</h3>
    <div class="m3-related-grid">
        <?php while ($related_query->have_posts()) : $related_query->the_post(); ?>
            <a href="<?php the_permalink(); ?>" class="m3-related-card">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="m3-related-card__image">
                        <?php the_post_thumbnail('medium_large'); ?>
                    </div>
                <?php endif; ?>
                <div class="m3-related-card__content">
                    <h4 class="m3-related-card__title"><?php the_title(); ?></h4>
                </div>
            </a>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
</section>
<?php endif; endif; ?>
