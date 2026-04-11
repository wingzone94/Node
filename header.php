<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&family=Noto+Sans+JP:wght@400;500;700;900&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="m3-page-container">

<header id="masthead" class="m3-header">
    <div class="m3-header__start">
        <button class="m3-icon-button m3-header__menu" aria-label="メニュー">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <h1 class="m3-header__logo">
            <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                <span class="m3-logo-text"><?php bloginfo('name'); ?></span>
            </a>
        </h1>
    </div>

    <div class="m3-header__end">
        <div class="m3-header__actions">
            <!-- 検索アイコン -->
            <button id="search-toggle" class="m3-icon-button" title="検索">
                <span class="material-symbols-outlined">search</span>
            </button>
            <!-- 検索窓 (非表示からトグル) -->
            <form role="search" method="get" class="m3-search-bar" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="search" class="m3-search-bar__input" placeholder="検索..." value="<?php echo get_search_query(); ?>" name="s" />
            </form>

            <a href="<?php bloginfo('rss2_url'); ?>" class="m3-icon-button" title="RSSフィード">
                <span class="material-symbols-outlined">rss_feed</span>
            </a>
            <button id="theme-toggle" class="m3-icon-button" title="テーマ切り替え">
                <span id="theme-toggle-dark-icon" class="material-symbols-outlined">dark_mode</span>
                <span id="theme-toggle-light-icon" class="material-symbols-outlined">light_mode</span>
            </button>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo admin_url(); ?>" class="m3-icon-button m3-icon-button--filled-tonal" title="管理画面">
                    <span class="material-symbols-outlined">account_circle</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
<nav class="m3-header__nav">
    <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false]); ?>
</nav>