<?php
/**
 * 記事カードテンプレート (Ver 1.2 - Aligned Grid)
 *
 * v1.2: 開示バッジ（AI・スポンサー）はアイキャッチに重ねず、
 *       本文エリアのカテゴリ行右端に表示する（画像との衝突防止）。
 *       フッターに追記日（最終更新日）を表示する。
 *
 * @package Node
 */

$post_id    = get_the_ID();
$card_class = $args['card_class'] ?? '';

$has_image = has_post_thumbnail($post_id);
$thumbnail = $has_image ? get_the_post_thumbnail($post_id, 'large', [
    'alt'   => get_the_title(),
    'class' => 'm3-card__image',
    'loading' => 'lazy'
]) : '';

// 開示バッジ（AI・スポンサー）: 出力有無を先に確定して空の行を作らない
ob_start();
node_the_post_badges($post_id, 'compact');
$badges_html = trim(ob_get_clean());

// シリーズバナー: 画像ありは画像右上、画像なしはカテゴリ行右端に出す
$series_banner_html = '';
if (function_exists('node_the_series_banner')) {
    ob_start();
    node_the_series_banner($post_id, $has_image ? '' : 'm3-card__series-banner--no-image');
    $series_banner_html = trim(ob_get_clean());
}

$modified = function_exists('node_get_post_modified_display') ? node_get_post_modified_display($post_id) : null;

$has_category = has_category('', $post_id);
$has_topline_labels = ('' !== $badges_html) || (!$has_image && '' !== $series_banner_html);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . $card_class . ($has_image ? ' m3-card--has-image' : ' m3-card--no-image')); ?>>

    <?php if ($has_image) : ?>
        <div class="m3-card__visual">
            <a href="<?php the_permalink(); ?>" class="m3-card__image-link" aria-hidden="true" tabindex="-1">
                <?php echo $thumbnail; ?>
                <div class="m3-card__image-gradient"></div>
            </a>
            <?php echo $series_banner_html; ?>
        </div>
    <?php endif; ?>

    <div class="m3-card__content">
        <?php if ($has_category || $has_topline_labels) : ?>
            <div class="m3-card__topline">
                <?php if ($has_category) : ?>
                    <div class="m3-card__category-container">
                        <?php node_the_category_labels(); ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_topline_labels) : ?>
                    <div class="m3-card__labels m3-card__labels--inline">
                        <?php echo $badges_html; ?>
                        <?php if (!$has_image) echo $series_banner_html; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h3 class="m3-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>


        <div class="m3-card__footer">
            <div class="m3-card__writer">
                <?php echo get_avatar(get_the_author_meta('ID'), 24); ?>
                <span class="m3-card__writer-name"><?php the_author(); ?></span>
            </div>

            <div class="m3-card__dates">
                <div class="m3-card__date">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <span>
                        <?php
                        // 相対時間「（N時間前）」は極小幅でCSSにより非表示にできるよう分離する
                        $date_text = node_get_relative_date($post_id);
                        if (preg_match('/^(.+?)\s*（(.+)）$/u', $date_text, $date_parts)) {
                            echo esc_html($date_parts[1]);
                            echo '<span class="m3-card__date-rel">（' . esc_html($date_parts[2]) . '）</span>';
                        } else {
                            echo esc_html($date_text);
                        }
                        ?>
                    </span>
                </div>
                <?php if ($modified) : ?>
                    <div class="m3-card__date m3-card__date--modified" title="<?php echo esc_attr(sprintf('追記 %s', $modified['display'])); ?>">
                        <span class="material-symbols-outlined">update</span>
                        <time datetime="<?php echo esc_attr($modified['datetime']); ?>">
                            <?php echo esc_html(sprintf('追記 %s', $modified['display_short'])); ?>
                        </time>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>
