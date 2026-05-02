<?php get_header(); 

// 404 Themes
$m3_404_themes = [
    'minecraft-lava' => '溶岩遊泳',
    'fortnite' => 'マッチ脱落',
    'minecraft-creeper' => 'クリーパーの爆発',
    'material-expressive' => 'Expressive UI (M3)',
    'minecraft-raid' => '村の襲撃',
    'windows-bsod' => 'システムエラー'
];

// Weighted Random Selection (Lower frequency for Fortnite on mobile)
$m3_404_keys = array_keys($m3_404_themes);
$m3_weights = [];
foreach ($m3_404_keys as $key) {
    if (wp_is_mobile() && $key === 'fortnite') {
        $m3_weights[$key] = 1;
    } else {
        $m3_weights[$key] = 10;
    }
}
$m3_weighted_keys = [];
foreach ($m3_weights as $key => $weight) {
    for ($i = 0; $i < $weight; $i++) { $m3_weighted_keys[] = $key; }
}
$m3_404_current = $m3_weighted_keys[array_rand($m3_weighted_keys)];

// Fetch static copyrights
$m3_copyrights = [];
foreach ($m3_404_keys as $key) {
    $m3_copyrights[$key] = node_get_404_copyright($key);
}
?>

<main id="primary" class="site-main m3-404-page m3-404--<?php echo esc_attr($m3_404_current); ?>">
    <!-- Background Layers -->
    <div class="m3-404-bg no-copy">
        <div class="m3-404-theme-bg m3-bg-minecraft-lava"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/minecraft-lava.png'); ?>" alt="" loading="lazy"></div>
        <div class="m3-404-theme-bg m3-bg-minecraft-creeper"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/creeper_aftermath_bg.png'); ?>" alt="" loading="lazy"></div>
        <div class="m3-404-theme-bg m3-bg-minecraft-raid"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/pillager_raid_aftermath_bg.png'); ?>" alt="" loading="lazy"></div>
        
        <!-- Fortnite Specific Background -->
        <div class="m3-404-theme-bg m3-bg-fortnite"><div class="m3-404-fortnite-bg"></div></div>
        
        <!-- Material 3 Expressive UI Background (Paper/Surfaces) -->
        <div class="m3-404-theme-bg m3-bg-material-expressive">
            <div class="m3-expressive-paper-canvas">
                <div class="m3-expressive-blob m3-blob-1"></div>
                <div class="m3-expressive-blob m3-blob-2"></div>
                <div class="m3-expressive-blob m3-blob-3"></div>
            </div>
        </div>

        <div class="m3-404-theme-bg m3-bg-windows-bsod"><div class="m3-404-bsod-bg"></div></div>
        <div class="m3-404-overlay"></div>
    </div>

    <!-- BSOD Specific Content -->
    <div class="m3-bsod-content no-copy">
        <div class="m3-bsod-smiley">:(</div>
        <h1 class="m3-bsod-title">ページのリクエストで問題が発生したため、再読み込みが必要です。</h1>
        <p class="m3-bsod-text">お探しのコンテンツが見つかりませんでした。404 エラー情報を収集しています。完了したら、自動的にホームページの案内を表示します。</p>
        <p class="m3-bsod-progress"><span>0</span>% 完了</p>
        <div class="m3-bsod-footer">
            <div class="m3-bsod-qr"></div>
            <div class="m3-bsod-info">
                詳細については、後で次のエラーをオンラインで検索してください: 404_PAGE_NOT_FOUND<br>
                停止コード: NODE_THEME_REQUEST_FAILED
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="m3-404-content no-copy">
        <div id="m3-404-trigger-area" title="Secret Area">
            <!-- Fortnite Theme -->
            <div class="m3-theme-specific m3-content-fortnite">
                <div class="m3-fortnite-banner">
                    <div class="m3-fortnite-rank">#404</div>
                    <div class="m3-fortnite-label">PLACED</div>
                </div>
                <div class="m3-fortnite-eliminated">ELIMINATED</div>
            </div>
            
            <!-- Minecraft Creeper -->
            <div class="m3-theme-specific m3-content-minecraft-creeper">
                <h1 class="m3-mc-death-msg">クリーパーに爆破された</h1>
            </div>

            <!-- Minecraft Raid -->
            <div class="m3-theme-specific m3-content-minecraft-raid">
                <h1 class="m3-mc-death-msg">村は襲撃によって破壊された</h1>
            </div>

            <!-- Material 3 Expressive UI -->
            <div class="m3-theme-specific m3-content-material-expressive">
                <div class="m3-expressive-ui-card">
                    <div class="m3-expressive-ui-header">
                        <span class="m3-expressive-ui-chip">Error 404</span>
                    </div>
                    <h1 class="m3-expressive-ui-title">Page Not Found</h1>
                    <p class="m3-expressive-ui-text">The requested component could not be inflated. Please check the destination or return home.</p>
                    <div class="m3-expressive-ui-visual">
                        <div class="m3-expressive-shape">404</div>
                    </div>
                </div>
            </div>

            <!-- Default / Lava -->
            <div class="m3-theme-specific m3-content-default">
                <h1 class="m3-404-title-large">404</h1>
            </div>
        </div>

        <!-- Global UI (except BSOD handled via CSS) -->
        <div class="m3-404-main-text">
            <h2 class="m3-404-subtitle" id="m3-404-subtext">ページが見つかりません</h2>
            <p class="m3-404-description" id="m3-404-desc">アクセスしようとしたページは削除されたか、URLが変更されている可能性があります。</p>
            
            <div class="m3-404-actions">
                <div class="m3-404-button-group">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="m3-mc-button" id="m3-404-home-btn">
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
    </div>

    <!-- Subtle Disclaimer & Copyright -->
    <div class="m3-404-disclaimer">
        <p>
            <span id="m3-404-original-copyright"><?php echo esc_html($m3_copyrights[$m3_404_current]); ?></span>. 
            All artworks are AI-generated parodies. <span class="m3-disclaimer-copy-guard">Unauthorized reproduction prohibited.</span>
        </p>
    </div>

    <!-- Gallery Modal -->
    <div id="m3-404-gallery-modal" class="m3-gallery-modal">
        <div class="m3-gallery-content">
            <div class="m3-gallery-header">
                <h3>404 Art Gallery <small style="font-size: 0.6em; opacity: 0.6; font-weight: normal;">(AI Generated Parody)</small></h3>
                <button class="m3-gallery-close"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="m3-gallery-grid">
                <?php foreach ($m3_404_themes as $key => $name): ?>
                    <div class="m3-gallery-item" data-theme="<?php echo esc_attr($key); ?>">
                        <div class="m3-gallery-preview m3-preview-<?php echo esc_attr($key); ?>"></div>
                        <span><?php echo esc_html($name); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const main = document.querySelector('.m3-404-page');
    const triggerArea = document.getElementById('m3-404-trigger-area');
    const modal = document.getElementById('m3-404-gallery-modal');
    const close = modal.querySelector('.m3-gallery-close');
    const items = modal.querySelectorAll('.m3-gallery-item');
    const copyrightEl = document.getElementById('m3-404-original-copyright');
    
    const copyrights = <?php echo json_encode($m3_copyrights); ?>;

    // Copy Guard
    main.addEventListener('contextmenu', (e) => e.preventDefault());
    main.addEventListener('dragstart', (e) => e.preventDefault());

    // Easter Egg (Triple Click)
    let clickCount = 0;
    triggerArea.addEventListener('click', () => {
        clickCount++;
        if (clickCount === 3) {
            modal.classList.add('is-active');
            clickCount = 0;
        }
        setTimeout(() => { clickCount = 0; }, 1000);
    });

    const subtext = document.getElementById('m3-404-subtext');
    const desc = document.getElementById('m3-404-desc');
    const homeBtn = document.getElementById('m3-404-home-btn');

    const themeData = {
        'minecraft-lava': { sub: 'ページが見つかりません', desc: '溶岩の中に落ちてしまったようです。', btn: 'ホームへ戻る' },
        'fortnite': { sub: 'マッチから離脱しました', desc: 'アクセスしようとしたページはストームの中に消えたようです。', btn: 'ロビーに戻る' },
        'minecraft-creeper': { sub: 'You died!', desc: '背後に気をつけたほうがよかったかもしれません。sssss...', btn: 'リスポーン' },
        'minecraft-raid': { sub: '襲撃終了', desc: '村を守ることはできませんでした。ページは略奪されました。', btn: 'リスポーン' },
        'material-expressive': { sub: 'Expressive Design', desc: 'このページのデザインシステムは現在再構築中です。', btn: 'Re-center' },
        'windows-bsod': { sub: '', desc: '', btn: '' }
    };

    close.addEventListener('click', () => modal.classList.remove('is-active'));
    modal.addEventListener('click', (e) => { if(e.target === modal) modal.classList.remove('is-active'); });

    items.forEach(item => {
        item.addEventListener('click', () => {
            const theme = item.dataset.theme;
            main.className = 'site-main m3-404-page m3-404--' + theme;
            
            if (themeData[theme]) {
                subtext.textContent = themeData[theme].sub;
                desc.textContent = themeData[theme].desc;
                homeBtn.textContent = themeData[theme].btn;
            }

            if (copyrights[theme]) {
                copyrightEl.textContent = copyrights[theme];
            }

            modal.classList.remove('is-active');

            if (theme === 'windows-bsod') {
                startBSOD();
            }
        });
    });

    // BSOD Progress Animation
    let bsodInterval;
    function startBSOD() {
        if(bsodInterval) clearInterval(bsodInterval);
        let prog = 0;
        const progSpan = document.querySelector('.m3-bsod-progress span');
        if(!progSpan) return;
        progSpan.textContent = "0";
        bsodInterval = setInterval(() => {
            prog += Math.floor(Math.random() * 5);
            if (prog >= 100) { prog = 100; clearInterval(bsodInterval); }
            progSpan.textContent = prog;
        }, 1000);
    }
    if (main.classList.contains('m3-404--windows-bsod')) startBSOD();
});
</script>

<?php get_footer(); ?>
