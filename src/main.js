import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';

document.addEventListener('DOMContentLoaded', async () => {
    // 1. カラー抽出ロジック (既存機能)
    initColorExtraction();

    // 2. ダークモード切り替え
    initDarkMode();

    // 3. 伸縮検索バー
    initSearchBar();

    // 4. ナビゲーションドロワー
    initDrawer();

    // 5. リンクコピー機能 (日本語化)
    initCopyButton();

    // 6. 目次 & FAB (既存機能)
    initTableOfContents();
    initCommentFAB();
});

async function initColorExtraction() {
    const labels = document.querySelectorAll('.m3-label--category');
    if (!labels.length) return;

    for (const label of labels) {
        const colorVal = label.dataset.color;
        const thumbUrl = label.dataset.thumb;
        const cacheId = `${label.textContent.trim()}_${thumbUrl || 'no-img'}`;

        try {
            if (colorVal && colorVal.startsWith('#')) {
                const colors = generateM3Colors(colorVal);
                applyM3Colors(label, colors);
                continue;
            }

            if (colorVal === 'auto' && thumbUrl) {
                const cached = storage.get(cacheId);
                if (cached) {
                    applyM3Colors(label, cached);
                    continue;
                }

                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.src = thumbUrl;
                label.style.opacity = '0.6';

                const rgb = await extractColorFromImage(img);
                const colors = generateM3Colors(rgb);
                applyM3Colors(label, colors);
                storage.set(cacheId, colors);
                label.style.opacity = '1';
            } else {
                const colors = generateM3Colors('#6750A4');
                applyM3Colors(label, colors);
            }
        } catch (err) {
            const colors = generateM3Colors('#6750A4');
            applyM3Colors(label, colors);
        }
    }
}

function applyM3Colors(el, colors) {
    el.style.setProperty('--md-sys-color-secondary-container', colors.secondaryContainer);
    el.style.setProperty('--md-sys-color-on-secondary-container', colors.onSecondaryContainer);
}

function initDarkMode() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const setIcon = (theme) => {
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');
        if (theme === 'dark') {
            if (darkIcon) darkIcon.classList.remove('hidden');
            if (lightIcon) lightIcon.classList.add('hidden');
        } else {
            if (lightIcon) lightIcon.classList.remove('hidden');
            if (darkIcon) darkIcon.classList.add('hidden');
        }
    };

    const currentTheme = localStorage.getItem('theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', currentTheme);
    setIcon(currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            setIcon(theme);
        });
    }
}

function initSearchBar() {
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.querySelector('.m3-search-bar__input');

    if (searchToggle && searchBar && searchInput) {
        searchToggle.addEventListener('click', (e) => {
            if (!searchBar.classList.contains('is-active')) {
                e.preventDefault();
                searchBar.classList.add('is-active');
                searchInput.focus();
            } else if (searchInput.value === '') {
                searchBar.classList.remove('is-active');
            } else {
                searchBar.submit();
            }
        });

        document.addEventListener('click', (e) => {
            if (!searchBar.contains(e.target) && searchBar.classList.contains('is-active')) {
                searchBar.classList.remove('is-active');
            }
        });
    }
}

function initDrawer() {
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');

    if (menuBtn && drawer && scrim) {
        const toggleDrawer = (open) => {
            drawer.classList.toggle('is-open', open);
            scrim.classList.toggle('is-visible', open);
            document.body.style.overflow = open ? 'hidden' : '';
        };

        menuBtn.addEventListener('click', () => toggleDrawer(true));
        scrim.addEventListener('click', () => toggleDrawer(false));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') toggleDrawer(false);
        });
    }
}

function initCopyButton() {
    const copyBtn = document.getElementById('m3-copy-trigger');
    if (copyBtn) {
        const copyIcon = copyBtn.querySelector('.m3-copy-icon');
        const copyLabel = copyBtn.querySelector('.m3-copy-label');

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(window.location.href);
                copyBtn.classList.add('is-success');
                const originalText = copyLabel ? copyLabel.textContent : 'リンクをコピー';
                
                if (copyIcon) copyIcon.textContent = 'check';
                if (copyLabel) copyLabel.textContent = 'リンクをコピーしました'; 
                
                copyBtn.style.transform = 'scale(0.9) translateY(0)';
                setTimeout(() => copyBtn.style.transform = '', 200);

                setTimeout(() => {
                    copyBtn.classList.remove('is-success');
                    if (copyIcon) copyIcon.textContent = 'content_copy';
                    if (copyLabel) copyLabel.textContent = originalText;
                }, 3000);
            } catch (err) {}
        });
    }
}

function initTableOfContents() {
    const articleBody = document.querySelector('.m3-article__body');
    const tocContainer = document.getElementById('m3-toc-container');
    const stickyNav = document.querySelector('.m3-sticky-navigation');

    if (articleBody && tocContainer) {
        const headings = articleBody.querySelectorAll('h2, h3');
        if (headings.length > 0) {
            const tocList = document.createElement('ul');
            headings.forEach((heading, index) => {
                const id = `heading-${index}`;
                heading.id = id;
                const li = document.createElement('li');
                li.className = `toc-level-${heading.tagName.toLowerCase()}`;
                const a = document.createElement('a');
                a.href = `#${id}`;
                a.textContent = heading.textContent;
                li.appendChild(a);
                tocList.appendChild(li);
            });
            tocContainer.innerHTML = ''; // クリア
            tocContainer.appendChild(tocList);
            if (stickyNav) stickyNav.classList.remove('hidden');
        }
    }
}

function initCommentFAB() {
    const commentSection = document.getElementById('comments');
    const stickyComments = document.getElementById('m3-sticky-comments');

    window.addEventListener('scroll', () => {
        const scrollY = window.scrollY;
        const windowHeight = window.innerHeight;
        if (stickyComments && commentSection) {
            const rect = commentSection.getBoundingClientRect();
            if (rect.top < windowHeight - 100) {
                stickyComments.classList.remove('is-visible');
            } else if (scrollY > 600) {
                stickyComments.classList.add('is-visible');
            } else {
                stickyComments.classList.remove('is-visible');
            }
        }
    });
}
