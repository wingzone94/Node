export function initTableSorter() {
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
