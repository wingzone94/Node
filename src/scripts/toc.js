export function initTableOfContents() {
    const container = document.getElementById('m3-toc-container');
    const trigger = document.getElementById('m3-toc-trigger');
    const tocCard = document.getElementById('m3-sticky-toc');
    const closeBtn = document.getElementById('m3-toc-close');
    const body = document.querySelector('.m3-article__body');

    if (!container || !body) return;

    // Generate TOC
    const headings = body.querySelectorAll('h2, h3');
    if (headings.length === 0) {
        if (trigger) trigger.style.display = 'none';
        return;
    }

    container.innerHTML = ''; // Clear existing
    const list = document.createElement('ul');
    list.className = 'm3-toc-list';

    headings.forEach((heading, index) => {
        const id = heading.id || `toc-heading-${index}`;
        heading.id = id;

        const li = document.createElement('li');
        li.className = `m3-toc-item m3-toc-item--${heading.tagName.toLowerCase()}`;
        
        const a = document.createElement('a');
        a.href = `#${id}`;
        a.textContent = heading.textContent;
        a.addEventListener('click', (e) => {
            e.preventDefault();
            if (tocCard) tocCard.classList.remove('is-active');
            if (typeof gsap !== 'undefined' && gsap.plugins.scrollTo) {
                gsap.to(window, { duration: 0.8, scrollTo: { y: heading, offsetY: 100 }, ease: "power3.inOut" });
            } else {
                window.scrollTo({ top: heading.offsetTop - 100, behavior: 'smooth' });
            }
        });

        li.appendChild(a);
        list.appendChild(li);
    });

    container.appendChild(list);

    // Toggle TOC
    if (trigger && tocCard) {
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            tocCard.classList.toggle('is-active');
        });
    }

    if (closeBtn && tocCard) {
        closeBtn.addEventListener('click', () => {
            tocCard.classList.remove('is-active');
        });
    }

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (tocCard && tocCard.classList.contains('is-active') && !tocCard.contains(e.target) && !trigger.contains(e.target)) {
            tocCard.classList.remove('is-active');
        }
    });
}
