export function initOverdriveScroll() {
    // Scroll behavior is handled via GSAP in scripts/card-animation.js.
}

export function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.getElementById('search-toggle')?.click();
        }
    });
}

export function initTooltips() {
    // Tooltips are handled via CSS.
}

export function initRippleEffect() {
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
