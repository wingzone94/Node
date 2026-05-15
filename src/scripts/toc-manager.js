/**
 * TOC Manager
 * Handles both floating TOC (mobile) and sidebar TOC (desktop).
 */

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

    if (!article || (!sidebarContainer && !floatingContainer)) return;

    const headings = Array.from(article.querySelectorAll('h1, h2, h3, h4, h5, h6'))
        .filter((heading) => heading.textContent.trim().length > 0);

    if (headings.length === 0) {
        sidebar?.remove();
        if (floatingTOC) floatingTOC.remove();
        if (fabTrigger) fabTrigger.style.display = 'none';
        if (handyTrigger) handyTrigger.style.display = 'none';
        return;
    }

    if (fabTrigger) {
        fabTrigger.style.display = 'flex';
        fabTrigger.classList.add('toc-ready');
    }
    if (handyTrigger) handyTrigger.style.display = '';

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

    // --- 1. Content Generation ---
    const generateTOCList = (container, isSidebar = false) => {
        container.innerHTML = '';
        const ul = document.createElement('ul');
        ul.className = isSidebar ? 'm3-toc-sidebar__list' : 'm3-toc-list';

        headings.forEach((heading) => {
            const li = document.createElement('li');
            const level = heading.tagName.toLowerCase();
            li.className = isSidebar ? `m3-toc-sidebar__item m3-toc-sidebar__item--${level}` : `m3-toc-item m3-toc-item--${level}`;

            const a = document.createElement('a');
            a.href = `#${heading.id}`;
            a.className = isSidebar ? 'm3-toc-sidebar__link' : 'm3-toc-link';
            a.textContent = heading.innerText || heading.textContent;

            a.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.getElementById(heading.id);
                if (target) {
                    const offset = 100;
                    const targetPos = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: targetPos, behavior: 'smooth' });

                    floatingTOC?.classList.remove('is-active');
                    document.body.classList.remove('is-active-toc');
                }
            });

            li.appendChild(a);
            ul.appendChild(li);
        });
        container.appendChild(ul);
    };

    if (sidebarContainer) generateTOCList(sidebarContainer, true);
    if (floatingContainer) generateTOCList(floatingContainer, false);

    // --- 2. ScrollSpy (Active Highlighting) ---
    const updateActiveHeading = () => {
        const offset = 130;
        let activeHeading = headings[0];

        headings.forEach((heading) => {
            if (heading.getBoundingClientRect().top <= offset) {
                activeHeading = heading;
            }
        });

        const activeHref = `#${activeHeading.id}`;
        document.querySelectorAll('.m3-toc-sidebar__link, .m3-toc-link').forEach((link) => {
            link.classList.toggle('is-active', link.getAttribute('href') === activeHref);
        });
    };

    // --- 3. Reading Progress ---
    const updateProgress = () => {
        const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
        if (progressBar) progressBar.style.width = `${scrolled}%`;
        updateActiveHeading();
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    updateProgress();

    // --- 4. Floating TOC Toggle ---
    const toggleTOC = (e) => {
        if (e) e.preventDefault();
        if (!floatingTOC) return;

        floatingTOC.classList.toggle('is-active');
        document.body.classList.toggle('is-active-toc', floatingTOC.classList.contains('is-active'));
    };

    if (fabTrigger) fabTrigger.addEventListener('click', toggleTOC);
    document.addEventListener('m3:toc:toggle', toggleTOC);

    if (closeBtn && floatingTOC) {
        closeBtn.addEventListener('click', () => {
            floatingTOC.classList.remove('is-active');
            document.body.classList.remove('is-active-toc');
        });
    }
}
