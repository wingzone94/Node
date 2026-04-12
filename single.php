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
                    <time class="m3-article__date" datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo get_the_date(); ?>
                    </time>
                </div>
                
                <h1 class="m3-article__title"><?php the_title(); ?></h1>

                <div class="m3-card__meta-info" style="justify-content: center; margin-bottom: 2rem;">
                    <?php if (get_the_category()) : ?>
                        <span class="m3-label m3-label--category">
                            <?php echo esc_html(get_the_category()[0]->name); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="m3-article__tags">
                    <?php the_tags('', ''); ?>
                </div>

                <?php $game_info = get_post_meta(get_the_ID(), '_node_game_info', true); ?>
                <?php if (!empty($game_info['title'])) : ?>
                    <div class="m3-article__game-info-brief">
                        <details class="m3-details-chip">
                            <summary class="m3-button m3-button--tonal">
                                <span class="material-symbols-outlined">info</span>
                                <?php echo esc_html($game_info['title']); ?> 情報
                            </summary>
                            <div class="m3-details-content">
                                <p><?php echo esc_html($game_info['summary']); ?></p>
                                <?php if (!empty($game_info['links'])) : ?>
                                    <div class="m3-article__store-links">
                                        <?php foreach ($game_info['links'] as $link) : ?>
                                            <a href="<?php echo esc_url($link['url']); ?>" class="m3-button m3-button--text" target="_blank">
                                                <span class="material-symbols-outlined">open_in_new</span>
                                                <?php echo esc_html($link['platform']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>
            </header>

            <?php $ai_summary = get_post_meta(get_the_ID(), '_node_ai_summary', true); ?>
            <?php if (!empty($ai_summary)) : ?>
                <aside class="m3-nexus-abstract">
                    <div class="m3-nexus-abstract__badge">
                        <span class="material-symbols-outlined">psychology</span>
                        NEXUS ABSTRACT
                    </div>
                    <div class="m3-nexus-abstract__content">
                        <?php echo nl2br(esc_html($ai_summary)); ?>
                    </div>
                </aside>
            <?php endif; ?>

            <div class="m3-article__body entry-content">
                <?php the_content(); ?>
                
                <?php wp_link_pages([
                    'before' => '<div class="m3-pagination">' . esc_html__( 'Pages:', 'node' ),
                    'after'  => '</div>',
                ]); ?>
            </div>

            <!-- ソーシャルシェアボタン -->
            <div class="m3-share-section">
                <h3 class="m3-share-title">SHARE</h3>
                <div class="m3-share-buttons">
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>" class="m3-share-btn m3-share-btn--x" target="_blank">
                        <i class="fa-brands fa-x-twitter"></i> X
                    </a>
                    <a href="https://b.hatena.ne.jp/add?mode=confirm&url=<?php echo urlencode(get_permalink()); ?>" class="m3-share-btn m3-share-btn--hatebu" target="_blank">
                        B!
                    </a>
                    <a href="https://www.threads.net/intent/post?text=<?php echo urlencode(get_permalink()); ?>" class="m3-share-btn m3-share-btn--threads" target="_blank">
                        <i class="fa-brands fa-threads"></i> Threads
                    </a>
                    <button class="m3-share-btn m3-share-btn--copy">
                        <i class="fa-solid fa-link"></i> Copy
                    </button>
                    <button class="m3-share-btn m3-share-btn--native" onclick="if(navigator.share){navigator.share({url:'<?php echo get_permalink(); ?>'})}">
                        <i class="fa-solid fa-share-nodes"></i> Share
                    </button>
                </div>
            </div>

            <footer class="m3-article__footer">
                <?php if (!empty($game_info['title'])) : ?>
                    <section class="m3-game-card">
                        <div class="m3-game-card__header">
                            <span class="material-symbols-outlined">videogame_asset</span>
                            <h3>GAME INFO</h3>
                        </div>
                        <div class="m3-game-card__body">
                            <h4><?php echo esc_html($game_info['title']); ?></h4>
                            <p><?php echo esc_html($game_info['summary']); ?></p>
                            <?php if (!empty($game_info['links'])) : ?>
                                <div class="m3-game-card__actions">
                                    <?php foreach ($game_info['links'] as $link) : ?>
                                        <a href="<?php echo esc_url($link['url']); ?>" class="m3-button m3-button--filled" target="_blank">
                                            <?php echo esc_html($link['platform']); ?>でチェック
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </footer>

        </article>

        <?php if (comments_open() || get_comments_number()) :
            comments_template();
        endif; ?>

    <?php endwhile; ?>

</main>

<?php get_footer(); ?>