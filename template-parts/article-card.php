<?php
/**
 * 記事カードテンプレート
 * - AI要約が存在する場合: タイトル上部に ✨ バッジ + タイトル直下に折りたたみ式要約を表示
 * - プログレッシブエンハンスメント: JS無効環境でも <details> でコンテンツ閲覧可能
 *
 * @package Node
 */

$post_id    = get_the_ID();
$ai_summary = get_post_meta($post_id, '_node_ai_summary', true);
$has_ai     = !empty(trim($ai_summary));
$card_class = $args['card_class'] ?? '';
$extra_class = $has_ai ? ' m3-card--has-ai' : '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . $card_class . (has_post_thumbnail() ? ' m3-card--has-image' : ' m3-card--no-image') . $extra_class); ?>>

    <!-- 情報のゾーニング (カード全体の上部オーバーレイ) -->
    <div class="card-overlay">
        <!-- 【左上】属性バッジ (AI生成メディア / スポンサー / AI要約) -->
        <div class="badge-group-left">
            <?php node_the_post_badges($post_id, 'compact'); ?>
            <?php if ($has_ai) : ?>
                <span class="m3-label--ai-summary m3-label--icon-only m3-tooltip-target"
                      data-tooltip="AI要約あり"
                      aria-label="AI要約あり"
                      role="img">
                    <span class="material-symbols-outlined" aria-hidden="true">auto_awesome</span>
                </span>
            <?php endif; ?>
        </div>

        <!-- 【右上】日付表示 -->
        <div class="badge-group-right">
            <div class="badge-date">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zM7 12h5v5H7z"/>
                </svg>
                <span><?php echo esc_html(node_get_relative_date($post_id)); ?></span>
            </div>
        </div>
    </div>

    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>" class="m3-card__image-link" tabindex="-1" aria-hidden="true">
            <div class="m3-card__background">
                <?php the_post_thumbnail('large', ['loading' => 'lazy', 'alt' => '']); ?>
            </div>
        </a>
    <?php else : ?>
        <div class="m3-card__background m3-card__background--empty"></div>
    <?php endif; ?>

    <div class="m3-card__content">
        <h3 class="m3-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <?php if ($has_ai) : ?>
        <!-- AI要約 折りたたみアコーディオン (プログレッシブエンハンスメント: <details>を基盤に使用) -->
        <details class="m3-card__ai-accordion" id="ai-accordion-<?php echo esc_attr($post_id); ?>">
            <summary class="m3-card__ai-summary-toggle" aria-label="AI要約を表示">
                <span class="m3-card__ai-toggle-inner">
                    <span class="material-symbols-outlined m3-card__ai-icon" aria-hidden="true">auto_awesome</span>
                    <span class="m3-card__ai-label">AI 要約</span>
                    <span class="material-symbols-outlined m3-card__ai-chevron" aria-hidden="true">expand_more</span>
                </span>
            </summary>
            <div class="m3-card__ai-body">
                <p class="m3-card__ai-text"><?php echo esc_html($ai_summary); ?></p>
                <div class="m3-card__ai-footer">
                    <span class="m3-badge">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:12px;">smart_toy</span>
                        Gemini Generated
                    </span>
                </div>
            </div>
        </details>
        <?php endif; ?>

        <div class="m3-card__footer-meta">
            <div class="m3-card__writer">
                <?php echo get_avatar(get_the_author_meta('ID'), 24); ?>
                <span class="m3-card__writer-name"><?php the_author(); ?></span>
            </div>

            <div class="m3-card__meta-right">
                <!-- カテゴリを右下へ移動 -->
                <div class="m3-card__category-tag">
                    <?php node_the_category_labels(); ?>
                </div>

                <?php if (get_comments_number() > 0) : ?>
                    <div class="m3-card__comment-count">
                        <span class="material-symbols-outlined" aria-hidden="true">chat_bubble</span>
                        <span><?php echo get_comments_number(); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>
