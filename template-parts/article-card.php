<?php
/**
 * 記事カードテンプレート (Ver 0.8.1 - Archive Parity)
 *
 * @package Node
 */

$post_id    = get_the_ID();
$show_ai    = $args['show_ai'] ?? true;
$ai_summary = $show_ai ? apply_filters( 'luminous_get_ai_summary', '', $post_id ) : '';
$has_ai     = ! empty( trim( $ai_summary ) );
$card_class = $args['card_class'] ?? '';

// archive.php等と共通の確実な取得方法
$has_image = has_post_thumbnail($post_id);
echo "<!-- DEBUG: Card Post ID = {$post_id}, Has Image = " . ($has_image ? 'TRUE' : 'FALSE') . " -->";
$thumbnail = $has_image ? get_the_post_thumbnail($post_id, 'large', [
    'alt'   => get_the_title(),
    'class' => 'm3-card__image',
    'loading' => 'lazy'
]) : '';
if ($has_image && empty($thumbnail)) {
    echo "<!-- DEBUG: Thumbnail HTML is empty for some reason -->";
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . $card_class . ($has_image ? ' m3-card--has-image' : ' m3-card--no-image')); ?>>

    <?php if ($has_image) : ?>
        <div class="m3-card__visual">
            <a href="<?php the_permalink(); ?>" class="m3-card__image-link" aria-hidden="true" tabindex="-1">
                <?php echo $thumbnail; ?>
                <div class="m3-card__image-gradient"></div>
            </a>

            <!-- Badges -->
            <div class="m3-card__labels">
                <?php node_the_post_badges($post_id, 'compact'); ?>
                <?php if ($has_ai) : ?>
                    <span class="m3-label--ai-summary">
                        <span class="material-symbols-outlined">auto_awesome</span>
                        <span>AI要約</span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="m3-card__content">
        <?php if (!$has_image) : ?>
            <div class="m3-card__labels m3-card__labels--no-image">
                <?php node_the_post_badges($post_id, 'compact'); ?>
            </div>
        <?php endif; ?>

        <?php if (has_category()) : ?>
            <!-- Category BELOW image -->
            <div class="m3-card__category-container">
                <?php node_the_category_labels(); ?>
            </div>
        <?php endif; ?>

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
