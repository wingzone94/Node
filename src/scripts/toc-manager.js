/**
 * TOC Manager
 * Handles sidebar TOC (desktop) and floating TOC (mobile/FAB).
 * Scroll-spy and progress follow v0.8.x behavior, with sidebar link auto-scroll.
 */

const SCROLL_OFFSET = 120;
const DESKTOP_SIDEBAR_MQ = '(min-width: 1101px)';
const SIDEBAR_COLLAPSED_KEY = 'm3-toc-sidebar-collapsed';

function isDesktopSidebar() {
    return window.matchMedia(DESKTOP_SIDEBAR_MQ).matches;
}

function getScrollOffset() {
    const header = document.querySelector('.m3-header');
    if (!header) return SCROLL_OFFSET;
    return Math.max(SCROLL_OFFSET, header.getBoundingClientRect().height + 16);
}

export function initTOCManager() {
    const sidebarContainer = document.getElementById('m3-toc-sidebar-content');
    const floatingContainer = document.getElementById('m3-toc-container');
    const article = document.querySelector('.m3-article__body');
    const progressBar = document.getElementById('m3-toc-progress-bar');
    const sidebar = document.querySelector('.m3-toc-sidebar');
    const floatingTOC = document.getElementById('m3-sticky-toc');
    const fabTrigger = document.getElementById('m3-toc-trigger');
    const handyTrigger = document.getElementById('m3-handy-toc-trigger');
    const closeBtn = document.getElementById('m3-toc-close');
    const actionStack = document.querySelector('.m3-action-stack');

    if (!article || (!sidebarContainer && !floatingContainer)) return;

    const headings = Array.from(article.querySelectorAll('h1, h2, h3, h4, h5, h6'))
        .filter((heading) => heading.textContent.trim().length > 0);

    if (headings.length === 0) {
        sidebar?.remove();
        floatingTOC?.remove();
        if (fabTrigger) fabTrigger.style.display = 'none';
        if (handyTrigger) handyTrigger.style.display = 'none';
        return;
    }

    const updateMobileTocTriggers = () => {
        const useMobileToc = !isDesktopSidebar();
        if (fabTrigger) {
            fabTrigger.hidden = !useMobileToc;
            fabTrigger.style.display = useMobileToc ? 'flex' : 'none';
            fabTrigger.classList.toggle('toc-ready', useMobileToc);
        }
        if (handyTrigger) {
            handyTrigger.hidden = !useMobileToc;
            handyTrigger.style.display = useMobileToc ? '' : 'none';
        }
    };

    updateMobileTocTriggers();
    window.matchMedia(DESKTOP_SIDEBAR_MQ).addEventListener('change', updateMobileTocTriggers);

    actionStack?.classList.add('is-has-toc');

    const headingSet = new Set(headings);
    const usedIds = new Set(
        Array.from(document.querySelectorAll('[id]'))
            .filter((element) => !headingSet.has(element))
            .map((element) => element.id)
            .filter(Boolean)
    );

    const createHeadingId = (heading, index) => {
        const baseId = heading.id || `m3-h-${index + 1}`;
        let id = baseId;
        let suffix = 2;

        while (usedIds.has(id)) {
            id = `${baseId}-${suffix}`;
            suffix += 1;
        }

        heading.id = id;
        usedIds.add(id);
        return id;
    };

    headings.forEach((heading, index) => createHeadingId(heading, index));

    const scrollToHeading = (heading) => {
        const offset = getScrollOffset();
        const targetPos = heading.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top: targetPos, behavior: 'smooth' });
    };

    const closeTOC = () => {
        floatingTOC?.classList.remove('is-active');
        document.body.classList.remove('is-active-toc');
    };

    const generateTOCList = (container, isSidebar = false) => {
        container.innerHTML = '';
        const ul = document.createElement('ul');
        ul.className = isSidebar ? 'm3-toc-sidebar__list' : 'm3-toc-list';

        headings.forEach((heading) => {
            const li = document.createElement('li');
            const level = heading.tagName.toLowerCase();
            li.className = isSidebar
                ? `m3-toc-sidebar__item m3-toc-sidebar__item--${level}`
                : `m3-toc-item m3-toc-item--${level}`;

            const a = document.createElement('a');
            a.href = `#${heading.id}`;
            a.className = isSidebar ? 'm3-toc-sidebar__link' : 'm3-toc-link';
            a.textContent = heading.innerText || heading.textContent;

            a.addEventListener('click', (e) => {
                e.preventDefault();
                scrollToHeading(heading);
                closeTOC();
                history.replaceState(null, '', `#${heading.id}`);
            });

            li.appendChild(a);
            ul.appendChild(li);
        });
        container.appendChild(ul);
    };

    if (sidebarContainer) generateTOCList(sidebarContainer, true);
    if (floatingContainer) generateTOCList(floatingContainer, false);

    let lastActiveId = null;

    const updateActiveHeading = () => {
        const offset = getScrollOffset();
        const scrollPos = window.scrollY + offset;
        let activeHeading = headings[0];

        headings.forEach((heading) => {
            if (scrollPos >= heading.offsetTop) {
                activeHeading = heading;
            }
        });

        const nearBottom =
            window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 48;
        if (nearBottom) {
            activeHeading = headings[headings.length - 1];
        }

        const activeId = activeHeading.id;
        const activeHref = `#${activeId}`;

        document.querySelectorAll('.m3-toc-sidebar__link, .m3-toc-link').forEach((link) => {
            link.classList.toggle('is-active', link.getAttribute('href') === activeHref);
        });

        if (activeId !== lastActiveId) {
            lastActiveId = activeId;
            if (sidebarContainer) {
                const activeLink = sidebarContainer.querySelector('.m3-toc-sidebar__link.is-active');
                activeLink?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    };

    const updateProgress = () => {
        const scrollY = window.scrollY || window.pageYOffset;
        const rect = article.getBoundingClientRect();
        const articleTop = rect.top + scrollY;
        const articleHeight = rect.height;
        const windowHeight = window.innerHeight;
        const headerHeight = getScrollOffset();
        const scrollStart = Math.max(0, articleTop - headerHeight);

        let progress = 0;
        if (scrollY > scrollStart) {
            const scrollDistance = scrollY - scrollStart;
            const scrollableHeight = articleHeight - windowHeight + headerHeight;
            progress = (scrollDistance / Math.max(1, scrollableHeight)) * 100;
        }

        progress = Math.min(100, Math.max(0, progress));
        if (progressBar) progressBar.style.width = `${progress}%`;
        updateActiveHeading();
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    updateProgress();

    const toggleTOC = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (!floatingTOC) return;

        const isActive = floatingTOC.classList.toggle('is-active');
        document.body.classList.toggle('is-active-toc', isActive);
        if (isActive) updateActiveHeading();
    };

    if (fabTrigger) fabTrigger.addEventListener('click', toggleTOC);
    document.addEventListener('m3:toc:toggle', toggleTOC);

    if (closeBtn) closeBtn.addEventListener('click', closeTOC);

    document.addEventListener('click', (e) => {
        if (!floatingTOC?.classList.contains('is-active')) return;
        const isInside = floatingTOC.contains(e.target);
        const isTrigger =
            (fabTrigger && fabTrigger.contains(e.target)) ||
            (handyTrigger && handyTrigger.contains(e.target));
        if (!isInside && !isTrigger) closeTOC();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && floatingTOC?.classList.contains('is-active')) {
            closeTOC();
        }
    });

    const scrollToHashTarget = () => {
        const hash = window.location.hash;
        if (!hash || hash.length < 2) return;
        const target = document.getElementById(decodeURIComponent(hash.slice(1)));
        if (target && headings.includes(target)) {
            requestAnimationFrame(() => scrollToHeading(target));
        }
    };

    window.addEventListener('hashchange', scrollToHashTarget);
    scrollToHashTarget();

    initSidebarCollapse(sidebar);
}

function initSidebarCollapse(sidebar) {
    const toggle = document.getElementById('m3-toc-sidebar-toggle');
    if (!sidebar || !toggle) return;

    const icon = toggle.querySelector('.material-symbols-outlined');

    const setCollapsed = (collapsed, persist = true) => {
        if (!isDesktopSidebar()) {
            sidebar.classList.remove('is-collapsed');
            toggle.setAttribute('aria-expanded', 'true');
            if (icon) icon.textContent = 'expand_less';
            toggle.setAttribute('aria-label', '目次を折りたたむ');
            return;
        }

        sidebar.classList.toggle('is-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
        if (icon) icon.textContent = collapsed ? 'expand_more' : 'expand_less';
        toggle.setAttribute('aria-label', collapsed ? '目次を展開' : '目次を折りたたむ');

        if (persist) {
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
        }
    };

    const savedCollapsed = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    setCollapsed(savedCollapsed, false);

    toggle.addEventListener('click', () => {
        setCollapsed(!sidebar.classList.contains('is-collapsed'));
    });

    window.matchMedia(DESKTOP_SIDEBAR_MQ).addEventListener('change', () => {
        if (isDesktopSidebar()) {
            setCollapsed(localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1', false);
        } else {
            setCollapsed(false, false);
        }
    });
}
