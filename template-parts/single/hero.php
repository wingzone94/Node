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
            $post_id = get_the_ID();
            $post_obj = get_post($post_id);
            $content = $post_obj->post_content;
            $char_count = mb_strlen(strip_tags($content));
            $reading_minutes = ceil($char_count / 600);

            // カラーランクの定義
            if ($char_count < 800) {
                $rank_label = '短い';
                $rank_color = '#C8E6C9'; // 緑
                $rank_on_color = '#1B5E20';
            } elseif ($char_count < 1500) {
                $rank_label = 'やや短い';
                $rank_color = '#DCEDC8'; // 黄緑
                $rank_on_color = '#33691E';
            } elseif ($char_count < 3000) {
                $rank_label = '標準';
                $rank_color = '#E3F2FD'; // ジェントル・ブルー (Primary Container風)
                $rank_on_color = '#0D47A1';
            } elseif ($char_count < 5000) {
                $rank_label = 'やや長い';
                $rank_color = '#FFF9C4'; // 黄
                $rank_on_color = '#F57F17';
            } else {
                $rank_label = '長い';
                $rank_color = '#FFDAD6'; // 赤 (Material Error Container)
                $rank_on_color = '#410002';
            }

            if ( class_exists( '\Node\Tools\Content\ReadingTime' ) ) {
                $reading_info = \Node\Tools\Content\ReadingTime::get_instance()->get_article_ranking_info($post_id);
            } else {
                $reading_info = [
                    'color' => $rank_color,
                    'on_color' => $rank_on_color,
                    'progress' => 50, 
                    'chars' => number_format($char_count), 
                    'label' => $rank_label,
                    'reading' => $reading_minutes
                ];
            }

            // フォールバック処理
            if ($reading_info['chars'] === '---' || empty($reading_info['chars'])) {
                $reading_info['chars'] = number_format($char_count);
            }
            if ($reading_info['label'] === '標準' || empty($reading_info['label'])) {
                $reading_info['label'] = $rank_label;
                $reading_info['color'] = $rank_color;
                $reading_info['on_color'] = $rank_on_color;
            }

            $reading_time_display = luminous_get_reading_time($post_id);
            if (empty($reading_time_display)) {
                $reading_time_display = $reading_info['reading'] . '分';
            }
            ?>
            <div class="m3-article__reading-badge-expressive" id="m3-hero-reading-badge" 
                 style="background-color: <?php echo esc_attr($reading_info['color']); ?>; 
                        color: <?php echo esc_attr($reading_info['on_color']); ?>;
                        --badge-accent: <?php echo esc_attr($reading_info['on_color']); ?>;" 
                 aria-hidden="true">
                <div class="m3-reading-badge__gauge">
                    <svg viewBox="0 0 36 36" class="m3-reading-circle__svg" aria-hidden="true" focusable="false">
                        <path class="m3-reading-circle__bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="m3-reading-circle__progress" 
                              style="--target-progress: <?php echo esc_attr($reading_info['progress']); ?>;" 
                              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <span class="material-symbols-outlined" aria-hidden="true">timer</span>
                </div>
                <span class="m3-reading-badge-text">
                    <?php echo esc_html($reading_time_display); ?> (約<?php echo esc_html($reading_info['chars']); ?>文字)
                </span>
                <span class="m3-reading-badge-label">
                    <span class="m3-badge-label-main"><?php echo esc_html($reading_info['label']); ?></span>
                    <span class="m3-badge-label-desc">読了目安：文字数と画像から計算しています</span>
                </span>
            </div>
        </div>
    </header>
</div>
