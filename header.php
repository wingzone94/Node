<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" id="m3-viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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

<div class="m3-page-container">

<!-- 1. Header Section -->
<header id="masthead" class="m3-header">
    <div class="m3-header__inner">
        <!-- Header Left: Branding Only -->
        <div class="m3-header__left">
            <div class="site-branding">
                <?php if (has_custom_logo()) : the_custom_logo(); else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home" class="m3-header__logo-link">
                        <span class="m3-logo-text">Luminous Core</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Header Right: Action Group -->
        <div class="m3-header__actions">
            <!-- Search Control -->
            <div class="m3-search-container">
                <form role="search" method="get" class="m3-search-bar" id="m3-main-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                    <div class="m3-search-input-wrapper">
                        <button type="button" class="m3-icon-button m3-search-mobile-close" id="m3-search-mobile-close" aria-label="検索を閉じる">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </button>
                        <input type="search" class="m3-search-bar__input" id="m3-search-input" placeholder="検索..." value="<?php echo get_search_query(); ?>" name="s" autocomplete="off">
                        <div class="m3-search-actions-inline">
                            <button type="button" class="m3-icon-button m3-search-clear" id="m3-search-clear" aria-label="クリア" style="display:none;">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                            <button type="button" class="m3-icon-button m3-search-advanced-trigger" id="m3-advanced-search-trigger" aria-label="詳細検索">
                                <span class="material-symbols-outlined">tune</span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="m3-icon-button m3-search-bar__toggle m3-tooltip-target" id="search-toggle" aria-label="検索" data-tooltip="検索">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                </form>
            </div>

            <!-- RSS -->
            <a href="<?php bloginfo('rss2_url'); ?>" class="m3-icon-button m3-tooltip-target m3-rss-button" id="m3-rss-trigger" aria-label="RSS" data-tooltip="RSSフィード">
                <span class="material-symbols-outlined">rss_feed</span>
            </a>
            
            <!-- Theme -->
            <div id="m3-theme-controls">
                <button id="theme-toggle" class="m3-icon-button m3-tooltip-target" aria-label="テーマ" data-tooltip="テーマ切り替え">
                    <span class="material-symbols-outlined" id="theme-toggle-icon">brightness_6</span>
                </button>
            </div>

            <!-- View (Tablet Only) -->
            <?php 
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $is_tablet = (strpos($ua, 'iPad') !== false) || (strpos($ua, 'Android') !== false && strpos($ua, 'Mobile') === false) || (strpos($ua, 'Tablet') !== false);
            if ($is_tablet) : 
            ?>
            <button class="m3-icon-button m3-tooltip-target" id="m3-view-toggle" aria-label="表示モード" data-tooltip="表示モード切替">
                <span class="material-symbols-outlined">devices</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reading Progress Bar -->
    <div id="m3-reading-progress" class="m3-header__progress-container">
        <div class="m3-header__progress-bar"></div>
    </div>
</header>

<!-- 3. Portal Components (Fixed/Overlay Elements) -->

<!-- Advanced Search Modal (Material 3 Expressive) -->
<div id="m3-advanced-search-modal" class="m3-modal m3-modal--wide">
    <div class="m3-modal__content m3-advanced-search-card">
        
        <div class="m3-modal__header">
            <div class="m3-modal__title-group">
                <span class="material-symbols-outlined">filter_alt</span>
                <h2 class="m3-modal__title">詳細検索</h2>
            </div>
            <button type="button" class="m3-icon-button m3-modal__close" id="m3-advanced-search-close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="m3-modal__tabs" id="m3-search-tabs">
            <div class="m3-modal__tab-indicator"></div>
            <button type="button" class="m3-modal__tab is-active" data-page="1">
                <span class="material-symbols-outlined">filter_alt</span>
                <span>絞り込み</span>
            </button>
            <button type="button" class="m3-modal__tab" data-page="2">
                <span class="material-symbols-outlined">schedule</span>
                <span>ボリューム</span>
            </button>
            <button type="button" class="m3-modal__tab" data-page="3">
                <span class="material-symbols-outlined">devices</span>
                <span>プラットフォーム</span>
            </button>
        </div>
        
        <div class="m3-modal__body">
            <div class="m3-modal__pages-container m3-modal__pages-container--3">
                <!-- Page 1: Basic Filters -->
                <div class="m3-modal__page is-active" data-page="1">
                    <div class="m3-advanced-search-grid-layout">
                        <div class="m3-advanced-search-column">
                            <div class="m3-search-section">
                                <label class="m3-search-section-label"><span class="material-symbols-outlined">category</span> カテゴリ</label>
                                <div class="m3-select-wrapper">
                                    <select name="m3_cat" class="m3-select">
                                        <option value="">すべてのカテゴリ</option>
                                        <?php
                                        $categories = get_categories(['hide_empty' => true]);
                                        foreach ($categories as $cat) : ?>
                                            <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="material-symbols-outlined m3-select-icon">expand_more</span>
                                </div>
                            </div>
                            <div class="m3-search-section">
                                <label class="m3-search-section-label"><span class="material-symbols-outlined">sell</span> タグ</label>
                                <div class="m3-textfield-wrapper">
                                    <input type="text" name="m3_tag" id="m3-tag-input" class="m3-text-input" placeholder="タグ名を入力..." autocomplete="off">
                                    <div id="m3-tag-suggestions" class="m3-suggestions-list"></div>
                                </div>
                            </div>
                            <!-- Mobile Exclusive: Sort Order -->
                            <div class="m3-search-section m3-desktop-hidden">
                                <label class="m3-search-section-label"><span class="material-symbols-outlined">sort</span> 並び順</label>
                                <div class="m3-radio-group">
                                    <label class="m3-radio-item"><input type="radio" name="m3_sort" value="date" checked><span class="m3-radio-label">新着</span></label>
                                    <label class="m3-radio-item"><input type="radio" name="m3_sort" value="views"><span class="m3-radio-label">人気</span></label>
                                    <label class="m3-radio-item"><input type="radio" name="m3_sort" value="comments"><span class="m3-radio-label">コメント</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="m3-advanced-search-column m3-mobile-hidden">
                            <div class="m3-search-section">
                                <label class="m3-search-section-label"><span class="material-symbols-outlined">calendar_month</span> 期間</label>
                                <div class="m3-date-picker-grid">
                                    <input type="date" name="m3_start_date" class="m3-date-input">
                                    <span class="m3-date-range-sep">~</span>
                                    <input type="date" name="m3_end_date" class="m3-date-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page 2: Volume & Media -->
                <div class="m3-modal__page" data-page="2">
                    <div class="m3-advanced-search-grid-layout">
                        <div class="m3-advanced-search-column">
                                <div class="m3-search-section">
                                    <!-- Range Slider -->
                                    <label class="m3-search-section-label"><span class="material-symbols-outlined">schedule</span> 読了目安・ボリューム</label>
                                    <div class="m3-radio-group">
                                        <label class="m3-radio-item"><input type="radio" name="m3_reading_time" value="all" checked><span class="m3-radio-label">すべて</span></label>
                                        <label class="m3-radio-item"><input type="radio" name="m3_reading_time" value="short"><span class="m3-radio-label">~5分</span></label>
                                        <label class="m3-radio-item"><input type="radio" name="m3_reading_time" value="medium"><span class="m3-radio-label">~10分</span></label>
                                        <label class="m3-radio-item"><input type="radio" name="m3_reading_time" value="long"><span class="m3-radio-label">15分~</span></label>
                                    </div>
                                    
                                    <div class="m3-slider-container">
                                        <div class="m3-search-section-label m3-search-section-label--sub"><span class="material-symbols-outlined">straighten</span> 文字数範囲</div>
                                        <div class="m3-range-slider" id="m3-word-count-slider">
                                            <div class="m3-range-slider__track"></div>
                                            <div class="m3-range-slider__range" id="m3-slider-range"></div>
                                            <div class="m3-range-slider__handle m3-range-slider__handle--min" id="m3-slider-handle-min">
                                                <div class="m3-range-slider__value">0</div>
                                            </div>
                                            <div class="m3-range-slider__handle m3-range-slider__handle--max" id="m3-slider-handle-max">
                                                <div class="m3-range-slider__value">10000</div>
                                            </div>
                                        </div>
                                        <div class="m3-char-input-grid">
                                            <div class="m3-char-input-field">
                                                <span class="m3-char-input-label">最小</span>
                                                <input type="number" name="m3_min" id="m3-min-chars" value="0" class="m3-text-input m3-char-input">
                                            </div>
                                            <div class="m3-char-input-field">
                                                <span class="m3-char-input-label">最大</span>
                                                <input type="number" name="m3_max" id="m3-max-chars" value="10000" class="m3-text-input m3-char-input">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                        <div class="m3-advanced-search-column">
                            <div class="m3-search-section">
                                <label class="m3-search-section-label"><span class="material-symbols-outlined">media_output</span> メディア</label>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--image"><input type="checkbox" name="m3_media_type[]" value="image"><span>画像</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--video"><input type="checkbox" name="m3_media_type[]" value="video"><span>動画</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--youtube"><input type="checkbox" name="m3_media_type[]" value="youtube"><span>YouTube</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--ai"><input type="checkbox" name="m3_media_type[]" value="ai"><span>AI生成</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page 3: Platforms -->
                <div class="m3-modal__page" data-page="3">
                    <div class="m3-platform-layout">
                        <!-- Mobile & Web -->
                        <div class="m3-platform-side">
                            <div class="m3-platform-group">
                                <label class="m3-platform-group-label"><span class="material-symbols-outlined">smartphone</span> Mobile & Web</label>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--ios"><input type="checkbox" name="m3_platform[]" value="iOS"><span>iOS</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--android"><input type="checkbox" name="m3_platform[]" value="Android"><span>Android</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--webapp"><input type="checkbox" name="m3_platform[]" value="Web"><span>Web App</span></label>
                                </div>
                            </div>
                            <div class="m3-platform-divider"></div>
                            <div class="m3-platform-group">
                                <label class="m3-platform-group-label"><span class="material-symbols-outlined">desktop_windows</span> PC Platforms</label>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--windows"><input type="checkbox" name="m3_platform[]" value="Windows"><span>Windows</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--mac"><input type="checkbox" name="m3_platform[]" value="Mac"><span>Mac</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--linux"><input type="checkbox" name="m3_platform[]" value="Linux"><span>Linux</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--steam"><input type="checkbox" name="m3_platform[]" value="Steam"><span>Steam</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--epic"><input type="checkbox" name="m3_platform[]" value="Epic"><span>Epic</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--geforce"><input type="checkbox" name="m3_platform[]" value="GeForce"><span>GFN</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="m3-platform-divider m3-platform-divider--vertical"></div>

                        <!-- Consoles -->
                        <div class="m3-platform-side">
                            <div class="m3-platform-group m3-platform-subgroup">
                                <label class="m3-platform-group-label"><span class="material-symbols-outlined">sports_esports</span> Consoles</label>
                                <span class="m3-platform-subgroup-title">Nintendo</span>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--nintendo"><input type="checkbox" name="m3_platform[]" value="Switch"><span>Switch</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--nintendo"><input type="checkbox" name="m3_platform[]" value="3DS"><span>3DS</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--nintendo"><input type="checkbox" name="m3_platform[]" value="WiiU"><span>Wii U</span></label>
                                </div>
                                <span class="m3-platform-subgroup-title">PlayStation</span>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--sony"><input type="checkbox" name="m3_platform[]" value="PS5"><span>PS5</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--sony"><input type="checkbox" name="m3_platform[]" value="PS4"><span>PS4</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--sony"><input type="checkbox" name="m3_platform[]" value="PS3"><span>PS3</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--sony"><input type="checkbox" name="m3_platform[]" value="PSVita"><span>PS Vita</span></label>
                                </div>
                                <span class="m3-platform-subgroup-title">Xbox</span>
                                <div class="m3-platform-list">
                                    <label class="m3-platform-chip m3-platform-chip--xbox"><input type="checkbox" name="m3_platform[]" value="XboxX"><span>Xbox X|S</span></label>
                                    <label class="m3-platform-chip m3-platform-chip--xbox"><input type="checkbox" name="m3_platform[]" value="XboxOne"><span>Xbox One</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="m3-modal__footer">
            <div class="m3-search-hits-display">
                <span class="m3-search-hits-text">
                    <strong id="m3-search-hit-count"><?php echo number_format_i18n(wp_count_posts()->publish); ?></strong> 件の記事
                </span>
            </div>
            <div class="m3-modal__footer-options">
                <label class="m3-checkbox-label">
                    <input type="checkbox" id="m3-save-search-settings">
                    <span class="m3-checkbox-custom"></span>
                    <span class="m3-checkbox-text">検索条件を保存する</span>
                </label>
            </div>
            <div class="m3-modal__footer-actions">
                <button type="button" class="m3-button m3-button--text" id="m3-advanced-search-reset">リセット</button>
                <button type="button" class="m3-button m3-button--filled" id="m3-advanced-search-apply">検索を実行</button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="m3-search-loading" class="m3-loading-overlay">
            <div class="m3-loading-spinner"></div>
        </div>
    </div>
</div>


<!-- Bottom Navigation (Handy Mode Optimized) -->
<nav class="m3-bottom-nav" id="m3-bottom-nav">
    <button class="m3-bottom-nav__item" id="m3-handy-toc-trigger" aria-label="目次">
        <span class="material-symbols-outlined">list_alt</span>
        <span class="m3-bottom-nav__label">目次</span>
    </button>
    <button class="m3-bottom-nav__item" id="m3-bottom-comments-trigger" aria-label="コメント">
        <span class="material-symbols-outlined">comment</span>
        <span class="m3-bottom-nav__label">コメント</span>
    </button>
    <button class="m3-bottom-nav__item" id="m3-back-to-top-handy" aria-label="トップへ">
        <span class="material-symbols-outlined">arrow_upward</span>
        <span class="m3-bottom-nav__label">トップ</span>
    </button>
</nav>

<!-- Snackbar (Notifications) -->
<div id="m3-snackbar" class="m3-snackbar" aria-live="polite">
    <div class="m3-snackbar__content">
        <span class="m3-snackbar__text">物理キーボードを検出しました。PCビューに切り替えますか？</span>
        <div class="m3-snackbar__actions">
            <button class="m3-button m3-button--text" id="m3-snackbar-action">切り替える</button>
            <button class="m3-icon-button" id="m3-snackbar-close" aria-label="閉じる">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
    </div>
</div>