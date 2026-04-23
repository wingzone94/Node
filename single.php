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

            <header class="m3-article__header">
                <div class="m3-article__meta">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align: middle; margin-right: 4px; opacity: 0.7;">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zM7 12h5v5H7z"/>
                    </svg>
                    <time class="m3-article__date" datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo esc_html(node_get_relative_date(get_the_ID())); ?>
                    </time>
                </div>

                <h1 class="m3-article__title"><?php the_title(); ?></h1>

                <div class="m3-article__meta-info" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: var(--m3-spacing-s);">
                    <?php node_the_category_labels(get_the_ID(), 99); ?>
                    <?php
                    $is_sponsor = get_post_meta(get_the_ID(), '_node_is_sponsor', true);
                    if ($is_sponsor === '1') :
                        $text = get_post_meta(get_the_ID(), '_node_sponsor_text', true) ?: 'SPONSORED';
                        $tooltip = get_post_meta(get_the_ID(), '_node_sponsor_tooltip', true) ?: '本記事はスポンサー提供です。';
                    ?>
                        <span class="m3-label m3-label--sponsor" style="background-color: var(--md-sys-color-primary-container); color: var(--md-sys-color-on-primary-container); margin-left: 8px;" title="<?php echo esc_attr($tooltip); ?>">
                            <span class="material-symbols-outlined" style="font-size: 16px; margin-right: 4px;">volunteer_activism</span>
                            <?php echo esc_html($text); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>
            <?php get_template_part('template-parts/card', 'nexus'); ?>

            
            <?php
            $reading_time = get_post_meta(get_the_ID(), '_node_reading_time', true);
            $ai_summary = get_post_meta(get_the_ID(), '_node_ai_summary_auto', true);
            
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

            <div class="m3-article__body entry-content">
                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before' => '<div class="m3-pagination">' . esc_html__( 'Pages:', 'node' ),
                    'after'  => '</div>',
                ]); ?>
            </div>

            <!-- ソーシャルシェアセクション -->
            <?php get_template_part('social-share'); ?>

            <?php
            $post_tags = get_the_tags();
            if ($post_tags) :
            ?>
            <div class="m3-article__taxonomies" style="margin-top: 48px;">
                <div class="m3-article__taxonomy-section" style="padding: 24px; background: var(--md-sys-color-surface-container-low); border-radius: 24px; border: 1px solid var(--md-sys-color-outline-variant);">
                    <h4 class="m3-article__taxonomy-title" style="display: flex; align-items: center; gap: 12px; font-weight: 900; color: var(--md-sys-color-primary); margin: 0 0 16px 0; font-size: 1.2rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m415-120-302-302q-18-18-28-42.5T75-515v-285q0-33 23.5-56.5T155-880h285q26 0 50.5 10t42.5 28l302 302q35 35 35 86t-35 86L587-120q-35 35-86 35t-86-35ZM155-800v285l346 346 248-248-346-346H155Zm115 160q21 0 35.5-14.5T320-690q0-21-14.5-35.5T270-740q-21 0-35.5 14.5T220-690q0 21 14.5 35.5T270-640Zm-115-160v285-285Z"/></svg>
                        TAGS
                    </h4>
                    <div class="m3-article__taxonomy-list" style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php foreach ($post_tags as $tag) : ?>
                            <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="m3-filter-chip">
                                <?php echo esc_html($tag->name); ?>
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

        <!-- 追従式ナビゲーション (目次) -->
        <aside class="m3-sticky-navigation hidden">
            <nav class="m3-sticky-toc">
                <div class="m3-sticky-toc__header">
                    <span class="material-symbols-outlined">list</span>
                    <span class="m3-sticky-toc__title">CONTENTS</span>
                </div>
                <div id="m3-toc-container"></div>
            </nav>
        </aside>

        <!-- 追従式コメントボタン (FAB) -->
        <a href="#comments" id="m3-sticky-comments" class="m3-fab-comment">
            <span class="material-symbols-outlined">comment</span>
            <?php if (get_comments_number() > 0) : ?>
                <span class="m3-fab-comment__badge"><?php echo get_comments_number(); ?></span>
            <?php endif; ?>
        </a>

        <?php if (comments_open() || get_comments_number()) :
            comments_template();
        endif; ?>

    <?php endwhile; ?>

</main>

<?php get_footer(); ?>
