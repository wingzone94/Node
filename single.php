<?php get_header(); ?>

<main id="primary" class="site-main article-view">

    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class('m3-article'); ?>>
            
            <?php if (has_post_thumbnail()) : ?>
                <div class="m3-article__hero">
                    <?php the_post_thumbnail('full'); ?>
                    <div class="m3-article__hero-overlay"></div>
                    
                    <div class="m3-article__hero-labels">
                        <?php if (get_post_meta(get_the_ID(), '_node_is_ai_generated', true)) : ?>
                            <span class="m3-label m3-label--ai">
                                <span class="material-symbols-outlined">auto_awesome</span>
                                生成されたメディアを含む
                            </span>
                        <?php endif; ?>
                        <?php if (get_post_meta(get_the_ID(), '_node_is_sponsor', true)) : ?>
                            <span class="m3-label m3-label--sponsor">SPONSORED</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <header class="m3-article__header">
                <div class="m3-article__meta">
                    <time class="m3-article__date" datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo get_the_date(); ?>
                    </time>
                </div>
                
                <h1 class="m3-article__title"><?php the_title(); ?></h1>

                <div class="m3-article__tags">
                    <?php the_tags('', ''); ?>
                </div>

                <!-- ゲーム・アプリ情報エリア (タイトル下) -->
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

            <!-- Nexus Abstract (AI要約) -->
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
                
                <!-- 複数ページ機能 -->
                <?php wp_link_pages([
                    'before' => '<div class="m3-pagination">' . esc_html__( 'Pages:', 'node' ),
                    'after'  => '</div>',
                ]); ?>
            </div>

            <footer class="m3-article__footer">
                <!-- 記事下のカテゴリ表示 -->
                <div class="m3-article__categories">
                    <span class="m3-article__categories-label">CATEGORIES:</span>
                    <?php 
                    $categories = get_the_category();
                    if (!empty($categories)) :
                        foreach ($categories as $category) : ?>
                            <a href="<?php echo esc_url(get_category_link($category->term_id)); ?>" class="m3-chip m3-chip--category">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        <?php endforeach;
                    endif; ?>
                </div>

                <!-- ゲーム・アプリ情報エリア (最下部) -->
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