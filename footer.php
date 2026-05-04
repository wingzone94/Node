    <?php get_template_part('template-parts/ad', 'article'); ?>
    <footer class="m3-footer">
        <div class="m3-footer__main">
            <div class="m3-footer__grid">
                <!-- Brand Column -->
                <div class="m3-footer__col m3-footer__col--brand">
                    <div class="site-branding">
                        <span class="m3-logo-text">Luminous Core</span>
                    </div>
                    <p class="m3-footer__tagline">Material You with Deep Intelligence.<br>Empowering creativity through AI and Design.</p>
                    <div class="m3-footer__social">
                        <?php get_template_part('template-parts/social-links'); ?>
                    </div>
                </div>

                <!-- Nav Column -->
                <div class="m3-footer__col m3-footer__col--nav">
                    <h3 class="m3-footer__title">NAVIGATION</h3>
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
                <div class="m3-footer__bottom-links">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span>Built with Gemini 3.1 Pro</span>
                </div>
            </div>
        </div>
    </footer>
</div><!-- .m3-page-container -->

<div class="m3-action-stack">
    <?php if (is_singular()) : 
        $post_id = get_the_ID();
        $has_ai = !empty(apply_filters('luminous_get_ai_summary', '', $post_id));
        $has_comments = comments_open($post_id) || get_comments_number($post_id) > 0;
    ?>
    <!-- 1. Comment Trigger (Bottom-most in stack) -->
    <?php if ($has_comments) : ?>
    <button id="m3-scroll-to-comments" class="m3-fab m3-tooltip-target" data-tooltip="コメント欄へ">
        <span class="material-symbols-outlined">chat_bubble</span>
    </button>
    <?php endif; ?>

    <!-- 2. TOC Trigger -->
    <button id="m3-toc-trigger" class="m3-fab m3-tooltip-target" data-tooltip="目次を表示">
        <span class="material-symbols-outlined">toc</span>
    </button>

    <!-- 3. AI Summary Jump -->
    <?php if ($has_ai) : ?>
    <button id="m3-jump-to-ai" class="m3-fab m3-tooltip-target" data-tooltip="AI要約へジャンプ">
        <span class="material-symbols-outlined">auto_awesome</span>
    </button>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 4. Back to Top (Top-most in stack) -->
    <button id="m3-back-to-top" class="m3-fab m3-tooltip-target" data-tooltip="トップへ戻る">
        <span class="material-symbols-outlined">arrow_upward</span>
    </button>
</div>

<div id="m3-ogp-tooltip" class="m3-dynamic-tooltip"></div>
<?php wp_footer(); ?>
</body>
</html>
