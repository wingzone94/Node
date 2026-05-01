    <footer class="m3-footer">
        <div class="m3-footer__container">
            <div class="m3-footer__logo">
                <span class="m3-logo-text">Luminous Core</span>
                <p class="m3-footer__tagline">Material You with Deep Intelligence.</p>
            </div>
            <nav class="m3-footer__nav">
                <ul class="m3-footer__links">
                    <li><a href="<?php echo esc_url(home_url('/about/')); ?>">このブログについて</a></li>
                    <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">プライバシーポリシー</a></li>
                    <li><a href="<?php echo esc_url(home_url('/contact/')); ?>">お問い合わせ</a></li>
                </ul>
            </nav>
            <div class="m3-footer__bottom">
                <p class="m3-footer__copyright">&copy; <?php echo date('Y'); ?> Luminous Core Teams. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div><!-- .m3-page-container -->

<div class="m3-action-stack">
    <!-- 1. Back to Top (Top) -->
    <button id="m3-back-to-top" class="m3-fab m3-tooltip-target" data-tooltip="トップへ戻る">
        <span class="material-symbols-outlined">arrow_upward</span>
    </button>

    <?php if (is_singular()) : ?>
    <!-- 2. Comment Trigger -->
    <button id="m3-scroll-to-comments" class="m3-fab m3-tooltip-target" data-tooltip="コメント欄へ">
        <span class="material-symbols-outlined">chat_bubble</span>
    </button>
    <?php endif; ?>

    <?php if (is_singular()) : ?>
    <!-- 3. TOC Trigger -->
    <button id="m3-toc-trigger" class="m3-fab m3-tooltip-target" data-tooltip="目次を表示">
        <span class="material-symbols-outlined">toc</span>
    </button>
    <?php endif; ?>
</div>

<div id="m3-ogp-tooltip" class="m3-dynamic-tooltip"></div>
<?php wp_footer(); ?>
</body>
</html>
