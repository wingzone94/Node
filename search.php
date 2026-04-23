<?php get_header(); ?>

<main id="primary" class="site-main">
    <header class="m3-archive-header" style="text-align: center; padding: var(--m3-spacing-xxxl) var(--m3-spacing-m) var(--m3-spacing-xl);">
        <h1 class="m3-archive-title" style="display: flex; align-items: center; justify-content: center; gap: var(--m3-spacing-m); flex-wrap: wrap; font-size: 2rem; font-weight: 900; color: var(--md-sys-color-on-surface);">
            「<?php echo esc_html(get_search_query()); ?>」の検索結果
            <span class="m3-badge">全 <?php echo $wp_query->found_posts; ?> 件</span>
        </h1>
    </header>

    <div class="m3-post-grid">
        <?php if (have_posts()) : ?>
            <div class="m3-post-grid__container">
                <?php while (have_posts()) : the_post(); ?>
                    <?php get_template_part('card'); ?>
                <?php endwhile; ?>
            </div>
            <div class="m3-navigation">
                <?php
                echo paginate_links([
                    'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
                    'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
                ]);
                ?>
            </div>
        <?php else : ?>
            <div class="m3-empty-state" style="text-align: center; padding: var(--m3-spacing-xxxl) var(--m3-spacing-m);">
                <span class="material-symbols-outlined" style="font-size: 64px; color: var(--md-sys-color-outline-variant); margin-bottom: var(--m3-spacing-m);">search_off</span>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--md-sys-color-on-surface); margin-bottom: var(--m3-spacing-s);">見つかりませんでした</h2>
                <p style="color: var(--md-sys-color-outline); margin-bottom: var(--m3-spacing-xl);">別のキーワードでお試しください。</p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="m3-button">
                    <span class="material-symbols-outlined">home</span>
                    ホームへ戻る
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
