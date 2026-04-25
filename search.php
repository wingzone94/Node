<?php get_header(); ?>

<main id="primary" class="site-main">
    <header class="m3-archive-header">
        <h1 class="m3-archive-title">
            「<?php echo esc_html(get_search_query()); ?>」の検索結果
            <span class="m3-badge">全 <?php echo $wp_query->found_posts; ?> 件</span>
        </h1>
    </header>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container">
                <?php
                while (have_posts()) : the_post();
                    $card_class = ($wp_query->current_post < 4 && !is_paged()) ? 'card-featured' : 'card-standard';
                    get_template_part('card', null, ['card_class' => $card_class]);
                endwhile;
                ?>
            </div>
            <div class="m3-navigation">
                <?php
                the_posts_pagination([
                    'mid_size'  => 2,
                    'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
                    'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
                ]);
                ?>
            </div>
        <?php else : ?>
            <div class="m3-empty-state">
                <span class="material-symbols-outlined m3-empty-icon">search_off</span>
                <h2 class="m3-empty-title">見つかりませんでした</h2>
                <p class="m3-empty-description">別のキーワードでお試しください。</p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="m3-button m3-button--filled">
                    <span class="material-symbols-outlined">home</span>
                    ホームへ戻る
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
