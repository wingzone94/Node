(function () {
    var addBtn = document.getElementById('node-library-add-link');
    var container = document.querySelector('#node-library-links-editor .links-container');

    if (addBtn && container) {
        addBtn.addEventListener('click', function () {
            var hidden = container.querySelectorAll('.link-row.is-hidden');
            if (hidden.length > 0) {
                hidden[0].classList.remove('is-hidden');
            }
            if (container.querySelectorAll('.link-row.is-hidden').length === 0) {
                addBtn.disabled = true;
                addBtn.textContent = 'リンク行の上限に達しました';
            }
        });

        if (container.querySelectorAll('.link-row.is-hidden').length === 0) {
            addBtn.disabled = true;
        }
    }

    var select = document.getElementById('node-linked-library-select');
    var preview = document.getElementById('node-library-metabox-preview');
    var previewText = document.getElementById('node-library-metabox-preview-text');

    if (select && preview && previewText) {
        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            if (!select.value) {
                preview.style.display = 'none';
                return;
            }
            var summary = option.getAttribute('data-summary') || '（紹介文なし）';
            previewText.textContent = summary || '（紹介文なし）';
            preview.style.display = '';
        });
    }
})();
