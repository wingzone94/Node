<?php get_header(); ?>

<main id="primary" class="site-main article-view">
    <?php 
    // SEO: パンくずリスト
    node_the_breadcrumbs();
    
    while (have_posts()) : the_post(); 
    ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
<?php get_template_part('template-parts/single/hero'); ?>
            
            

            <?php
                // AI 要約を取得
                $ai_summary    = get_post_meta( get_the_ID(), '_node_ai_summary', true );
                $tone_color    = get_post_meta( get_the_ID(), '_node_ai_tone_color', true );
                $keywords      = get_post_meta( get_the_ID(), '_node_ai_keywords', true );
                // コンポーネントへ渡す引数配列
                $ai_args = array(
                    'summary'    => $ai_summary,
                    'mode'       => 'single',
                    'tone_color' => $tone_color,
                    'keywords'   => is_array( $keywords ) ? $keywords : array(),
                );
                if ( ! empty( $ai_summary ) ) {
                    get_template_part( 'template-parts/ai-summary', null, $ai_args );
                }
            ?>
            <div class="m3-article__body">
                <?php the_content(); ?>
            </div>


            <?php get_template_part('template-parts/single/footer'); ?>
            
        </article>

        <?php get_template_part('template-parts/single/related'); ?>

        <section id="comments" class="m3-comments-section">
            <?php if (comments_open() || get_comments_number()) :
                comments_template();
            endif; ?>
        </section>

        <?php get_template_part('template-parts/single/toc'); ?>

<?php endwhile; ?>
</main>

<?php get_footer(); ?>
