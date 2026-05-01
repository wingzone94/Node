<?php
/**
 * Template part for displaying the article content in single.php
 */
?>
<div class="m3-article__body entry-content">
    <?php do_action( 'luminous_before_content', get_the_ID() ); ?>
    
    <?php the_content(); ?>
    
    <?php wp_link_pages([
        'before'      => '<nav class="m3-navigation split-post-navigation"><div class="nav-links">',
        'after'       => '</div></nav>',
        'link_before' => '<span class="page-numbers">',
        'link_after'  => '</span>',
        'separator'   => '',
    ]); ?>

    <?php do_action( 'luminous_after_content', get_the_ID() ); ?>
</div>
