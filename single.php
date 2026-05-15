<?php get_header(); ?>

<main id="primary" class="site-main article-view m3-reveal m3-page-enter">
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
                $current_page = (get_query_var('page')) ? get_query_var('page') : 1;
                if ( ! empty( $ai_summary ) && $current_page === 1 ) {
                    get_template_part( 'template-parts/ai-summary', null, $ai_args );
                }

                // プラグイン等からの拡張表示（Node Library等）
                luminous_after_article_header(get_the_ID());
            ?>
            <div class="m3-article__body m3-reveal">
                <?php 
                the_content(); 
                ?>
                
                <div class="m3-article__body-footer-clear"></div>

                <div class="m3-article__pagination-container">
                    <div class="m3-article__pagination-row">
                        <?php 
                        global $numpages, $page;
                        if ( $numpages > 1 ) : ?>
                            <nav class="m3-pagination m3-pagination--split">
                                <span class="m3-pagination__label">
                                    <span class="material-symbols-outlined m3-pagination__label-icon">auto_stories</span>
                                    PAGES
                                </span>
                                <div class="m3-pagination__controls">
                                    <div class="m3-pagination__select-wrapper">
                                        <select id="m3-page-selector" class="m3-pagination__select" aria-label="ページを選択">
                                            <?php for ( $i = 1; $i <= $numpages; $i++ ) : ?>
                                                <?php 
                                                $link = _wp_link_page( $i );
                                                preg_match( '/href="([^"]+)"/', $link, $match );
                                                $url = $match[1] ?? '';
                                                ?>
                                                <option value="<?php echo esc_url( $url ); ?>" <?php selected( $i, $page ); ?>>
                                                    Page <?php echo $i; ?> / <?php echo $numpages; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <span class="material-symbols-outlined m3-select-chevron">expand_more</span>
                                    </div>
                                    <div class="m3-pagination__numbers">
                                        <?php
                                        // First Page Button
                                        if ( $page > 1 ) {
                                            $first_link = _wp_link_page( 1 );
                                            preg_match( '/href="([^"]+)"/', $first_link, $first_match );
                                            $first_url = $first_match[1] ?? '';
                                            echo '<a href="' . esc_url( $first_url ) . '" class="m3-pagination__number" aria-label="最初のページへ"><span class="material-symbols-outlined">first_page</span></a>';
                                        }

                                        wp_link_pages( array(
                                            'before'      => '',
                                            'after'       => '',
                                            'link_before' => '<span class="m3-pagination__number">',
                                            'link_after'  => '</span>',
                                            'separator'   => '',
                                        ) );

                                        // Last Page Button
                                        if ( $page < $numpages ) {
                                            $last_link = _wp_link_page( $numpages );
                                            preg_match( '/href="([^"]+)"/', $last_link, $last_match );
                                            $last_url = $last_match[1] ?? '';
                                            echo '<a href="' . esc_url( $last_url ) . '" class="m3-pagination__number" aria-label="最後のページへ"><span class="material-symbols-outlined">last_page</span></a>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </nav>
                        <?php endif; ?>
                    </div>
                    <a href="#" id="m3-article-top-anchor" class="m3-pagination-top-btn" aria-label="最上部へ戻る">
                        <span class="material-symbols-outlined">arrow_upward</span>
                        <span class="m3-pagination-top-btn__text">TOP</span>
                    </a>
                </div>
            </div>


            <?php get_template_part('template-parts/single/footer'); ?>
            
        </article>

        <?php get_template_part('template-parts/single/related'); ?>

        <section id="comments-section" class="m3-comments-section m3-reveal">
            <?php if (comments_open() || get_comments_number()) :
                comments_template();
            endif; ?>
        </section>

        <?php get_template_part('template-parts/single/toc'); ?>

<?php endwhile; ?>
</main>

<?php get_footer(); ?>
