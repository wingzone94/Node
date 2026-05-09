<?php
/**
 * 簡略化された記事カード (HEADLINES用)
 */
$post_id = get_the_ID();
?>
<a href="<?php the_permalink(); ?>" class="m3-headline-card m3-ripple-host">
    <div class="m3-headline-card__meta">
        <span class="m3-headline-card__date"><?php echo get_the_date('m.d'); ?></span>
        <?php
        $categories = get_the_category();
        if (!empty($categories)) :
            $cat = $categories[0];
            echo '<span class="m3-headline-card__category" style="color: var(--md-sys-color-primary);">' . esc_html($cat->name) . '</span>';
        endif;
        ?>
    </div>
    <h3 class="m3-headline-card__title"><?php the_title(); ?></h3>
    <div class="m3-headline-card__icon">
        <span class="material-symbols-outlined">chevron_right</span>
    </div>
</a>
