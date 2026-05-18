<?php
/**
 * Template part for displaying the handy bottom navigation (mobile singular).
 */
?>
<?php if (is_singular()) : ?>
<nav class="m3-bottom-nav" id="m3-bottom-nav" aria-label="ハンディモードナビゲーション">
    <button class="m3-bottom-nav__item" id="m3-handy-toc-trigger" aria-label="目次">
        <span class="material-symbols-outlined">list_alt</span>
        <span class="m3-bottom-nav__label">目次</span>
    </button>
    <button class="m3-bottom-nav__item" id="m3-back-to-top-handy" aria-label="トップ">
        <span class="material-symbols-outlined">arrow_upward</span>
        <span class="m3-bottom-nav__label">トップ</span>
    </button>
</nav>
<?php endif; ?>
