<?php get_header(); ?>

<main id="primary" class="site-main article-view">
    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <?php get_template_part('template-parts/single/hero'); ?>

            <?php
            $ai_summary = apply_filters( 'luminous_get_ai_summary', '', get_the_ID() );
            if ( $ai_summary ) {
                get_template_part( 'template-parts/ai-summary', null, [ 'summary' => $ai_summary, 'mode' => 'single' ] );
            }
            ?>

            <?php get_template_part('template-parts/single/content'); ?>

            <?php get_template_part('template-parts/single/footer'); ?>
            
        </article>

        <?php get_template_part('template-parts/single/related'); ?>

        <section id="comments" class="m3-comments-section">
            <?php if (comments_open() || get_comments_number()) :
                comments_template();
            endif; ?>
        </section>

        <?php get_template_part('template-parts/single/toc'); ?>

        <!-- フローティングアクションボタン群 -->
        <div class="m3-action-stack">
            <button id="m3-toc-trigger" class="m3-fab m3-tooltip-target" title="目次を表示" data-tooltip="目次を表示">
                <span class="material-symbols-outlined">toc</span>
            </button>
            <?php if (comments_open() || get_comments_number()) : ?>
                <button id="m3-scroll-to-comments" class="m3-fab m3-tooltip-target" title="コメントへ移動" data-tooltip="コメントへ移動">
                    <span class="material-symbols-outlined">chat</span>
                    <?php if (get_comments_number() > 0) : ?>
                        <span class="m3-fab__badge"><?php echo get_comments_number(); ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            <button id="m3-back-to-top" class="m3-fab m3-tooltip-target" title="最上部へ戻る" data-tooltip="最上部へ戻る">
                <span class="material-symbols-outlined">arrow_upward</span>
            </button>
        </div>

    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
