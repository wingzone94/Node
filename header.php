<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="<?php echo get_template_directory_uri(); ?>/manifest.json">
    <link rel="mask-icon" href="<?php echo get_template_directory_uri(); ?>/Luminouscore.svg" color="#FF9900">
    <link rel="icon" type="image/svg+xml" href="<?php echo get_template_directory_uri(); ?>/Luminouscore.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?php echo get_template_directory_uri(); ?>/sw.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.log('Service Worker registration failed: ', err));
        });
    }
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="m3-page-container">

<header id="masthead" class="m3-header">
    <div class="m3-header__inner">
        <h1 class="m3-header__logo">
            <a href="<?php echo esc_url(home_url('/')); ?>" rel="home" class="m3-tooltip-target" data-tooltip="トップページへ">
                <span class="m3-logo-text"><?php bloginfo('name'); ?></span>
            </a>
        </h1>

        <div class="m3-header__end">
            <div class="m3-header__actions">
                <form role="search" method="get" class="m3-search-bar" action="<?php echo esc_url(home_url('/')); ?>">
                    <button type="button" id="search-toggle" class="m3-icon-button m3-tooltip-target" data-tooltip="サイト内検索">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                    <input type="search" class="m3-search-bar__input" placeholder="検索..." value="<?php echo get_search_query(); ?>" name="s" />
                </form>

                <a href="<?php bloginfo('rss2_url'); ?>" class="m3-icon-button m3-tooltip-target" data-tooltip="RSSフィード">
                    <span class="material-symbols-outlined">rss_feed</span>
                </a>
                
                <div class="m3-theme-controls" id="m3-theme-controls">
                    <button id="theme-toggle" class="m3-icon-button m3-tooltip-target" data-tooltip="外観を切り替え">
                        <span id="theme-toggle-dark-icon" class="material-symbols-outlined">dark_mode</span>
                        <span id="theme-toggle-light-icon" class="material-symbols-outlined">light_mode</span>
                    </button>
                    
                    <div class="m3-theme-popover" id="theme-popover">
                        <div class="m3-theme-popover__content">
                            <div class="m3-theme-popover__row">
                                <span class="m3-theme-popover__label">システム設定と同期</span>
                                <label class="m3-switch">
                                    <input type="checkbox" id="theme-sync-toggle">
                                    <span class="m3-switch__slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="m3-theme-popover__arrow"></div>
                    </div>
                </div>

                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo admin_url(); ?>" class="m3-icon-button m3-icon-button--filled-tonal m3-tooltip-target" data-tooltip="管理画面">
                        <span class="material-symbols-outlined">account_circle</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<nav class="m3-header__nav">
    <?php 
    if (has_nav_menu('primary')) {
        wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false]); 
    }
    ?>
</nav>