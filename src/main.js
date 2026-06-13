import './gsap';
import './scripts/card-animation';
import './scripts/share-actions';
import { initSearchBar } from './scripts/search-bar';
import { initArticleNavigation } from './scripts/article-navigation';
import { initDrawer } from './scripts/drawer';
import { initHandyMode } from './scripts/handy-mode';
import { initHeroInfoBubble, initLatestGridExpansion, initSectionArchiveLinks } from './scripts/home-layout';
import { initKeyboardSnackbar } from './scripts/keyboard-snackbar';
import { initExpressiveFloatingTOC } from './scripts/expressive-toc';
import { initFloatingActions } from './scripts/floating-actions';
import { initReadingProgressSingleOnly } from './scripts/reading-progress';
import { initScrollAnimations } from './scripts/scroll-animations';
import { initSmartHeader } from './scripts/smart-header';
import { initKeyboardShortcuts, initOverdriveScroll, initRippleEffect, initTooltips } from './scripts/ui-effects';
import { initViewSwitcher } from './scripts/view-switcher';

const optionalInitializers = [
    {
        name: 'initColorExtraction',
        shouldRun: () => document.querySelector('.m3-article__category-group a[data-thumb], .m3-reading-badge-label[data-thumb]'),
        load: () => import('./scripts/color-extraction')
    },
    {
        name: 'initCommentForm',
        shouldRun: () => document.getElementById('commentform'),
        load: () => import('./scripts/comment-form')
    },
    {
        name: 'initHeaderClock',
        shouldRun: () => document.getElementById('m3-header-clock'),
        load: () => import('./scripts/header-clock')
    },
    {
        name: 'initTableSorter',
        shouldRun: () => document.querySelector('.wp-block-table.is-sortable table, .wp-block-table.is-style-sortable table, .m3-table--sortable table'),
        load: () => import('./scripts/table-sorter')
    },
    {
        name: 'initPagination',
        shouldRun: () => (
            document.getElementById('m3-page-selector') ||
            document.querySelector('.m3-pagination__number') ||
            document.getElementById('m3-article-toc-anchor') ||
            document.getElementById('m3-article-top-anchor')
        ),
        load: () => import('./scripts/pagination')
    },
    {
        name: 'initReadingBadgeAnimation',
        shouldRun: () => (
            (document.body.classList.contains('single') || document.body.classList.contains('single-post')) &&
            document.getElementById('m3-hero-reading-badge')
        ),
        load: () => import('./scripts/reading-badge-animation')
    }
];

const runOptionalInitializers = () => {
    optionalInitializers.forEach(async optional => {
        if (!optional.shouldRun()) return;

        try {
            const module = await optional.load();
            module[optional.name]();
        } catch (e) {
            console.error(`Initializer failed: ${optional.name}`, e);
        }
    });
};

const scheduleOptionalInitializers = () => {
    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(runOptionalInitializers, { timeout: 1200 });
        return;
    }

    window.setTimeout(runOptionalInitializers, 250);
};

function initColorModeLoader() {
    if (!document.querySelector('#theme-toggle, #m3-theme-toggle-handy, #m3-color-scheme-toggle, .m3-color-scheme-toggle, [data-color-toggle]')) return;

    import('./scripts/color-mode')
        .then(module => {
            module.initColorMode();
        })
        .catch(e => {
            console.error('Initializer failed: initColorMode', e);
        });
}

document.addEventListener('DOMContentLoaded', async () => {
    const initializers = [
        initSearchBar,
        initDrawer,
        initViewSwitcher,
        initColorModeLoader,
        initKeyboardSnackbar,
        initHandyMode,
        initExpressiveFloatingTOC,
        initFloatingActions,
        initSmartHeader,
        initOverdriveScroll,
        initKeyboardShortcuts,
        initTooltips,
        initRippleEffect,
        initReadingProgressSingleOnly,
        initSectionArchiveLinks,
        initLatestGridExpansion,
        initArticleNavigation,
        initHeroInfoBubble,
        initScrollAnimations,
    ];

    initializers.forEach(init => {
        try {
            init();
        } catch (e) {
            console.error(`Initializer failed: ${init.name}`, e);
        }
    });

    scheduleOptionalInitializers();
});
