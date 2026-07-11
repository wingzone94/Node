<?php
/**
 * Template part for displaying the article hero section in single.php
 * Reverted to 0.9.1 Centered Style
 */
?>
<div class="m3-article__header-card <?php echo has_post_thumbnail() ? 'has-thumbnail' : 'has-no-thumbnail'; ?>">
    <?php 
    global $post;
    $current_post_id = $post->ID ?? get_the_ID();
    $has_thumb = has_post_thumbnail($current_post_id);
    ?>

    <?php if ($has_thumb) : ?>
        <div class="m3-article__featured-image">
            <?php
            $thumbnail_id = get_post_thumbnail_id($current_post_id);
            $image_data   = wp_get_attachment_image_src($thumbnail_id, 'full');
            if ($image_data) :
                $image_url    = $image_data[0];
                $image_alt    = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) ?: get_the_title();
            ?>
                <img src="<?php echo esc_url($image_url); ?>" 
                     alt="<?php echo esc_attr($image_alt); ?>" 
                     class="m3-article__featured-img"
                     loading="eager" fetchpriority="high" decoding="sync">
            <?php endif; ?>
            <div class="m3-article__featured-gradient"></div>
        </div>
    <?php endif; ?>

    <header class="m3-article__header m3-article__header--overlap">
        <div class="m3-article__accent-line"></div>
        
        <div class="m3-article__header-inner">
            <!-- Category Labels -->
            <div class="m3-article__cat-top">
                <?php node_the_category_labels(); ?>
            </div>

            <!-- AI Disclosure Badges -->
            <?php
            $has_ai_media = get_post_meta(get_the_ID(), '_node_is_ai_generated', true) === '1';
            $has_ai_text  = get_post_meta(get_the_ID(), '_node_is_ai_text_generated', true) === '1';
            if ($has_ai_media || $has_ai_text) :
            ?>
                <div class="m3-article__ai-disclosure-wrapper">
                    <?php node_the_post_badges(get_the_ID(), 'expressive', ['ai']); ?>
                </div>
            <?php endif; ?>

            <?php if (get_post_meta(get_the_ID(), '_node_is_sponsor', true) === '1') : ?>
                <div class="m3-article__sponsor-bubble-wrapper">
                    <?php node_the_post_badges(get_the_ID(), 'full', ['sponsor']); ?>
                </div>
            <?php endif; ?>

            <!-- Main Title -->
            <h1 class="m3-article__title">
                <?php the_title(); ?>
            </h1>

            <!-- Meta Info (Centered) -->
            <div class="m3-article__meta-container">
                <div class="m3-article__meta">
                    <div class="m3-article__meta-item m3-article__date">
                        <span class="material-symbols-outlined">calendar_today</span>
                        <time datetime="<?php echo get_the_date('c'); ?>">
                            <?php echo esc_html(get_the_date('Y/m/d')); ?>
                        </time>
                    </div>
                    <?php
                    // 追記日（v1.2と共通の仕組み）: 手動メタ _node_manual_modified_date を優先し、
                    // 投稿日と異なる場合のみ表示する
                    $manual_modified_date     = get_post_meta( get_the_ID(), '_node_manual_modified_date', true );
                    $has_manual_modified_date = is_string( $manual_modified_date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $manual_modified_date );
                    $manual_display_date      = $has_manual_modified_date ? str_replace( '-', '/', $manual_modified_date ) : '';
                    // 手動メタ（保存時の自動記録含む）があれば公開日と同日でも表示する
                    // （公開後数時間での訂正・追記を当日中に開示するため）。
                    // 未設定時はWP標準の更新日が投稿日と異なる場合のみ表示。
                    $show_modified_date       = $has_manual_modified_date
                        ? true
                        : get_the_modified_date( 'Y/m/d' ) !== get_the_date( 'Y/m/d' );
                    $modified_datetime        = $has_manual_modified_date ? $manual_modified_date : get_the_modified_date( 'c' );
                    $modified_display_date    = $has_manual_modified_date ? $manual_display_date : get_the_modified_date( 'Y/m/d' );
                    ?>
                    <?php if ( $show_modified_date ) : ?>
                    <div class="m3-article__meta-item m3-article__modified">
                        <span class="material-symbols-outlined">update</span>
                        <time datetime="<?php echo esc_attr( $modified_datetime ); ?>">
                            <?php echo esc_html( sprintf( '追記 %s', $modified_display_date ) ); ?>
                        </time>
                    </div>
                    <?php endif; ?>
                    <?php if (comments_open() || get_comments_number() > 0) : ?>
                    <a href="#comments" class="m3-article__meta-item m3-article__comments" id="m3-hero-comment-trigger">
                        <span class="material-symbols-outlined">chat_bubble</span>
                        <span><?php echo get_comments_number(); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expressive Reading Badge (0.9.1 Style) -->
            <?php
            $post_id = get_the_ID();
            $reading_info = node_get_article_ranking_info($post_id);
            $total_seconds = isset($reading_info['reading_seconds'])
                ? max(30, (int) $reading_info['reading_seconds'])
                : max(30, (int) round(($reading_info['chars'] / 550) * 60));
            $minutes = (int) floor($total_seconds / 60);
            $seconds = $total_seconds % 60;
            $reading_time_display = $minutes > 0
                ? sprintf('%d分%02d秒', $minutes, $seconds)
                : sprintf('%d秒', $seconds);
            $reading_progress = isset($reading_info['progress'])
                ? min(100, max(0, (float) $reading_info['progress']))
                : 0;
            $reading_angle = round($reading_progress * 3.6, 2);
            ?>

            <!-- v0.9.1 Style Reading Badge (Restored from Git) -->
            <div class="m3-article__meta-reading">
                <?php
                if ($reading_info['chars'] > 200) : // 極端に短い記事は非表示
                ?>
                <div class="m3-article__reading-badge-expressive m3-ripple-host"
                     id="m3-hero-reading-badge"
                     style="--reading-color: <?php echo esc_attr($reading_info['color']); ?>; --reading-bg: <?php echo esc_attr($reading_info['container_color']); ?>; --reading-rank-color: <?php echo esc_attr($reading_info['badge_color']); ?>; --reading-rank-bg: <?php echo esc_attr($reading_info['badge_bg']); ?>;"
                     role="button"
                     tabindex="0"
                     aria-expanded="false"
                     aria-controls="m3-reading-badge-desc">
                    <div class="m3-reading-badge__gauge">
                        <svg viewBox="0 0 36 36"
                             class="m3-reading-circle__svg"
                             style="--target-progress: <?php echo esc_attr($reading_progress); ?>; --target-angle: <?php echo esc_attr($reading_angle); ?>deg;"
                             aria-hidden="true"
                             focusable="false">
                            <path class="m3-reading-circle__bg" pathLength="100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path class="m3-reading-circle__progress"
                                  pathLength="100"
                                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <circle class="m3-reading-circle__head" cx="18" cy="2.0845" r="2.15" />
                        </svg>
                        <span class="material-symbols-outlined" aria-hidden="true">timer</span>
                    </div>
                    <div class="m3-reading-badge-content">
                        <span class="m3-reading-badge-text m3-reading-badge-text--main">
                            <?php echo esc_html($reading_time_display); ?>
                            <span class="m3-reading-chars">(約<?php echo esc_html(number_format_i18n($reading_info['chars'])); ?>文字)</span>
                        </span>
                        <span class="m3-reading-badge-label">
                            <span class="m3-badge-label-main"><?php echo esc_html($reading_info['label']); ?></span>
                        </span>
                        <span id="m3-reading-badge-desc" class="m3-reading-badge-text m3-reading-badge-text--desc">
                            本文文字数とサイト平均文字数を基準にした読了目安です。
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </header>
</div>
