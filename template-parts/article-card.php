<?php
/**
 * 記事カードテンプレート (Ver 0.7.0 Pure Edition)
 *
 * @package Node
 */

$post_id    = get_the_ID();
$show_ai    = $args['show_ai'] ?? true;
$ai_summary = $show_ai ? apply_filters( 'luminous_get_ai_summary', '', $post_id ) : '';
$has_ai     = ! empty( trim( $ai_summary ) );
$card_class = $args['card_class'] ?? '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . $card_class . (has_post_thumbnail() ? ' m3-card--has-image' : ' m3-card--no-image')); ?>>

    <div class="m3-card__visual">
        <?php if (has_post_thumbnail()) : ?>
            <a href="<?php the_permalink(); ?>" class="m3-card__image-link" aria-hidden="true" tabindex="-1">
                <?php echo get_the_post_thumbnail(get_the_ID(), 'full', ['alt' => get_the_title(), 'class' => 'm3-card__image']); ?>
                <div class="m3-card__image-gradient"></div>
            </a>
        <?php else : ?>
            <a href="<?php the_permalink(); ?>" class="m3-card__no-image-placeholder">
                <span class="material-symbols-outlined">image_not_supported</span>
            </a>
        <?php endif; ?>

        <!-- Labels Area (Absolute Top-Left) -->
        <div class="m3-card__labels">
            <?php 
            node_the_category_labels(); 
            node_the_post_badges($post_id, 'compact');
            ?>
            <?php if ($has_ai) : ?>
                <span class="m3-label--ai-summary">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span>AI要約</span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="m3-card__content">
        <h3 class="m3-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <?php if ($has_ai) : ?>
            <details class="m3-card__ai-accordion">
                <summary class="m3-card__ai-summary-toggle">
                    <span class="m3-card__ai-toggle-inner">
                        <span class="material-symbols-outlined m3-card__ai-icon">auto_awesome</span>
                        <span class="m3-card__ai-label">Intelligence Summary</span>
                        <span class="material-symbols-outlined m3-card__ai-chevron m3-card__ai-chevron--more">unfold_more</span>
                        <span class="material-symbols-outlined m3-card__ai-chevron m3-card__ai-chevron--less">unfold_less</span>
                    </span>
                </summary>
                <div class="m3-card__ai-body">
                    <p class="m3-card__ai-text">
                        <?php 
                        $clean_summary = strip_tags($ai_summary);
                        echo esc_html( mb_strlen($clean_summary) > 80 ? mb_substr($clean_summary, 0, 80) . '...' : $clean_summary ); 
                        ?>
                    </p>
                </div>
            </details>
        <?php endif; ?>

        <div class="m3-card__footer">
            <div class="m3-card__writer">
                <?php echo get_avatar(get_the_author_meta('ID'), 24); ?>
                <span class="m3-card__writer-name"><?php the_author(); ?></span>
            </div>
            
            <div class="m3-card__date">
                <span class="material-symbols-outlined">calendar_today</span>
                <span><?php echo esc_html(node_get_relative_date($post_id)); ?></span>
            </div>
        </div>
    </div>
</article>
