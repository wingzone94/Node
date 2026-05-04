<?php
/**
 * 記事カードテンプレート
 * - AI要約が存在する場合: タイトル上部に ✨ バッジ + タイトル直下に折りたたみ式要約を表示
 * - プログレッシブエンハンスメント: JS無効環境でも <details> でコンテンツ閲覧可能
 *
 * @package Node
 */

$post_id    = get_the_ID();
$show_ai    = $args['show_ai'] ?? true;
// Hook 経由で AI 要約を取得 (プラグイン無効時は空文字 / 表示オフ時は取得しない)
$ai_summary = $show_ai ? apply_filters( 'luminous_get_ai_summary', '', $post_id ) : '';
$has_ai     = ! empty( trim( $ai_summary ) );
$card_class = $args['card_class'] ?? '';
$extra_class = $has_ai ? ' m3-card--has-ai' : '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('m3-card ' . $card_class . (has_post_thumbnail() ? ' m3-card--has-image' : ' m3-card--no-image') . $extra_class); ?>>

    <?php 
        global $wp_query;
        $is_priority = ($wp_query->current_post < 2 && !is_paged());
        $attr = $is_priority ? ['fetchpriority' => 'high', 'loading' => 'eager'] : ['loading' => 'lazy'];
        $attr['alt'] = get_the_title();
    ?>
    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>" class="m3-card__image-link" aria-hidden="true" tabindex="-1">
            <?php the_post_thumbnail('medium_large', ['alt' => get_the_title()]); ?>
            
            <!-- AI Catchphrase Badge (3-10 chars) -->
            <?php $short_summary = get_post_meta(get_the_ID(), 'node_short_summary', true); ?>
            <?php if (!empty($short_summary)) : ?>
                <div class="m3-ai-catchphrase">
                    <span class="material-symbols-outlined" style="font-size: 14px;">auto_awesome</span>
                    <?php echo esc_html($short_summary); ?>
                </div>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <div class="m3-card__content">
        <h3 class="m3-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <!-- 新設: タイトル下の属性・カテゴリーラベルグループ (可読性重視) -->
        <div class="m3-card__meta-badges">
            <?php 
            node_the_category_labels(); 
            node_the_post_badges($post_id, 'compact');
            ?>

            <?php if ($has_ai) : ?>
                <span class="m3-label--ai-summary" title="AI要約あり">
                    <span class="material-symbols-outlined" aria-hidden="true">auto_awesome</span>
                    <span>AI要約</span>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($has_ai) : ?>
        <!-- AI要約 折りたたみアコーディオン (プログレッシブエンハンスメント: <details>を基盤に使用) -->
        <?php 
        $ai_vibe_color = '#FF9800'; // オレンジで統一
        $ai_keywords   = get_post_meta($post_id, '_node_ai_keywords', true) ?: [];
        ?>
        <details class="m3-card__ai-accordion" id="ai-accordion-<?php echo esc_attr($post_id); ?>" style="--ai-vibe-color: <?php echo esc_attr($ai_vibe_color); ?>;">
            <summary class="m3-card__ai-summary-toggle" aria-label="AI要約を表示">
                <span class="m3-card__ai-toggle-inner">
                    <span class="material-symbols-outlined m3-card__ai-icon" aria-hidden="true">auto_awesome</span>
                    <span class="m3-card__ai-label">AI 要約</span>
                    <span class="material-symbols-outlined m3-card__ai-chevron" aria-hidden="true">expand_more</span>
                </span>
            </summary>
            <div class="m3-card__ai-body">
                <p class="m3-card__ai-text">
                    <?php 
                    $clean_summary = strip_tags($ai_summary);
                    echo esc_html( mb_strlen($clean_summary) > 80 ? mb_substr($clean_summary, 0, 80) . '...' : $clean_summary ); 
                    ?>
                </p>
                
                <?php if (!empty($ai_keywords)) : ?>
                    <div class="m3-card__ai-keywords">
                        <?php foreach (array_slice($ai_keywords, 0, 3) as $keyword) : ?>
                            <span class="m3-ai-keyword-chip">#<?php echo esc_html($keyword); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="m3-card__ai-footer">
                    <span class="m3-badge">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:12px;">smart_toy</span>
                        Gemini 3.1 Pro
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
                <?php if (get_comments_number() > 0) : ?>
                    <div class="m3-card__comment-count">
                        <span class="material-symbols-outlined" aria-hidden="true">chat_bubble</span>
                        <span><?php echo get_comments_number(); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- カード全体の右下に配置される日付ラベル -->
    <div class="badge-date">
        <span class="material-symbols-outlined" aria-hidden="true" style="font-size: 14px;">calendar_today</span>
        <span><?php echo esc_html(node_get_relative_date($post_id)); ?></span>
    </div>
</article>
