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
            <div class="m3-search-container">
                <form role="search" method="get" class="m3-search-bar" id="m3-main-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                    <div class="m3-search-input-wrapper">
                        <input type="search" class="m3-search-bar__input" id="m3-search-input" placeholder="検索..." value="<?php echo get_search_query(); ?>" name="s" autocomplete="off">
                        
                        <div class="m3-search-actions-inline">
                            <button type="button" class="m3-icon-button m3-search-clear" id="m3-search-clear" aria-label="検索ワードをクリア" style="display:none;">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                            <button type="button" class="m3-icon-button m3-search-advanced-trigger" id="m3-advanced-search-trigger" aria-label="詳細検索設定">
                                <span class="material-symbols-outlined">tune</span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="m3-icon-button" id="search-toggle">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                </form>
            </div>

            <a href="<?php bloginfo('rss2_url'); ?>" class="m3-icon-button m3-tooltip-target" id="m3-rss-trigger" data-tooltip="RSSフィード">
                <span class="material-symbols-outlined">rss_feed</span>
            </a>
            
            <style>
                #m3-rss-trigger:hover,
                #m3-rss-trigger:active {
                    color: #F26522 !important; /* Official RSS Orange on Interaction */
                    background: rgba(242, 101, 34, 0.1) !important;
                }
            </style>
            
            <div id="m3-theme-controls">
                <button id="theme-toggle" class="m3-icon-button m3-tooltip-target" data-tooltip="テーマ切り替え">
                    <span class="material-symbols-outlined" id="theme-toggle-icon">brightness_6</span>
                </button>
            </div>
        </div>
    </div>

    <?php if (is_home() || is_front_page()) : ?>
    <div class="m3-header__clock" id="m3-header-clock">
        <div class="m3-header__greeting" id="m3-header-greeting"></div>
        <div class="m3-header__datetime">
            <span class="m3-header__date" id="m3-header-date"></span>
            <span class="m3-header__time" id="m3-header-time"></span>
        </div>
    </div>
    <?php endif; ?>



    <!-- 詳細検索モーダル (Material 3 Expressive - Multi-page Masterpiece) -->
    <div id="m3-advanced-search-modal" class="m3-modal m3-modal--wide">
        <div class="m3-modal__content m3-advanced-search-card">
            
            <!-- Modal Header -->
            <div class="m3-modal__header">
                <div class="m3-modal__title-group">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <h2 class="m3-modal__title">詳細検索</h2>
                </div>
                <button type="button" class="m3-icon-button m3-modal__close" id="m3-advanced-search-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Tab Navigation (Top) -->
            <div class="m3-modal__tabs">
                <button type="button" class="m3-modal__tab is-active" data-page="1">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <span>絞り込み</span>
                </button>
                <button type="button" class="m3-modal__tab" data-page="2">
                    <span class="material-symbols-outlined">schedule</span>
                    <span>ボリューム・メディア</span>
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
                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">category</span> カテゴリ指定
                                    </label>
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
                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">sell</span> タグ指定
                                    </label>
                                    <div class="m3-textfield-wrapper">
                                        <input type="text" name="m3_tag" class="m3-text-input" placeholder="タグ名を入力...">
                                    </div>
                                </div>
                            </div>
                            <div class="m3-advanced-search-column">
                                <div class="m3-search-section">
                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">calendar_month</span> 日付の範囲指定
                                    </label>
                                    <div class="m3-date-picker-grid">
                                        <div class="m3-date-input-field">
                                            <span class="m3-date-input-hint">開始日</span>
                                            <div class="m3-date-input-wrapper">
                                                <input type="date" name="m3_start_date" class="m3-date-input">
                                                <span class="material-symbols-outlined m3-date-icon">event</span>
                                            </div>
                                        </div>
                                        <div class="m3-date-input-field">
                                            <span class="m3-date-input-hint">終了日</span>
                                            <div class="m3-date-input-wrapper">
                                                <input type="date" name="m3_end_date" class="m3-date-input">
                                                <span class="material-symbols-outlined m3-date-icon">event</span>
                                            </div>
                                        </div>
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
                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">schedule</span> 読了時間・文字数の指定
                                    </label>
                                    
                                    <div class="m3-radio-group">
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_reading_time" value="all" checked class="m3-radio-input">
                                            <span class="m3-radio-label">すべて</span>
                                        </label>
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_reading_time" value="short" class="m3-radio-input">
                                            <span class="m3-radio-label">~5分</span>
                                        </label>
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_reading_time" value="medium" class="m3-radio-input">
                                            <span class="m3-radio-label">~10分</span>
                                        </label>
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_reading_time" value="long" class="m3-radio-input">
                                            <span class="m3-radio-label">15分~</span>
                                        </label>
                                    </div>

                                    <div class="m3-range-slider" id="m3-word-count-slider">
                                        <div class="m3-range-slider__track"></div>
                                        <div class="m3-range-slider__range"></div>
                                        <div class="m3-range-slider__handle m3-range-slider__handle--min" tabindex="0">
                                            <span class="m3-range-slider__value">0</span>
                                        </div>
                                        <div class="m3-range-slider__handle m3-range-slider__handle--max" tabindex="0">
                                            <span class="m3-range-slider__value">10000+</span>
                                        </div>
                                    </div>
                                    <div class="m3-char-input-grid">
                                        <div class="m3-char-input-field">
                                            <span class="m3-char-input-label">最少文字数</span>
                                            <input type="number" name="m3_min" id="m3-min-chars" value="0" min="0" max="10000" step="100" class="m3-text-input m3-char-input">
                                        </div>
                                        <div class="m3-char-input-field">
                                            <span class="m3-char-input-label">最大文字数</span>
                                            <input type="number" name="m3_max" id="m3-max-chars" value="10000" min="0" max="10000" step="100" class="m3-text-input m3-char-input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="m3-advanced-search-column">
                                <div class="m3-search-section">
                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">media_output</span> 含まれるメディア・埋め込み
                                    </label>
                                    <div class="m3-platform-list">
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="image">
                                            <span>画像</span>
                                        </label>
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="video">
                                            <span>動画</span>
                                        </label>
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="map">
                                            <span>地図</span>
                                        </label>
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="youtube">
                                            <span>YouTube</span>
                                        </label>
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="sns">
                                            <span>SNS埋め込み</span>
                                        </label>
                                        <label class="m3-platform-chip">
                                            <input type="checkbox" name="m3_media_type[]" value="download">
                                            <span>ファイルのダウンロード</span>
                                        </label>
                                    </div>

                                    <label class="m3-search-section-label">
                                        <span class="material-symbols-outlined">auto_awesome</span> 生成されたメディアの有無
                                    </label>
                                    <div class="m3-radio-group">
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_ai" value="all" checked class="m3-radio-input">
                                            <span class="m3-radio-label">すべて</span>
                                        </label>
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_ai" value="only" class="m3-radio-input">
                                            <span class="m3-radio-label">AI生成あり</span>
                                        </label>
                                        <label class="m3-radio-item">
                                            <input type="radio" name="m3_ai" value="exclude" class="m3-radio-input">
                                            <span class="m3-radio-label">AI生成なし</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Page 3: Platforms -->
                    <div class="m3-modal__page" data-page="3">
                        <div class="m3-platform-layout">
                            <!-- Left Side: Digital Devices -->
                            <div class="m3-platform-side">
                                <div class="m3-platform-group">
                                    <label class="m3-platform-group-label">
                                        <span class="material-symbols-outlined">smartphone</span>
                                        スマートフォン・タブレット
                                    </label>
                                    <div class="m3-platform-list">
                                        <?php 
                                        $mobile_apps = [
                                            'iOS' => 'm3-platform-chip--ios', 
                                            'Android' => 'm3-platform-chip--android'
                                        ];
                                        foreach($mobile_apps as $p => $cls): ?>
                                            <label class="m3-platform-chip <?php echo $cls; ?>">
                                                <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>">
                                                <span><?php echo $p; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="m3-platform-group" style="margin-top: 32px;">
                                    <label class="m3-platform-group-label">
                                        <span class="material-symbols-outlined">laptop_mac</span>
                                        PC
                                    </label>
                                    <div class="m3-platform-list">
                                        <?php 
                                        $pc_apps = [
                                            'Windows' => 'm3-platform-chip--windows', 
                                            'Mac' => 'm3-platform-chip--mac', 
                                            'Linux' => 'm3-platform-chip--linux',
                                            'Chromebook' => 'm3-platform-chip--chromebook'
                                        ];
                                        foreach($pc_apps as $p => $cls): ?>
                                            <label class="m3-platform-chip <?php echo $cls; ?>">
                                                <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>">
                                                <span><?php echo $p; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="m3-platform-divider"></div>

                            <!-- Right Side: Game Platforms -->
                            <div class="m3-platform-side">
                                <div class="m3-platform-group">
                                    <label class="m3-platform-group-label">
                                        <span class="material-symbols-outlined">sports_esports</span>
                                        ゲームプラットフォーム
                                    </label>
                                    <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 12px;">
                                        <div class="m3-platform-subgroup">
                                            <span class="m3-platform-subgroup-title">Nintendo</span>
                                            <div class="m3-platform-list">
                                                <?php 
                                                $nintendo = ['Switch 2', 'Switch', 'Wii U', 'Wii', 'GameCube', 'N64', 'SFC', 'FC', '3DS', 'DS', 'GBA', 'GB'];
                                                foreach($nintendo as $p): ?>
                                                    <label class="m3-platform-chip m3-platform-chip--nintendo">
                                                        <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>" data-is-game="true">
                                                        <span><?php echo $p; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="m3-platform-subgroup">
                                            <span class="m3-platform-subgroup-title">PlayStation</span>
                                            <div class="m3-platform-list">
                                                <?php 
                                                $sony = ['PS5', 'PS4', 'PS3', 'PS2', 'PS1', 'PS Vita', 'PSP'];
                                                foreach($sony as $p): ?>
                                                    <label class="m3-platform-chip m3-platform-chip--sony">
                                                        <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>" data-is-game="true">
                                                        <span><?php echo $p; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="m3-platform-subgroup">
                                            <span class="m3-platform-subgroup-title">Xbox</span>
                                            <div class="m3-platform-list">
                                                <?php 
                                                $ms = ['Xbox Series X/S', 'Xbox One', 'Xbox 360', 'Xbox'];
                                                foreach($ms as $p): ?>
                                                    <label class="m3-platform-chip m3-platform-chip--xbox">
                                                        <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>" data-is-game="true">
                                                        <span><?php echo $p; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="m3-platform-subgroup">
                                            <span class="m3-platform-subgroup-title">PC Game Stores & Others</span>
                                            <div class="m3-platform-list">
                                                <?php 
                                                $other_platforms = [
                                                    'Steam' => 'm3-platform-chip--steam',
                                                    'Epic Games Store' => 'm3-platform-chip--epic',
                                                    'Geforce NOW' => 'm3-platform-chip--geforce',
                                                    'その他' => 'm3-platform-chip--others'
                                                ];
                                                foreach($other_platforms as $p => $cls): ?>
                                                    <label class="m3-platform-chip <?php echo $cls; ?>">
                                                        <input type="checkbox" name="m3_platform[]" value="<?php echo $p; ?>" data-is-game="true">
                                                        <span><?php echo $p; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="m3-modal__footer">
                <div class="m3-search-hits-display">
                    <span class="material-symbols-outlined m3-search-hits-icon">description</span>
                    <span class="m3-search-hits-text">
                        <strong id="m3-search-hit-count"><?php echo number_format_i18n(wp_count_posts()->publish); ?></strong> 件の記事
                    </span>
                </div>
                <div class="m3-modal__footer-actions">
                    <button type="button" class="m3-button m3-button--text" id="m3-advanced-search-reset">
                        <span class="material-symbols-outlined">delete</span> リセット
                    </button>
                    <button type="button" class="m3-button m3-button--filled" id="m3-advanced-search-apply">
                        <span class="material-symbols-outlined">search_check</span> 
                        <span>検索を実行</span>
                    </button>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div id="m3-search-loading" class="m3-loading-overlay">
                <div class="m3-loading-spinner">
                    <svg viewBox="0 0 50 50" class="m3-spinner-svg">
                        <circle class="m3-spinner-path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                    </svg>
                </div>
                <span class="m3-loading-text">Searching...</span>
            </div>
        </div>
    </div>
    <!-- 読了プログレスバー -->
    <div id="m3-reading-progress" class="m3-header__progress-container">
        <div class="m3-header__progress-bar"></div>
    </div>
</header>

<nav class="m3-header__nav">
    <?php 
    if (has_nav_menu('primary')) {
        wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false]); 
    }
    ?>
</nav>