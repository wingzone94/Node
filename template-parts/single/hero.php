<?php
/**
 * Template part for displaying the article hero section in single.php
 */
?>
<div class="m3-article__header-card">
    <?php if (has_post_thumbnail()) : ?>
        <div class="m3-article__featured-image">
            <?php the_post_thumbnail('full'); ?>
        </div>
    <?php endif; ?>

    <header class="m3-article__header m3-article__header--overlap">
        <div class="m3-article__accent-line"></div>
        
        <!-- カテゴリ表示 (最上部へ移動) -->
        <div class="m3-article__cat-top">
            <?php node_the_category_labels(); ?>
        </div>
        
        <!-- タイトルの上の吹き出しスポンサー表示 -->
        <?php if (get_post_meta(get_the_ID(), '_node_is_sponsor', true) === '1') : ?>
            <div class="m3-article__sponsor-bubble-wrapper">
                <?php node_the_post_badges(get_the_ID(), 'full'); ?>
            </div>
        <?php endif; ?>

        <?php
        $title_len = mb_strlen(get_the_title());
        $title_class = 'm3-article__title';
        if ($title_len > 40) {
            $title_class .= ' is-long';
        } elseif ($title_len > 25) {
            $title_class .= ' is-medium';
        }
        ?>
        <h1 class="<?php echo esc_attr($title_class); ?>"><?php the_title(); ?></h1>

        <div class="m3-article__meta-container">
            <!-- 日付表示 -->
            <div class="m3-article__meta">
                <div class="m3-article__meta-item m3-article__date">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <time datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo esc_html(get_the_date('Y/m/d')); ?>
                    </time>
                </div>
            </div>

            <!-- 読了時間・文字数ゲージ（分離して表示 / 200文字以上の記事のみ表示） -->
            <div class="m3-article__meta-reading">
                <?php
                $reading_info = node_get_article_ranking_info(get_the_ID());
                $reading_time_display = luminous_get_reading_time(get_the_ID());
                if (empty($reading_time_display)) {
                    $reading_time_display = $reading_info['reading'] . '分';
                }
                
                if ($reading_info['chars'] > 200) : // 極端に短い記事は非表示
                ?>
                <div class="m3-article__reading-meta m3-reading-gauge--circle m3-ripple-host" 
                     id="m3-reading-meta-toggle"
                     style="--reading-color: <?php echo esc_attr($reading_info['color']); ?>; --reading-bg: <?php echo esc_attr($reading_info['container_color']); ?>;">
                    
                    <div class="m3-article__reading-main">
                        <div class="m3-reading-circle">
                            <svg viewBox="0 0 36 36" class="m3-reading-circle__svg">
                                <path class="m3-reading-circle__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="m3-reading-circle__progress" 
                                      stroke-dasharray="100, 100" 
                                      stroke-dashoffset="100"
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                            <span class="material-symbols-outlined m3-icon-animate-timer">timer</span>
                        </div>
                        
                        <div class="m3-reading-text-group">
                            <div class="m3-reading-view-default">
                                <span class="m3-reading-time-val m3-animate-reveal" style="--m3-reveal-delay: 2.1s;">
                                    <?php echo esc_html($reading_time_display); ?>
                                    <span class="m3-reading-chars">(約<?php echo esc_html($reading_info['chars']); ?>文字)</span>
                                </span>
                                <span class="m3-reading-time-label m3-animate-reveal" style="--m3-reveal-delay: 2.2s;">
                                    <?php echo esc_html($reading_info['label']); ?>
                                </span>
                            </div>
                            <div class="m3-reading-view-info">
                                読了時間・文字数を自動で判定して表示しています。
                            </div>
                        </div>

                        <div class="m3-reading-info-icon">
                            <span class="material-symbols-outlined">info</span>
                        </div>
                    </div>
                </div>
                <?php endif; // End check for > 200 chars ?>
            </div>
        </div>
    </header>
</div>
