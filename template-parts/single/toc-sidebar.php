<?php
/**
 * Template part for the sidebar-integrated Table of Contents
 */
?>

<aside id="m3-toc-sidebar" class="m3-toc-sidebar" aria-labelledby="toc-sidebar-title">
    <div class="m3-toc-sidebar__inner m3-surface-container-low">
        <div class="m3-toc-sidebar__header">
            <span class="material-symbols-outlined" aria-hidden="true">toc</span>
            <h2 id="toc-sidebar-title" class="m3-toc-sidebar__title">目次</h2>
        </div>
        
        <nav id="m3-toc-sidebar-content" class="m3-toc-sidebar__nav" aria-label="ページ内目次">
            <!-- TOC will be injected here by JS -->
            <div class="m3-toc-sidebar__placeholder">
                <div class="m3-toc-sidebar__loading-line"></div>
                <div class="m3-toc-sidebar__loading-line" style="width: 80%;"></div>
                <div class="m3-toc-sidebar__loading-line" style="width: 60%;"></div>
            </div>
        </nav>

        <div class="m3-toc-sidebar__footer">
            <div class="m3-toc-sidebar__progress-track">
                <div id="m3-toc-progress-bar" class="m3-toc-sidebar__progress-bar"></div>
            </div>
        </div>
    </div>
</aside>
