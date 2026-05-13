<?php get_template_part('template-parts/ad', 'article'); ?>
    <footer class="m3-footer">
        <div class="m3-footer__main">
            <div class="m3-footer__grid">
                <!-- Brand Column -->
                <div class="m3-footer__col m3-footer__col--brand">
                    <div class="site-branding">
                        <span class="m3-logo-text">Node</span>
                    </div>
                    <p class="m3-footer__tagline">AI、ガジェット、ゲームの最新情報をあなたに。</p>
                    <div class="m3-footer__social">
                        <!-- Site-wide social links can be placed here if needed -->
                    </div>
                </div>

                <!-- Nav Column -->
                <div class="m3-footer__col m3-footer__col--nav">
                    <h3 class="m3-footer__title">ナビゲーション</h3>
                    <?php
                    wp_nav_menu(
                        array(
                            'theme_location' => 'footer',
                            'menu_class'     => 'm3-footer__links',
                            'container'      => false,
                            'fallback_cb'    => 'node_footer_menu_fallback',
                        )
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="m3-footer__bottom">
            <div class="m3-footer__bottom-inner">
                <p class="m3-footer__copyright">&copy; <?php echo date('Y'); ?> Luminous Core Teams. <span class="m3-footer__version">v<?php echo wp_get_theme()->get('Version'); ?></span></p>
            </div>
        </div>
    </footer>
</div><!-- .m3-page-container -->

<div class="m3-action-stack <?php echo is_singular() ? 'is-singular' : ''; ?>">
    <!-- 1. Back to Top -->
    <button id="m3-back-to-top" class="m3-fab m3-fab--extended m3-fab--mobile-hidden">
        <span class="material-symbols-outlined">arrow_upward</span>
        <span class="m3-fab-text">トップへ</span>
        <span class="m3-fab-label-top">トップへ戻る</span>
    </button>

    <?php if (is_singular()) : 
        $post_id = get_the_ID();
        $has_ai = !empty(apply_filters('luminous_get_ai_summary', '', $post_id));
        $has_comments = comments_open($post_id) || get_comments_number($post_id) > 0;
    ?>
    <!-- 2. Comment Trigger -->
    <?php if ($has_comments) : ?>
    <button id="m3-scroll-to-comments" class="m3-fab m3-fab--extended m3-fab--mobile-hidden">
        <span class="material-symbols-outlined">chat_bubble</span>
        <span class="m3-fab-text">コメント</span>
        <span class="m3-fab-label-top">コメント欄へ</span>
    </button>
    <?php endif; ?>

    <!-- 3. AI Summary Jump -->
    <?php if ($has_ai) : ?>
    <button id="m3-jump-to-ai" class="m3-fab m3-fab--ai-expressive">
        <span class="material-symbols-outlined">auto_awesome</span>
        <span class="m3-fab-text">AI要約</span>
        <span class="m3-fab-label-top">AI要約へ</span>
    </button>
    <?php endif; ?>

    <!-- 4. TOC Trigger (Bottom-most) -->
    <button id="m3-toc-trigger" class="m3-fab m3-fab--extended m3-fab--mobile-hidden">
        <span class="material-symbols-outlined">list</span>
        <span class="m3-fab-text">目次</span>
        <span class="m3-fab-label-top">目次を表示</span>
    </button>
    <?php endif; ?>
</div>

<div id="m3-ogp-tooltip" class="m3-dynamic-tooltip"></div>
<?php wp_footer(); ?>
</body>
</html>