<?php get_template_part('template-parts/ad', 'article'); ?>
    <footer class="m3-footer">
        <div class="m3-footer__main">
            <div class="m3-footer__grid">
                <!-- Brand Column -->
                <div class="m3-footer__col m3-footer__col--brand">
                    <div class="site-branding">
                        <span class="m3-logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                    </div>
                    <p class="m3-footer__tagline">AI、ガジェット、ゲームの最新情報をあなたに。</p>
                    <?php get_template_part( 'template-parts/preferred-source', null, array( 'context' => 'footer' ) ); ?>
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
                    <?php if ( current_user_can( 'edit_posts' ) ) : ?>
                        <ul class="m3-footer__links">

                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="m3-footer__bottom">
            <div class="m3-footer__bottom-inner">
                <p class="m3-footer__copyright">&copy; <?php echo date('Y'); ?> Luminous Core Team. <span class="m3-footer__version">v<?php echo esc_html( node_get_theme_version() ); ?></span></p>
            </div>
        </div>
    </footer>
</div><!-- .m3-page-container -->

<?php get_template_part('template-parts/components/floating-actions'); ?>

<div id="m3-ogp-tooltip" class="m3-dynamic-tooltip"></div>
<?php wp_footer(); ?>
</body>
</html>
