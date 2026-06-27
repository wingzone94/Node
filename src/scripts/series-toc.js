const initSeriesChartToggle = () => {
    document.querySelectorAll('.m3-series-toc__toggle').forEach((toggle) => {
        const panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) return;

        panel.style.maxHeight = '0px';

        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            const nextOpen = !isOpen;

            toggle.setAttribute('aria-expanded', String(nextOpen));
            panel.classList.toggle('is-open', nextOpen);
            panel.style.maxHeight = nextOpen ? `${panel.scrollHeight}px` : '0px';
        });
    });
};

const initSeriesInfoToggle = () => {
    document.querySelectorAll('[data-series-info-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
        });
    });
};

export function initSeriesToc() {
    initSeriesChartToggle();
    initSeriesInfoToggle();
}
