<?php get_header(); ?>

<main id="primary" class="site-main m3-404-page">
    <div class="m3-404-bg">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/minecraft-lava.png'); ?>" alt="Background">
        <div class="m3-404-overlay"></div>
    </div>

    <div class="m3-404-content">
        <h1 class="m3-404-title-large">404</h1>
        <h2 class="m3-404-subtitle">お探しのページは見つかりませんでした</h2>
        
        <p class="m3-404-description">
            アクセスしようとしたページは削除されたか、URLが変更されている可能性があります。
        </p>
        
        <div class="m3-404-actions">
            <div class="m3-404-button-group">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="m3-mc-button">
                    ホームへ戻る
                </a>
            </div>
            
            <div class="m3-404-search-expressive">
                <p class="m3-mc-text">またはキーワードで検索してください</p>
                <div class="m3-404-search-form-wrapper">
                    <?php get_search_form(); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
