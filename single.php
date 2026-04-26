<?php get_header(); ?>

<main id="primary" class="site-main article-view">

    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <?php if (has_post_thumbnail()) : ?>
                <div class="m3-article__hero">
                    <?php the_post_thumbnail('full'); ?>
                    <div class="m3-article__hero-overlay"></div>
                </div>
            <?php endif; ?>

            <div class="m3-article__header-card">
                <header class="m3-article__header">
                    <div class="m3-article__top-badges">
                        <?php node_the_post_badges(get_the_ID(), 'full'); ?>
                    </div>
                    <div class="m3-article__meta">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align: middle; margin-right: 4px; opacity: 0.7;">
                            <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zM7 12h5v5H7z"/>
                        </svg>
                        <time class="m3-article__date" datetime="<?php echo get_the_date('c'); ?>">
                            <?php echo esc_html(node_get_relative_date(get_the_ID())); ?>
                        </time>
                    </div>

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

                    <div class="m3-article__meta-info">
                        <?php node_the_category_labels(); ?>
                    </div>
                </header>
            </div>

            <?php
            // Hook: プラグインによるヘッダー直後の拡張ポイント (Nexus カード等)
            if ( function_exists( 'luminous_after_article_header' ) ) {
                luminous_after_article_header( get_the_ID() );
            } else {
                get_template_part( 'template-parts/card', 'nexus' );
            }
            ?>

            <?php
            // Hook 経由で AI 要約・読了時間を取得 (プラグイン無効時はフォールバック)
            $reading_time = function_exists( 'luminous_get_reading_time' )
                ? luminous_get_reading_time( get_the_ID() )
                : get_post_meta( get_the_ID(), '_node_reading_time', true );
            $ai_summary = function_exists( 'luminous_get_ai_summary' )
                ? luminous_get_ai_summary( get_the_ID() )
                : get_post_meta( get_the_ID(), '_node_ai_summary', true );
            
            if ($ai_summary) :
            ?>
            <div class="m3-ai-summary">
                <div class="m3-ai-summary__header">
                    <h3 class="m3-ai-summary__title"><span class="material-symbols-outlined">auto_awesome</span> AI Summary</h3>
                    <?php if ($reading_time) : ?>
                    <span class="m3-filter-chip"><span class="material-symbols-outlined">schedule</span> <?php echo esc_html($reading_time); ?></span>
                    <?php endif; ?>
                </div>
                <div class="m3-ai-summary__content">
                    <div class="m3-ai-shimmer">
                        <div class="m3-ai-shimmer__line"></div>
                        <div class="m3-ai-shimmer__line"></div>
                        <div class="m3-ai-shimmer__line" style="width: 70%;"></div>
                    </div>
                    <p class="m3-ai-summary__text hidden" data-summary="<?php echo esc_attr($ai_summary); ?>"></p>
                </div>
                <div class="m3-ai-summary__footer hidden">
                    <span class="m3-badge">AI Generated</span>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Hook: CERO Z 年齢確認ゲート
            if ( function_exists( 'luminous_requires_age_gate' ) && luminous_requires_age_gate( get_the_ID() ) ) {
                luminous_render_age_gate( get_the_ID() );
            }
            ?>

            <div class="m3-article__body entry-content">
                <!-- 広告表示エリア (Top) -->
                <?php if (function_exists('node_the_ad_area')) node_the_ad_area('top'); ?>

                <?php
                // Hook: 記事本文の前に挿入
                if ( function_exists( 'luminous_before_content' ) ) {
                    luminous_before_content( get_the_ID() );
                }
                ?>

                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before'      => '<nav class="m3-navigation split-post-navigation"><div class="nav-links">',
                    'after'       => '</div></nav>',
                    'link_before' => '<span class="page-numbers">',
                    'link_after'  => '</span>',
                    'separator'   => '',
                ]); ?>

                <?php
                // Hook: 記事本文の後に挿入
                if ( function_exists( 'luminous_after_content' ) ) {
                    luminous_after_content( get_the_ID() );
                }
                ?>

                <!-- 広告表示エリア (Bottom) -->
                <?php if (function_exists('node_the_ad_area')) node_the_ad_area('bottom'); ?>
            </div>

            <!-- ソーシャルシェアセクション -->
            <?php get_template_part('social-share'); ?>

            <?php
            $post_tags = get_the_tags();
            if ($post_tags) :
            ?>
            <div class="m3-article__taxonomies" style="margin-top: 48px;">
                <div class="m3-article__taxonomy-section" style="padding: 24px; background: var(--md-sys-color-surface-container-low); border-radius: 24px; border: 1px solid var(--md-sys-color-outline-variant);">
                    <h4 class="m3-article__taxonomy-title" style="display: flex; align-items: center; gap: 12px; font-weight: 900; color: var(--md-sys-color-on-surface-variant); margin: 0 0 16px 0; font-size: 1.2rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m415-120-302-302q-18-18-28-42.5T75-515v-285q0-33 23.5-56.5T155-880h285q26 0 50.5 10t42.5 28l302 302q35 35 35 86t-35 86L587-120q-35 35-86 35t-86-35ZM155-800v285l346 346 248-248-346-346H155Zm115 160q21 0 35.5-14.5T320-690q0-21-14.5-35.5T270-740q-21 0-35.5 14.5T220-690q0 21 14.5 35.5T270-640Zm-115-160v285-285Z"/></svg>
                        TAGS
                    </h4>
                    <div class="m3-article__taxonomy-list" style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php foreach ($post_tags as $tag) : ?>
                            <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="m3-filter-chip">
                                #<?php echo esc_html($tag->name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ライター情報 -->
            <?php get_template_part('template-parts/card', 'writer'); ?>

            <footer class="m3-article__footer">
                <?php get_template_part('template-parts/card', 'game'); ?>
                
                <?php
                $prev_post = get_previous_post();
                $next_post = get_next_post();
                if ($prev_post || $next_post) :
                ?>
                <nav class="m3-post-navigation">
                    <?php if ($prev_post) : 
                        $prev_thumb = get_the_post_thumbnail_url($prev_post->ID, 'medium_large');
                    ?>
                    <a href="<?php echo get_permalink($prev_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host">
                        <?php if ($prev_thumb) : ?>
                            <div class="m3-elevated-nav-card__bg" style="background-image: url('<?php echo esc_url($prev_thumb); ?>');"></div>
                        <?php endif; ?>
                        <div class="m3-elevated-nav-card__overlay"></div>
                        <div class="m3-elevated-nav-card__content">
                            <span class="m3-elevated-nav-card__label"><span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span> PREVIOUS</span>
                            <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></h4>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($next_post) : 
                        $next_thumb = get_the_post_thumbnail_url($next_post->ID, 'medium_large');
                    ?>
                    <a href="<?php echo get_permalink($next_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host" style="text-align: right;">
                        <?php if ($next_thumb) : ?>
                            <div class="m3-elevated-nav-card__bg" style="background-image: url('<?php echo esc_url($next_thumb); ?>');"></div>
                        <?php endif; ?>
                        <div class="m3-elevated-nav-card__overlay"></div>
                        <div class="m3-elevated-nav-card__content" style="align-items: flex-end;">
                            <span class="m3-elevated-nav-card__label">NEXT <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span></span>
                            <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($next_post->ID)); ?></h4>
                        </div>
                    </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </footer>

        </article>

        <?php if (comments_open() || get_comments_number()) :
            comments_template();
        endif; ?>

    <?php endwhile; ?>

</main>

<?php get_footer(); ?>
