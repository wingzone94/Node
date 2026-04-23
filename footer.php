<footer class="m3-footer">
    <div class="m3-footer__content">
        <div class="m3-footer__brand">
            <h2 class="m3-logo-text"><?php bloginfo('name'); ?></h2>
            <p class="m3-footer__tagline">Expansive Digital Experience</p>
        </div>

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

</div><!-- #page .m3-page-container -->

<?php wp_footer(); ?>
</body>
</html>