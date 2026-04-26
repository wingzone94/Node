<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#FF9900">
    <link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/Luminouscore.svg">
    <link rel="manifest" href="<?php echo get_template_directory_uri(); ?>/manifest.json">
    <link rel="mask-icon" href="<?php echo get_template_directory_uri(); ?>/Luminouscore.svg" color="#FF9900">
    <link rel="icon" type="image/svg+xml" href="<?php echo get_template_directory_uri(); ?>/Luminouscore.svg">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
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

<header id="masthead" class="m3-header">
    <div class="m3-header__inner">
        <div class="m3-header__left">
            <div class="site-branding">
                <?php if (has_custom_logo()) : the_custom_logo(); else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home" class="m3-header__logo-link">
                        <span class="m3-logo-text">Luminous Core</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="m3-header__actions">
            <form role="search" method="get" class="m3-search-bar" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="search" class="m3-search-bar__input" placeholder="Search..." value="<?php echo get_search_query(); ?>" name="s">
                <button type="submit" class="m3-icon-button" id="search-toggle">
                    <span class="material-symbols-outlined">search</span>
                </button>
            </form>

            <a href="<?php bloginfo('rss2_url'); ?>" class="m3-icon-button m3-tooltip-target" data-tooltip="RSSフィード">
                <span class="material-symbols-outlined">rss_feed</span>
            </a>
            
            <div id="m3-theme-controls">
                <button id="theme-toggle" class="m3-icon-button m3-tooltip-target" data-tooltip="テーマ切り替え">
                    <span class="material-symbols-outlined" id="theme-toggle-dark-icon">dark_mode</span>
                    <span class="material-symbols-outlined hidden" id="theme-toggle-light-icon">light_mode</span>
                </button>
                <div id="theme-popover" class="m3-popover">
                    <div class="m3-popover__content">
                        <div class="m3-popover__item">
                            <span class="material-symbols-outlined">sync</span>
                            <span>システム同期</span>
                            <label class="m3-switch">
                                <input type="checkbox" id="theme-sync-toggle" checked>
                                <span class="m3-switch__slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
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