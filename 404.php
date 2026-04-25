<?php get_header(); ?>

<main id="primary" class="site-main m3-404-page">
    <div class="m3-empty-state">
        <div class="m3-404-visual">
            <span class="m3-404-number">404</span>
            <span class="material-symbols-outlined m3-404-icon">explore_off</span>
        </div>
        <h2 class="m3-section-title justify-center">お探しのページは見つかりませんでした</h2>
        <p class="m3-empty-description">URLが間違っているか、ページが移動・削除された可能性があります。</p>
        
        <div class="m3-404-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="m3-button m3-button--filled">
                <span class="material-symbols-outlined">home</span>
                ホームへ戻る
            </a>
            <div class="m3-404-search">
                <p>またはキーワードで検索してみてください</p>
                <?php get_search_form(); ?>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
