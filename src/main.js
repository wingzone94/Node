import './gsap';
import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';
import './scripts/card-animation';
import './scripts/share-actions';
import { initHandyMode } from './scripts/handy-mode';
import { initPagination } from './scripts/pagination';
import { initThemeManager } from './scripts/theme-manager';
import { initFloatingActions } from './scripts/floating-actions';
import { initReadingProgress } from './scripts/reading-progress';

document.addEventListener('DOMContentLoaded', async () => {
    const initializers = [
        initColorExtraction,
        initThemeManager,
        initSearchBar,
        initDrawer,
        initViewSwitcher,
        initHandyMode,
        initExpressiveFloatingTOC,
        initFloatingActionsPatched,
        initOverdriveScroll,
        initKeyboardShortcuts,
        initTooltips,
        initRippleEffect,
        initReadingProgressSingleOnly,
        initPagination,
        initLatestGridExpansion,
        initArticleNavigation,
        initHeroInfoBubble,
        initScrollAnimations,
        initHeaderClock,
        initTableSorter,
        initCommentForm
    ];

    initializers.forEach(init => {
        try {
            init();
        } catch (e) {
            console.error(`Initializer failed: ${init.name}`, e);
        }
    });
});

function isSinglePostView() {
    return document.body.classList.contains('single') || document.body.classList.contains('single-post');
}

function isIndexOrArchiveView() {
    const body = document.body;
    return body.classList.contains('home')
        || body.classList.contains('blog')
        || body.classList.contains('archive')
        || body.classList.contains('category')
        || body.classList.contains('tag')
        || body.classList.contains('date')
        || body.classList.contains('author');
}

function initReadingProgressSingleOnly() {
    const progressContainer = document.getElementById('m3-reading-progress');
    if (!isSinglePostView()) {
        if (progressContainer) progressContainer.style.display = 'none';
        return;
    }

    initReadingProgress();
}

function initFloatingActionsPatched() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const isSingle = isSinglePostView();
    const showBackToTopContext = isSingle || isIndexOrArchiveView();
    const backToTop = document.getElementById('m3-back-to-top');
    const tocTrigger = document.getElementById('m3-toc-trigger');
    const handyTocTrigger = document.getElementById('m3-handy-toc-trigger');
    const tocPanel = document.getElementById('m3-sticky-toc');

    backToTop?.addEventListener('click', (event) => {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    if (!isSingle) {
        if (tocTrigger) {
            tocTrigger.classList.remove('toc-ready');
            tocTrigger.style.display = 'none';
        }
        if (handyTocTrigger) handyTocTrigger.style.display = 'none';
        tocPanel?.classList.remove('is-active');
        document.body.classList.remove('is-active-toc');
    }

    const updateVisibility = () => {
        if (!showBackToTopContext) {
            actionStack.classList.remove('is-visible');
            return;
        }

        const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        const tocReady = isSingle && Boolean(document.querySelector('#m3-toc-trigger.toc-ready'));
        const isDesktop = window.matchMedia('(min-width: 1001px)').matches;
        const shouldShow = isDesktop || scrollY > 200 || tocReady;

        actionStack.classList.toggle('is-visible', shouldShow);
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility, { passive: true });
    document.addEventListener('m3:toc:ready', updateVisibility);
    setTimeout(updateVisibility, 150);
}

function initLatestGridExpansion() {
    const body = document.body;
    const isLatestPage = (body.classList.contains('home')
        || body.classList.contains('blog')
        || body.classList.contains('front-page'))
        && !body.classList.contains('paged');

    if (!isLatestPage) return;

    const cardSelector = '.m3-card, .m3-elevated-nav-card, .special-features__item, .m3-nexus-card, .m3-blog-card, .m3-product-card';
    const applyLatestLayout = () => {
        const latestSurface = document.querySelector('.m3-surface--latest');
        const latestContainer = latestSurface?.querySelector('.m3-post-grid__container--featured');
        const articlesContainer = document.querySelector('.m3-surface--articles .m3-post-grid__container.is-articles-grid');
        if (!latestSurface || !latestContainer) return;

        const latestCards = Array.from(latestContainer.children).filter((node) => node.matches?.(cardSelector));
        const extraCards = Array.from(articlesContainer?.children || []).filter((node) => node.matches?.(cardSelector));
        const needed = Math.max(0, 9 - latestCards.length);

        for (let i = 0; i < needed && i < extraCards.length; i += 1) {
            latestContainer.appendChild(extraCards[i]);
        }

        latestContainer.querySelectorAll(cardSelector).forEach((card) => {
            card.style.display = '';
        });

        if (articlesContainer) {
            const hasArticleCards = Array.from(articlesContainer.children).some((node) => node.matches?.(cardSelector));
            if (!hasArticleCards) {
                articlesContainer.closest('.m3-surface--articles')?.classList.add('is-empty');
            }
        }

        if (!latestSurface.querySelector('.m3-latest-see-all')) {
            const sourceLink = document.querySelector('.m3-archive-pill-button')?.getAttribute('href')
                || document.querySelector('.m3-headlines__more')?.getAttribute('href')
                || new URL('page/2/', window.location.href).toString();

            const seeAllLink = document.createElement('a');
            seeAllLink.className = 'm3-latest-see-all m3-button m3-button--text';
            seeAllLink.href = sourceLink;
            seeAllLink.innerHTML = [
                '<span class="m3-latest-see-all__text">すべて見る</span>',
                '<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>'
            ].join('');
            latestSurface.appendChild(seeAllLink);
        }

        window.dispatchEvent(new Event('resize'));
    };

    applyLatestLayout();
    setTimeout(applyLatestLayout, 180);
}

function initHeroInfoBubble() {
    const trigger = document.getElementById('m3-hero-reading-badge');
    const panel = document.getElementById('m3-hero-info-panel');
    if (!trigger) return;

    const showInfo = () => {
        trigger.classList.add('is-info-active');
        trigger.setAttribute('aria-expanded', 'true');
        panel?.classList.add('is-active');
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (trigger.classList.contains('is-info-active')) {
            trigger.classList.remove('is-info-active');
            trigger.setAttribute('aria-expanded', 'false');
            panel?.classList.remove('is-active');
        } else {
            showInfo();
        }
    });

    trigger.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        trigger.click();
    });

    document.addEventListener('click', (e) => {
        if (!trigger.contains(e.target) && (!panel || !panel.contains(e.target))) {
            trigger.classList.remove('is-info-active');
            trigger.setAttribute('aria-expanded', 'false');
            panel?.classList.remove('is-active');
        }
    });
}

function initHeaderClock() {
    const clock = document.getElementById('m3-header-clock');
    if (!clock) return;

    const update = () => {
        const now = new Date();
        const greetingEl = document.getElementById('m3-header-greeting');
        const dateEl = document.getElementById('m3-header-date');
        const timeEl = document.getElementById('m3-header-time');

        if (greetingEl) {
            const hour = now.getHours();
            let g = "Hello";
            if (hour < 5) g = "Good night";
            else if (hour < 12) g = "Good morning";
            else if (hour < 18) g = "Good afternoon";
            else g = "Good evening";
            greetingEl.textContent = g;
        }

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        }

        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        }

        // Auto hide after 5 seconds if not home/front
        const isHomePage = document.body.classList.contains('home') || document.body.classList.contains('front-page');
        if (!isHomePage) {
            setTimeout(() => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(clock, {
                        opacity: 0,
                        y: 10,
                        duration: 1.5,
                        ease: "power3.inOut",
                        onComplete: () => {
                            clock.style.display = 'none';
                        }
                    });
                } else {
                    clock.style.display = 'none';
                }
            }, 5000);
        }
    };

    setInterval(update, 1000);
    update();
}

function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Optional: keep observing if you want reveal-on-each-scroll, 
                // but unobserve is standard for "reveal once"
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.m3-reading-badge, .m3-reveal, .m3-reveal-group').forEach(el => observer.observe(el));
}

function initArticleNavigation() {
    // Event delegation for better reliability
    document.addEventListener('click', (e) => {
        // Hero Comment Trigger
        const commentTrigger = e.target.closest('#m3-hero-comment-trigger');
        if (commentTrigger) {
            e.preventDefault();
            console.log('Main: Hero Comment Clicked');
            const target = document.getElementById('comments');
            if (target) {
                const headerOffset = 120; // 余裕を持たせたオフセット
                const elementPosition = target.getBoundingClientRect().top + window.scrollY;
                window.scrollTo({
                    top: elementPosition - headerOffset,
                    behavior: 'smooth'
                });
            }
        }
    });
}

function initExpressiveFloatingTOC() {
    if (!isSinglePostView()) return;

    const root = document.documentElement;
    const headingSelector = 'h1, h2, h3, h4, h5, h6';
    const normalizeText = (value) => value.replace(/\s+/g, ' ').trim();
    const slugify = (value) => {
        return normalizeText(value)
            .toLowerCase()
            .replace(/[^\p{Letter}\p{Number}\s-]/gu, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    };

    let headings = [];

    const getArticle = () => {
        return document.querySelector('.m3-article__body')
            || document.querySelector('.entry-content')
            || document.querySelector('.wp-block-post-content')
            || document.querySelector('article');
    };

    const getRefs = () => {
        return {
            tocPanel: document.getElementById('m3-sticky-toc'),
            tocContainer: document.getElementById('m3-toc-container'),
            tocTrigger: document.getElementById('m3-toc-trigger'),
            handyTrigger: document.getElementById('m3-handy-toc-trigger'),
            backToTopTrigger: document.getElementById('m3-back-to-top'),
            actionStack: document.querySelector('.m3-action-stack'),
            bottomNav: document.getElementById('m3-bottom-nav')
        };
    };

    const ensureShell = () => {
        let {
            tocPanel,
            tocContainer,
            tocTrigger,
            handyTrigger,
            actionStack,
            bottomNav
        } = getRefs();

        if (!tocTrigger && actionStack) {
            tocTrigger = document.createElement('button');
            tocTrigger.id = 'm3-toc-trigger';
            tocTrigger.type = 'button';
            tocTrigger.className = 'm3-fab m3-fab--extended m3-fab--mobile-hidden toc-ready';
            tocTrigger.setAttribute('aria-label', '目次を表示');
            tocTrigger.innerHTML = [
                '<span class="material-symbols-outlined" aria-hidden="true">list</span>',
                '<span class="m3-fab-text">目次</span>'
            ].join('');
            actionStack.appendChild(tocTrigger);
        }

        if (!handyTrigger && bottomNav) {
            handyTrigger = document.createElement('button');
            handyTrigger.id = 'm3-handy-toc-trigger';
            handyTrigger.type = 'button';
            handyTrigger.className = 'm3-bottom-nav__item';
            handyTrigger.setAttribute('aria-label', '目次');
            handyTrigger.innerHTML = [
                '<span class="material-symbols-outlined" aria-hidden="true">list_alt</span>',
                '<span class="m3-bottom-nav__label">目次</span>'
            ].join('');
            bottomNav.prepend(handyTrigger);
        }

        if (!tocPanel) {
            tocPanel = document.createElement('div');
            tocPanel.id = 'm3-sticky-toc';
            tocPanel.className = 'm3-sticky-toc';
            tocPanel.setAttribute('aria-hidden', 'true');
            tocPanel.innerHTML = [
                '<div class="m3-sticky-toc__header">',
                '<span class="material-symbols-outlined m3-toc-icon" aria-hidden="true">toc</span>',
                '<span class="m3-sticky-toc__title">目次</span>',
                '<button type="button" id="m3-toc-close" class="m3-toc-close-btn" aria-label="目次を閉じる">',
                '<span class="material-symbols-outlined" aria-hidden="true">close</span>',
                '</button>',
                '</div>',
                '<nav id="m3-toc-container" class="m3-toc-body" aria-label="ページ内目次"></nav>'
            ].join('');
            document.body.appendChild(tocPanel);
            tocContainer = tocPanel.querySelector('#m3-toc-container');
        }

        if (!tocContainer && tocPanel) {
            tocContainer = document.createElement('nav');
            tocContainer.id = 'm3-toc-container';
            tocContainer.className = 'm3-toc-body';
            tocContainer.setAttribute('aria-label', 'ページ内目次');
            tocPanel.appendChild(tocContainer);
        }
    };

    const getScrollTarget = (heading) => {
        const header = document.querySelector('.m3-header');
        const headerHeight = header ? header.getBoundingClientRect().height : 0;
        return heading.getBoundingClientRect().top + window.scrollY - Math.max(88, headerHeight + 16);
    };

    const updateActiveLink = () => {
        const { tocContainer } = getRefs();
        if (!tocContainer || !headings.length) return;

        const header = document.querySelector('.m3-header');
        const scrollBase = window.scrollY + Math.max(88, (header ? header.getBoundingClientRect().height : 0) + 24);
        let currentId = headings[0]?.id;

        headings.forEach((heading) => {
            const top = heading.getBoundingClientRect().top + window.scrollY;
            if (scrollBase >= top) currentId = heading.id;
        });

        tocContainer.querySelectorAll('.m3-toc-link').forEach((link) => {
            link.classList.toggle('is-active', link.getAttribute('href') === `#${currentId}`);
        });
    };

    const setPanelOpen = (open) => {
        const { tocPanel, tocTrigger, handyTrigger } = getRefs();
        if (!tocPanel) return;

        let scrim = document.querySelector('.m3-toc-scrim');
        if (!scrim) {
            scrim = document.createElement('div');
            scrim.className = 'm3-toc-scrim';
            document.body.appendChild(scrim);
        }

        tocPanel.classList.toggle('is-active', open);
        tocPanel.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('is-active-toc', open);
        scrim.classList.toggle('is-active', open);
        tocTrigger?.setAttribute('aria-expanded', String(open));
        handyTrigger?.setAttribute('aria-expanded', String(open));

        if (open) {
            updateActiveLink();
        }
    };

    const renderTOC = () => {
        const article = getArticle();
        const {
            backToTopTrigger,
            actionStack
        } = getRefs();

        if (!article) return;

        headings = Array.from(article.querySelectorAll(headingSelector)).filter((heading) => {
            if (heading.closest('#m3-sticky-toc, #m3-inline-toc, #comments, #comments-section, .m3-post-comment-toc')) {
                return false;
            }
            return Boolean(normalizeText(heading.textContent || ''));
        });

        if (!headings.length) {
            const { tocPanel, tocTrigger, handyTrigger } = getRefs();
            tocTrigger?.classList.remove('toc-ready');
            if (tocTrigger) tocTrigger.style.display = 'none';
            if (handyTrigger) handyTrigger.style.display = 'none';
            actionStack?.classList.remove('is-has-toc');
            tocPanel?.classList.remove('is-active');
            document.body.classList.remove('is-active-toc');
            return;
        }

        ensureShell();

        const {
            tocPanel,
            tocContainer,
            tocTrigger,
            handyTrigger
        } = getRefs();

        tocPanel?.classList.add('m3-sticky-toc--expressive');
        tocTrigger?.classList.add('toc-ready');
        tocTrigger?.setAttribute('aria-label', '目次を表示');
        tocTrigger?.setAttribute('aria-controls', 'm3-sticky-toc');
        tocTrigger?.setAttribute('aria-haspopup', 'menu');
        handyTrigger?.setAttribute('aria-label', '目次を表示');
        handyTrigger?.setAttribute('aria-controls', 'm3-sticky-toc');
        backToTopTrigger?.setAttribute('aria-label', '最上部へ戻る');
        actionStack?.classList.add('is-has-toc', 'is-visible');

        const usedIds = new Set(Array.from(document.querySelectorAll('[id]')).map((element) => element.id));
        headings.forEach((heading, index) => {
            if (!heading.id) {
                const slug = slugify(heading.textContent || `section-${index + 1}`);
                const baseId = slug ? `toc-${slug}` : `section-${index + 1}`;
                let nextId = baseId;
                let suffix = 2;
                while (usedIds.has(nextId)) {
                    nextId = `${baseId}-${suffix}`;
                    suffix += 1;
                }
                heading.id = nextId;
                usedIds.add(nextId);
            }
        });

        if (!tocContainer) return;

        const list = document.createElement('ul');
        list.className = 'm3-toc-list m3-toc-list--expressive';

        headings.forEach((heading) => {
            const item = document.createElement('li');
            const level = heading.tagName.toLowerCase();
            item.className = `m3-toc-item m3-toc-item--${level}`;

            const link = document.createElement('a');
            link.className = 'm3-toc-link';
            link.href = `#${heading.id}`;
            link.textContent = normalizeText(heading.textContent || '');

            item.appendChild(link);
            list.appendChild(item);
        });

        tocContainer.innerHTML = '';
        tocContainer.appendChild(list);
        tocContainer.dataset.m3Enhanced = 'true';
        updateActiveLink();
        document.dispatchEvent(new CustomEvent('m3:toc:ready'));
    };

    if (!root.dataset.m3TocBound) {
        root.dataset.m3TocBound = 'true';

        const scheduleRender = () => {
            window.clearTimeout(window.__m3TocRenderTimer);
            window.__m3TocRenderTimer = window.setTimeout(() => {
                window.__m3RefreshFloatingTOC?.();
            }, 80);
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('#m3-toc-trigger, #m3-handy-toc-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation?.();
                const { tocPanel } = getRefs();
                setPanelOpen(!(tocPanel?.classList.contains('is-active')));
                return;
            }

            const link = event.target.closest('#m3-toc-container a[href^="#"]');
            if (link) {
                const targetId = link.getAttribute('href').slice(1);
                const heading = document.getElementById(targetId);
                if (!heading) return;
                event.preventDefault();
                window.scrollTo({ top: getScrollTarget(heading), behavior: 'smooth' });
                history.replaceState(null, '', `#${targetId}`);
                setPanelOpen(false);
                return;
            }

            const closeButton = event.target.closest('#m3-toc-close');
            if (closeButton) {
                event.preventDefault();
                setPanelOpen(false);
                return;
            }

            const { tocPanel, tocTrigger, handyTrigger } = getRefs();
            if (!tocPanel?.classList.contains('is-active')) return;

            const clickedPanel = tocPanel.contains(event.target);
            const clickedTrigger = tocTrigger?.contains(event.target) || handyTrigger?.contains(event.target);
            if (!clickedPanel && !clickedTrigger) {
                setPanelOpen(false);
            }
        }, true);

        document.addEventListener('m3:toc:toggle', (event) => {
            event.preventDefault?.();
            event.stopPropagation?.();
            event.stopImmediatePropagation?.();
            const { tocPanel } = getRefs();
            setPanelOpen(!(tocPanel?.classList.contains('is-active')));
        }, true);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setPanelOpen(false);
        });

        window.addEventListener('scroll', updateActiveLink, { passive: true });
        window.addEventListener('resize', scheduleRender, { passive: true });
        window.addEventListener('pageshow', scheduleRender);

        const observer = new MutationObserver(() => scheduleRender());
        observer.observe(document.body, { childList: true, subtree: true });
    }

    window.__m3RefreshFloatingTOC = renderTOC;
    renderTOC();
    setTimeout(renderTOC, 120);
    setTimeout(renderTOC, 600);
}




function initColorExtraction() {
    const badges = document.querySelectorAll('.m3-article__category-group a, .m3-reading-badge-label');
    badges.forEach(badge => {
        const thumbUrl = badge.dataset.thumb;
        if (thumbUrl) {
            extractColorFromImage(thumbUrl).then(color => {
                if (color) {
                    badge.style.backgroundColor = color;
                    badge.style.color = '#ffffff';
                    badge.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';
                }
            });
        }
    });
}


function initSearchBar() {
    console.log('Search Bar: Initializing Full Version...');
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.getElementById('m3-search-input');
    const searchClear = document.getElementById('m3-search-clear');
    const modal = document.getElementById('m3-advanced-search-modal');
    const modalClose = document.getElementById('m3-advanced-search-close');
    const modalReset = document.getElementById('m3-advanced-search-reset');
    const modalApply = document.getElementById('m3-advanced-search-apply');
    const header = document.querySelector('.m3-header');

    if (!searchToggle || !searchBar || !searchInput) return;

    // --- Search Bar Toggle ---
    searchToggle.addEventListener('click', (e) => {
        if (!searchBar.classList.contains('is-active')) {
            searchBar.classList.add('is-active');
            header?.classList.add('search-is-active');
            setTimeout(() => searchInput.focus(), 300);
        } else if (!searchInput.value.trim()) {
            searchBar.classList.remove('is-active');
            header?.classList.remove('search-is-active');
        } else {
            searchBar.submit();
        }
    });

    // --- Clear Button ---
    const updateClearBtn = () => {
        if (searchClear) searchClear.style.display = searchInput.value ? 'flex' : 'none';
    };

    // Initialize state
    updateClearBtn();

    searchInput.addEventListener('input', updateClearBtn);
    searchInput.addEventListener('change', updateClearBtn);
    searchInput.addEventListener('search', updateClearBtn); // For 'x' in type="search" browsers

    searchClear?.addEventListener('click', () => {
        if (searchInput.value) {
            animateSearchClear(searchInput, searchClear, () => {
                updateClearBtn();
            });
            searchInput.focus();
        }
    });

    // --- Mobile Close ---
    document.getElementById('m3-search-mobile-close')?.addEventListener('click', () => {
        searchBar.classList.remove('is-active');
        header?.classList.remove('search-is-active');
    });

    if (!modal) return;

    const openModal = () => {
        console.log('Search Bar: Opening Modal...');
        modal.classList.add('is-active');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        document.body.style.overflow = 'hidden';

        // --- Restore saved search settings ---
        const saved = storage.get('m3-saved-search');
        const saveToggle = document.getElementById('m3-save-search-settings');
        if (saved) {
            console.log('Search Bar: Restoring saved settings...');
            modal.querySelectorAll('input, select').forEach(input => {
                if (saved[input.name] !== undefined) {
                    if (input.type === 'checkbox') {
                        input.checked = Array.isArray(saved[input.name]) ? saved[input.name].includes(input.value) : saved[input.name] === input.value;
                    } else if (input.type === 'radio') {
                        input.checked = saved[input.name] === input.value;
                    } else {
                        input.value = saved[input.name];
                    }
                }
            });
            if (saveToggle) saveToggle.checked = true;
        }

        if (window.innerWidth > 600) {
            switchPage(1);
        }

        setTimeout(() => {
            initRangeSlider();
            updateHitCount();
            updateTabStatus();
        }, 150);
    };

    const closeModal = () => {
        modal.classList.remove('is-active');
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        setTimeout(() => { modal.style.display = 'none'; }, 400);
        document.body.style.overflow = '';
    };

    // --- Global Event Delegation for Advanced Search Trigger ---
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.m3-search-advanced-trigger, #m3-advanced-search-trigger');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        }
    });

    modalClose?.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // --- PC Tab Switching ---
    const switchPage = (pageNum) => {
        if (window.innerWidth <= 600) return;

        const pagesContainer = modal.querySelector('.m3-modal__pages-container');
        const allPages = Array.from(modal.querySelectorAll('.m3-modal__page'));
        const allTabs = Array.from(modal.querySelectorAll('.m3-modal__tab'));

        if (!pagesContainer || allPages.length === 0) return;

        const movePercent = (100 / allPages.length) * (pageNum - 1);
        pagesContainer.style.transform = `translateX(-${movePercent}%)`;

        allPages.forEach(p => p.classList.toggle('is-active', p.dataset.page == pageNum));
        allTabs.forEach(t => t.classList.toggle('is-active', t.dataset.page == pageNum));

        // Update tab indicator
        const tabsContainer = document.getElementById('m3-search-tabs');
        if (tabsContainer) {
            const indicator = tabsContainer.querySelector('.m3-modal__tab-indicator');
            const activeTab = allTabs.find(t => t.dataset.page == pageNum);
            if (indicator && activeTab) {
                indicator.style.width = `${activeTab.offsetWidth}px`;
                indicator.style.left = `${activeTab.offsetLeft}px`;
            }
        }
    };

    modal.querySelectorAll('.m3-modal__tab').forEach(el => {
        el.addEventListener('click', () => switchPage(parseInt(el.dataset.page)));
    });

    const updateTabStatus = () => {
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        tabs.forEach(tab => {
            const pageNum = tab.dataset.page;
            const page = modal.querySelector(`.m3-modal__page[data-page="${pageNum}"]`);
            const hasValue = Array.from(page.querySelectorAll('input, select')).some(input => {
                if (input.type === 'checkbox' || input.type === 'radio') return input.checked && input.value !== 'all';
                if (input.type === 'date' || input.type === 'number') return !!input.value;
                if (input.tagName === 'SELECT') return !!input.value;
                return false;
            });
            tab.classList.toggle('has-value', hasValue);
        });
    };

    // --- Range Slider ---
    function initRangeSlider() {
        const slider = document.getElementById('m3-word-count-slider');
        const minHandle = document.getElementById('m3-slider-handle-min');
        const maxHandle = document.getElementById('m3-slider-handle-max');
        const range = document.getElementById('m3-slider-range');
        const minInput = document.getElementById('m3-min-chars');
        const maxInput = document.getElementById('m3-max-chars');

        if (!slider || !minHandle || !maxHandle || !range || !minInput || !maxInput) return;

        let minVal = parseInt(minInput.value) || 0;
        let maxVal = parseInt(maxInput.value) || 10000;
        const totalMax = 10000;

        const updateUI = () => {
            const rect = slider.getBoundingClientRect();
            if (rect.width === 0) return;

            const minPercent = (minVal / totalMax) * 100;
            const maxPercent = (maxVal / totalMax) * 100;

            const offset = 14;
            const trackWidth = rect.width - (offset * 2);

            const minPos = offset + (minPercent / 100) * trackWidth;
            const maxPos = offset + (maxPercent / 100) * trackWidth;

            minHandle.style.left = `${minPos}px`;
            maxHandle.style.left = `${maxPos}px`;
            range.style.left = `${minPos}px`;
            range.style.width = `${maxPos - minPos}px`;

            minHandle.querySelector('.m3-range-slider__value').textContent = minVal;
            maxHandle.querySelector('.m3-range-slider__value').textContent = maxVal >= totalMax ? '10000+' : maxVal;

            minInput.value = minVal;
            maxInput.value = maxVal;
        };

        const handleDrag = (e, type) => {
            if (e.cancelable) e.preventDefault();
            const rect = slider.getBoundingClientRect();
            const offset = 14;
            const trackWidth = rect.width - (offset * 2);
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const x = clientX - rect.left - offset;

            const percent = Math.min(100, Math.max(0, (x / trackWidth) * 100));
            let val = Math.round((percent / 100) * totalMax);
            val = Math.round(val / 500) * 500;

            if (type === 'min') {
                minVal = Math.min(val, maxVal - 500);
            } else {
                maxVal = Math.max(val, minVal + 500);
            }
            updateUI();
            updateHitCount();
        };

        const onStart = (e, type) => {
            const onMove = (me) => handleDrag(me, type);
            const onEnd = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onEnd);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        };

        minHandle.addEventListener('mousedown', (e) => onStart(e, 'min'));
        minHandle.addEventListener('touchstart', (e) => onStart(e, 'min'), { passive: false });
        maxHandle.addEventListener('mousedown', (e) => onStart(e, 'max'));
        maxHandle.addEventListener('touchstart', (e) => onStart(e, 'max'), { passive: false });

        minInput.addEventListener('change', () => {
            minVal = Math.min(parseInt(minInput.value) || 0, maxVal - 500);
            updateUI();
            updateHitCount();
        });
        maxInput.addEventListener('change', () => {
            maxVal = Math.max(parseInt(maxInput.value) || 0, minVal + 500);
            updateUI();
            updateHitCount();
        });

        window.addEventListener('resize', updateUI);
        updateUI();
    }

    // --- Search Hit Counter ---
    let debounceTimer;
    function updateHitCount() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const data = new FormData();
            modal.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) data.append(input.name, input.value);
                } else {
                    data.append(input.name, input.value);
                }
            });

            const params = new URLSearchParams(data);
            params.append('action', 'm3_get_search_hit_count');

            const counter = document.getElementById('m3-search-hit-count');
            const applyBtn = document.getElementById('m3-advanced-search-apply');

            if (counter) counter.style.opacity = '0.5';

            fetch(m3_ajax.ajax_url, {
                method: 'POST',
                body: params
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success && counter) {
                        counter.textContent = res.data.count;
                        counter.style.opacity = '1';

                        if (applyBtn) {
                            applyBtn.disabled = (res.data.count === 0);
                            applyBtn.style.opacity = res.data.count === 0 ? '0.5' : '1';
                        }
                    }
                });
        }, 300);
    }

    modal.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => { updateHitCount(); updateTabStatus(); });
        if (input.type === 'text' || input.type === 'number') input.addEventListener('input', () => { updateHitCount(); updateTabStatus(); });
    });

    // --- Apply Search ---
    modalApply?.addEventListener('click', () => {
        document.getElementById('m3-search-loading')?.classList.add('is-active');
        const params = new URLSearchParams();
        params.append('s', searchInput.value.trim());

        const searchData = {};
        const saveToggle = document.getElementById('m3-save-search-settings');

        modal.querySelectorAll('input, select').forEach(input => {
            if ((input.type === 'checkbox' || input.type === 'radio')) {
                if (input.checked) {
                    params.append(input.name, input.value);
                    if (input.type === 'checkbox') {
                        if (!searchData[input.name]) searchData[input.name] = [];
                        searchData[input.name].push(input.value);
                    } else {
                        searchData[input.name] = input.value;
                    }
                }
            } else if (input.tagName === 'SELECT' && input.value) {
                params.append(input.name, input.value);
                searchData[input.name] = input.value;
            } else if ((input.type === 'text' || input.type === 'date' || input.type === 'number') && input.value) {
                params.append(input.name, input.value);
                searchData[input.name] = input.value;
            }
        });

        // Save to LocalStorage if enabled
        if (saveToggle && saveToggle.checked) {
            storage.set('m3-saved-search', searchData);
        } else {
            storage.remove('m3-saved-search');
        }

        setTimeout(() => { window.location.href = `${m3_ajax.home_url}?${params.toString()}`; }, 600);
    });

    // --- Reset ---
    modalReset?.addEventListener('click', () => {
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'checkbox') input.checked = false;
            else if (input.type === 'radio') input.checked = (input.value === 'all' || input.value === 'date');
            else if (input.tagName === 'SELECT') input.selectedIndex = 0;
            else if (input.id === 'm3-min-chars') input.value = 0;
            else if (input.id === 'm3-max-chars') input.value = 10000;
            else input.value = '';
        });

        // Also clear saved search
        storage.remove('m3-saved-search');
        const saveToggle = document.getElementById('m3-save-search-settings');
        if (saveToggle) saveToggle.checked = false;

        initRangeSlider();
        updateHitCount();
        updateTabStatus();
    });
}

function initDrawer() {
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');
    if (menuBtn && drawer && scrim) {
        const toggle = (open) => {
            drawer.classList.toggle('is-active', open);
            scrim.classList.toggle('is-active', open);
            document.body.style.overflow = open ? 'hidden' : '';
        };
        menuBtn.addEventListener('click', () => toggle(true));
        scrim.addEventListener('click', () => toggle(false));
    }
}

function initViewSwitcher() {
    const btn = document.getElementById('m3-view-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const current = storage.get('view-mode') || 'pc';
        const next = current === 'pc' ? 'mobile' : 'pc';
        storage.set('view-mode', next);
        location.reload();
    });
}

function initCommentForm() {
    const commentForm = document.getElementById('commentform');
    if (commentForm) {
        const submitBtn = commentForm.querySelector('.m3-comment-submit-btn');
        const requiredFields = commentForm.querySelectorAll('[required]');
        
        // 1. Validation Logic
        const checkValidity = () => {
            let isValid = true;
            requiredFields.forEach(field => {
                if (!field.value.trim()) isValid = false;
            });
            
            if (isValid) {
                submitBtn.removeAttribute('disabled');
                submitBtn.classList.add('is-ready');
            } else {
                submitBtn.setAttribute('disabled', 'disabled');
                submitBtn.classList.remove('is-ready');
            }
        };

        // Input listeners for real-time validation
        requiredFields.forEach(field => {
            field.addEventListener('input', checkValidity);
        });

        // 2. Submit Logic
        commentForm.addEventListener('submit', (e) => {
            if (submitBtn) {
                submitBtn.classList.add('is-submitting');
                submitBtn.innerHTML = '送信中...<span class="material-symbols-outlined">schedule</span>';
                submitBtn.style.backgroundColor = '#2196F3';
                submitBtn.style.pointerEvents = 'none';
            }
        });

        // Initial check
        checkValidity();
    }
}

function initOverdriveScroll() {
    // Scroll behavior handled via GSAP in scripts/card-animation.js
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.getElementById('search-toggle')?.click();
        }
    });
}

function initTooltips() {
    // Tooltips handled via CSS and small JS in main.js
}

function initRippleEffect() {
    document.querySelectorAll('.m3-button, .m3-fab, .m3-icon-button').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const x = e.clientX - e.target.offsetLeft;
            const y = e.clientY - e.target.offsetTop;
            const ripple = document.createElement('span');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

function animateSearchClear(input, button, callback) {
    if (typeof gsap === 'undefined') return;

    // 1. Button Animation (Subtle rotation)
    gsap.to(button, {
        rotation: 90,
        duration: 0.15,
        ease: "power2.inOut",
        onComplete: () => {
            gsap.set(button, { rotation: 0 });
        }
    });

    // 2. Simple Text Fade Animation
    gsap.to(input, {
        opacity: 0,
        x: -5,
        duration: 0.1,
        ease: "power2.in",
        onComplete: () => {
            input.value = '';
            if (callback) callback();
            gsap.to(input, {
                opacity: 1,
                x: 0,
                duration: 0.15,
                delay: 0.05,
                ease: "power2.out"
            });
        }
    });
}

function initTableSorter() {
    const tables = document.querySelectorAll('.wp-block-table.is-sortable table, .wp-block-table.is-style-sortable table, .m3-table--sortable table');

    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const isAscending = header.classList.contains('is-asc');

                // Clear header classes
                headers.forEach(h => h.classList.remove('is-asc', 'is-desc'));

                rows.sort((a, b) => {
                    const aText = a.children[index]?.textContent.trim() || '';
                    const bText = b.children[index]?.textContent.trim() || '';

                    const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                    const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAscending ? bNum - aNum : aNum - bNum;
                    }
                    return isAscending ? bText.localeCompare(aText, 'ja') : aText.localeCompare(bText, 'ja');
                });

                header.classList.add(isAscending ? 'is-desc' : 'is-asc');
                while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
                tbody.append(...rows);
            });
        });
    });
}


window.__vite_ae_ce_fix = "ae,ce";
