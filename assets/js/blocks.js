/**
 * Luminous Core v0.4 - Hegemony Interactions (Vanilla JS & GSAP)
 */

document.addEventListener('DOMContentLoaded', () => {
    initSmartSortTables();
    initMediaLabels();
    initVotingParticles();
});

const getGsap = () => (typeof window !== 'undefined' && window.gsap ? window.gsap : null);

const setStyles = (element, styles) => {
    Object.entries(styles).forEach(([property, value]) => {
        element.style[property] = value;
    });
};

/**
 * 1. Smart Sort Table (Lightweight Vanilla Logic)
 */
function initSmartSortTables() {
    const tables = document.querySelectorAll('.m3-sort-table, .m3-sortable-table');
    
    tables.forEach(table => {
        if (table.dataset.sortEnabled === 'false') return;
        
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.addEventListener('click', () => {
                const isAsc = header.classList.contains('sort-asc');
                sortTable(table, index, !isAsc);
                
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc', 'is-sorting'));
                header.classList.add(isAsc ? 'sort-desc' : 'sort-asc', 'is-sorting');
            });
        });
    });
}

function sortTable(table, colIndex, asc) {
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    
    const sortedRows = rows.sort((a, b) => {
        const valA = a.cells[colIndex].textContent.trim();
        const valB = b.cells[colIndex].textContent.trim();
        
        // Auto-detect numeric
        const numA = parseFloat(valA.replace(/[^0-9.-]/g, ''));
        const numB = parseFloat(valB.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(numA) && !isNaN(numB)) {
            return asc ? numA - numB : numB - numA;
        }
        
        return asc ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });
    
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    tbody.append(...sortedRows);
}

/**
 * 2. Checkbox-Linked Media Labels (GSAP)
 */
function initMediaLabels() {
    const containers = document.querySelectorAll('.m3-media-container');
    
    containers.forEach(container => {
        const checkbox = container.querySelector('.m3-media-checkbox');
        const label = container.querySelector('.m3-media-label');
        
        if (!checkbox || !label) return;

        checkbox.addEventListener('change', () => {
            const gsap = getGsap();

            if (checkbox.checked) {
                if (gsap) {
                    gsap.to(label, {
                        opacity: 1,
                        scale: 1,
                        y: -10,
                        duration: 0.5,
                        ease: "back.out(1.7)"
                    });
                    return;
                }

                setStyles(label, {
                    opacity: '1',
                    transform: 'translateY(-10px) scale(1)'
                });
            } else {
                if (gsap) {
                    gsap.to(label, {
                        opacity: 0,
                        scale: 0.8,
                        y: 0,
                        duration: 0.3,
                        ease: "power2.in"
                    });
                    return;
                }

                setStyles(label, {
                    opacity: '0',
                    transform: 'translateY(0) scale(0.8)'
                });
            }
        });
    });
}

/**
 * 3. Voting Particles & Results (Canvas)
 */
function initVotingParticles() {
    // Previous particle logic remains, but results display is updated
    const votingCards = document.querySelectorAll('.m3-voting-card');
    
    votingCards.forEach(card => {
        const buttons = card.querySelectorAll('.m3-voting-button');
        buttons.forEach(button => {
            button.addEventListener('click', () => {
                const gsap = getGsap();
                const results = card.querySelectorAll('.m3-voting-result-container');

                results.forEach(res => {
                    res.style.display = 'flex';
                    if (gsap) {
                        gsap.from(res, { opacity: 0, y: 10, duration: 0.4 });
                        return;
                    }

                    setStyles(res, {
                        opacity: '1',
                        transform: 'translateY(0)'
                    });
                });
            });
        });
    });
}
