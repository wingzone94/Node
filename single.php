<?php get_header(); ?>

<main id="primary" class="site-main article-view">
    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <!-- 1. 統合されたオシャレなレイヤードカード -->
            <div class="m3-article__header-card">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="m3-article__featured-image">
                        <?php the_post_thumbnail('full'); ?>
                    </div>
                <?php endif; ?>

                <header class="m3-article__header m3-article__header--overlap">
                    <div class="m3-article__accent-line"></div>
                    
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

                    <div class="m3-article__cat-bottom-right">
                        <?php node_the_category_labels(); ?>
                    </div>

                    <div class="m3-article__meta">
                        <div class="m3-article__meta-item">
                            <span class="material-symbols-outlined">calendar_today</span>
                            <time datetime="<?php echo get_the_date('c'); ?>">
                                <?php echo esc_html(get_the_date('Y/m/d')); ?>
                            </time>
                        </div>
                    </div>
                </header>
            </div>

            <!-- 3. AI要約 (プレビュー) -->
            <?php
            $reading_time = apply_filters( 'luminous_get_reading_time', '', get_the_ID() );
            $ai_summary   = apply_filters( 'luminous_get_ai_summary', '', get_the_ID() );
            
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
                    <div class="m3-ai-shimmer"><div class="m3-ai-shimmer__line"></div><div class="m3-ai-shimmer__line"></div><div class="m3-ai-shimmer__line" style="width: 70%;"></div></div>
                    <p class="m3-ai-summary__text hidden" data-summary="<?php echo esc_attr($ai_summary); ?>"></p>
                </div>
                <div class="m3-ai-summary__footer hidden"><span class="m3-badge">AI Generated</span></div>
            </div>
            <?php endif; ?>

            <!-- 4. 記事本文 -->
            <div class="m3-article__body entry-content">
                <?php do_action( 'luminous_before_content', get_the_ID() ); ?>
                
                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before'      => '<nav class="m3-navigation split-post-navigation"><div class="nav-links">',
                    'after'       => '</div></nav>',
                    'link_before' => '<span class="page-numbers">',
                    'link_after'  => '</span>',
                    'separator'   => '',
                ]); ?>

                <?php do_action( 'luminous_after_content', get_the_ID() ); ?>
            </div>

            <!-- 5. 記事フッター (タグ・SNSシェア・著者) -->
            <footer class="m3-article__footer">
                <?php
                $post_tags = get_the_tags();
                if ($post_tags) :
                ?>
                <div class="m3-article__tags">
                    <?php foreach ($post_tags as $tag) : ?>
                        <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="m3-filter-chip">#<?php echo esc_html($tag->name); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php get_template_part('social-share'); ?>
                
                <?php get_template_part('template-parts/card', 'writer'); ?>

                <!-- 前後の記事ナビゲーション -->
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
                        <div class="m3-elevated-nav-card__content">
                            <span class="m3-elevated-nav-card__label">PREVIOUS</span>
                            <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></h4>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($next_post) : 
                        $next_thumb = get_the_post_thumbnail_url($next_post->ID, 'medium_large');
                    ?>
                    <a href="<?php echo get_permalink($next_post->ID); ?>" class="m3-elevated-nav-card m3-ripple-host" style="text-align: right;">
                        <div class="m3-elevated-nav-card__content" style="align-items: flex-end;">
                            <span class="m3-elevated-nav-card__label">NEXT</span>
                            <h4 class="m3-elevated-nav-card__title"><?php echo esc_html(get_the_title($next_post->ID)); ?></h4>
                        </div>
                    </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </footer>
        </article>

        <!-- 6. 関連記事セクション -->
        <?php
        $categories = get_the_category(get_the_ID());
        if ($categories) :
            $cat_ids = array();
            foreach($categories as $cat) $cat_ids[] = $cat->term_id;

            $related_query = new WP_Query(array(
                'category__in'   => $cat_ids,
                'post__not_in'   => array(get_the_ID()),
                'posts_per_page' => 4,
                'orderby'        => 'rand',
            ));

            if ($related_query->have_posts()) :
        ?>
        <section class="m3-related-posts m3-related-posts--simple">
            <h3 class="m3-related-posts__title">RELATED POSTS</h3>
            <div class="m3-related-grid">
                <?php while ($related_query->have_posts()) : $related_query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="m3-related-card">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="m3-related-card__image">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="m3-related-card__content">
                            <h4 class="m3-related-card__title"><?php the_title(); ?></h4>
                        </div>
                    </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
        <?php endif; endif; ?>

        <!-- 7. コメントセクション -->
        <section id="comments" class="m3-comments-section">
            <?php if (comments_open() || get_comments_number()) :
                comments_template();
            endif; ?>
        </section>

        <!-- フローティング目次 (Popup) -->
        <div id="m3-sticky-toc" class="m3-floating-toc-card">
            <div class="m3-floating-toc-card__header">
                <span class="material-symbols-outlined">toc</span>
                <span class="m3-floating-toc-card__title">目次</span>
                <button id="m3-toc-close" class="m3-icon-button"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div id="m3-toc-container" class="m3-floating-toc-card__content"></div>
        </div>

    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
