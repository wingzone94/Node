<footer class="m3-footer">
    <div class="m3-footer__content">


        <nav class="m3-footer__nav" aria-label="フッターナビゲーション">
            <ul class="m3-footer__links">
                <li><a href="<?php echo esc_url(home_url('/about/')); ?>">このブログについて</a></li>
                <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">プライバシーポリシー</a></li>
                <li><a href="<?php echo esc_url(home_url('/contact/')); ?>">お問い合わせ</a></li>
            </ul>
        </nav>

        <div class="m3-footer__meta">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> - Luminous Core Teams</p>
        </div>
    </div>
</footer>

</div> <!-- .m3-page-container -->

<?php if (is_singular()) : ?>
    <!-- 追従ナビゲーションユニット -->
    <div class="m3-sticky-navigation">
        <!-- 追従目次パネル -->
        <div id="m3-sticky-toc" class="m3-sticky-toc">
            <div class="m3-sticky-toc__header">
                <span class="material-symbols-outlined m3-toc-icon">toc</span>
                <span class="m3-sticky-toc__title">目次</span>
                <button id="m3-toc-close" class="m3-toc-close-btn" aria-label="目次を閉じる">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="m3-toc-container" class="m3-toc-body"></div>
        </div>

        <!-- アクションボタンスタック -->
        <div class="m3-action-stack">
            <!-- 目次展開ボタン -->
            <button id="m3-toc-trigger" class="m3-fab m3-fab--tertiary" title="目次を表示" aria-label="目次を表示">
                <span class="material-symbols-outlined">list</span>
            </button>

            <!-- コメントへ移動ボタン -->
            <button id="m3-scroll-to-comments" class="m3-fab m3-fab--secondary" title="コメントへ移動" aria-label="コメントへ移動">
                <span class="material-symbols-outlined">comment</span>
                <?php 
                $comment_count = is_singular() ? get_comments_number(get_queried_object_id()) : 0;
                if ($comment_count > 0) : ?>
                    <span class="m3-fab__badge"><?php echo esc_html($comment_count); ?></span>
                <?php endif; ?>
            </button>

            <!-- 最上部へ戻るボタン -->
            <button id="m3-back-to-top" class="m3-fab m3-fab--primary" title="最上部へ戻る" aria-label="最上部へ戻る">
                <span class="material-symbols-outlined">arrow_upward</span>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if (is_home() || is_archive() || is_search()) : ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof gsap === 'undefined') return;
    const cards = document.querySelectorAll('.m3-post-grid .card-standard, .m3-post-grid .card-featured, .m3-spotlight-badge, .special-features__item');
    if (!cards.length) return;
    
    // 初期状態として非表示＆少し下に下げておく
    gsap.set(cards, { autoAlpha: 0, y: 40, scale: 0.95 });
    
    // Material Design らしい Stagger (ずらし) アニメーションを再生
    gsap.to(cards, {
        autoAlpha: 1, 
        y: 0, 
        scale: 1, 
        duration: 0.6, 
        stagger: 0.08, 
        ease: "back.out(1.2)"
    });
});
</script>
<?php endif; ?>

<script>
/**
 * System Share & Action Logic
 * ビルド環境に依存せず確実に動作させるためのホットパッチ
 */
/**
 * Share Logic Hotfix
 * 既存の built main.js の挙動をオーバーライドして正常化する
 */
(function() {
    // コピー処理の共通関数
    const executeCopy = async (btn, url) => {
        try {
            await navigator.clipboard.writeText(url);
            btn.classList.add('is-success');
            const label = btn.querySelector('.m3-share-btn__label');
            const icon = btn.querySelector('span.material-symbols-outlined, i');
            const originalText = label ? label.textContent : '';
            const originalIcon = icon ? icon.textContent : '';

            if (label) label.textContent = 'コピーしました！';
            if (icon && icon.classList.contains('material-symbols-outlined')) icon.textContent = 'check';

            setTimeout(() => {
                btn.classList.remove('is-success');
                if (label) label.textContent = originalText;
                if (icon && icon.classList.contains('material-symbols-outlined')) icon.textContent = originalIcon;
            }, 2000);
        } catch (err) {
            console.error('Copy failed:', err);
        }
    };

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.m3-share-btn');
        if (!btn) return;

        const isSystemShare = btn.id === 'm3-system-share-trigger' || btn.classList.contains('m3-share-btn--system');

        if (isSystemShare) {
            e.stopImmediatePropagation();
            e.preventDefault();

            const urlToShare = btn.dataset.url || window.location.href;

            if (navigator.share) {
                try {
                    await navigator.share({
                        title: document.title,
                        url: urlToShare
                    });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        // シェアに失敗した場合はコピーへフォールバック（自分自身をコピー状態にする）
                        executeCopy(btn, urlToShare);
                    }
                }
            } else {
                // システムシェア非対応時は、他のボタンをクリックせず、このボタン自体でコピーを実行
                executeCopy(btn, urlToShare);
            }
        }
    }, { capture: true }); 
})();
</script>

<?php wp_footer(); ?>
</body>
</html>