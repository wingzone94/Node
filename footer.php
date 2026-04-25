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

<?php wp_footer(); ?>
</body>
</html>