<?php get_header(); ?>

<main id="primary" class="site-main article-view">

    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <?php if (has_post_thumbnail()) : ?>
                <div class="m3-article__hero">
                    <?php the_post_thumbnail('full'); ?>
                    <div class="m3-article__hero-overlay"></div>
                    <?php 
                    $is_sponsor = get_post_meta(get_the_ID(), '_node_is_sponsor', true);
                    if ($is_sponsor === '1') : 
                        $text = get_post_meta(get_the_ID(), '_node_sponsor_text', true) ?: 'SPONSORED';
                        $tooltip = get_post_meta(get_the_ID(), '_node_sponsor_tooltip', true) ?: '本記事はスポンサー提供です。';
                    ?>
                        <span class="m3-label--sponsor-rtx" data-tooltip-text="<?php echo esc_attr($tooltip); ?>">
                            <?php echo esc_html($text); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <header class="m3-article__header">
                <div class="m3-article__meta">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align: middle; margin-right: 4px; opacity: 0.7;">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zM7 12h5v5H7z"/>
                    </svg>
                    <time class="m3-article__date" datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo node_get_relative_date(get_the_ID()); ?>
                    </time>
                </div>
                
                <h1 class="m3-article__title"><?php the_title(); ?></h1>

                <div class="m3-article__meta-info">
                    <?php node_the_category_labels(get_the_ID(), 1); ?>
                </div>
            </header>

            <?php get_template_part('template-parts/card', 'nexus'); ?>

            <div class="m3-article__body entry-content">
                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before' => '<div class="m3-pagination">' . esc_html__( 'Pages:', 'node' ),
                    'after'  => '</div>',
                ]); ?>
            </div>

            <!-- ソーシャルシェアセクション -->
            <?php get_template_part('social-share'); ?>

            <!-- ライター情報 -->
            <?php get_template_part('template-parts/card', 'writer'); ?>

            <footer class="m3-article__footer">
                <?php get_template_part('template-parts/card', 'game'); ?>
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