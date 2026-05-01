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

            <?php
            $reading_info = node_get_article_ranking_info(get_the_ID());
            $reading_time_display = luminous_get_reading_time(get_the_ID());
            if (empty($reading_time_display)) {
                $reading_time_display = $reading_info['reading'] . '分';
            }
            ?>
            <div class="m3-article__reading-badge-expressive" id="m3-hero-reading-badge" style="background-color: <?php echo esc_attr($reading_info['color']); ?>;">
                <div class="m3-reading-badge__gauge">
                    <svg viewBox="0 0 36 36" class="m3-reading-circle__svg">
                        <path class="m3-reading-circle__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="m3-reading-circle__progress" 
                              style="--target-progress: <?php echo esc_attr($reading_info['progress']); ?>;" 
                              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <span class="material-symbols-outlined">timer</span>
                </div>
                <span class="m3-reading-badge-text">
                    <?php echo esc_html($reading_time_display); ?> (約<?php echo esc_html($reading_info['chars']); ?>文字)
                </span>
                <span class="m3-reading-badge-label"><?php echo esc_html($reading_info['label']); ?></span>

                <!-- Hero Info Bubble -->
                <div class="m3-hero-info-bubble" id="m3-hero-info-bubble">
                    <div class="m3-hero-info-bubble__inner">
                        <span class="material-symbols-outlined">info</span>
                        <p>この記事の読了目安です。ブログ全体の平均文字数に基づき、あなたの読書進捗をリアルタイムで計測します。</p>
                    </div>
                </div>
            </div>
        </div>
    </header>
</div>
