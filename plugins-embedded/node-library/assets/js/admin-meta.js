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

    var generateBtn = document.getElementById('node-library-generate-info');
    var generateStatus = document.getElementById('node-library-generate-status');
    var summaryField = document.querySelector('textarea[name="node_library_summary"]');
    var titleField = document.getElementById('title');
	var typeField = document.getElementById('node-library-type');

    if (generateBtn && generateStatus && summaryField && titleField && window.wp && window.wp.apiFetch) {
        generateBtn.addEventListener('click', function () {
            var title = titleField.value.trim();
			var linkRows = container ? Array.prototype.slice.call(container.querySelectorAll('.link-row')) : [];
			var hasLinks = linkRows.some(function (row) {
				var platform = row.querySelector('input[name*="[platform]"]');
				var url = row.querySelector('input[name*="[url]"]');
				return (platform && platform.value.trim()) || (url && url.value.trim());
			});
            if (!title) {
                generateStatus.textContent = '先にタイトルを入力してください。';
                titleField.focus();
                return;
            }

			if ((summaryField.value.trim() || hasLinks) && !window.confirm('現在の紹介文とストアリンクを取得結果で置き換えますか？')) {
                return;
            }

            generateBtn.disabled = true;
            generateStatus.textContent = '取得中…';

            window.wp.apiFetch({
                path: (window.nodeLibraryAdmin && window.nodeLibraryAdmin.generatePath) || '/node-library/v1/generate-game-info',
                method: 'POST',
				data: {
					title: title,
					type: typeField ? typeField.value : 'game'
				}
            }).then(function (response) {
                summaryField.value = response.summary || '';
                summaryField.dispatchEvent(new Event('input', { bubbles: true }));

				var validCategories = ['auto', 'pc', 'mobile', 'console'];
				var validHardware = [
					'auto',
					'windows-pc',
					'mac',
					'iphone-ipad',
					'android',
					'amazon-fire',
					'nintendo-switch',
					'nintendo-switch-2',
					'playstation-4',
					'playstation-5',
					'xbox-one',
					'xbox-series'
				];
				linkRows.forEach(function (row, index) {
					var link = response.links && response.links[index] ? response.links[index] : null;
					var platform = row.querySelector('input[name*="[platform]"]');
					var url = row.querySelector('input[name*="[url]"]');
					var category = row.querySelector('select[name*="[category]"]');
					var hardware = row.querySelector('select[name*="[hardware]"]');
					if (platform) platform.value = link ? link.platform : '';
					if (url) url.value = link ? link.url : '';
					if (category) {
						var cat = link && validCategories.indexOf(link.category) !== -1 ? link.category : 'auto';
						category.value = cat;
					}
					if (hardware) {
						var device = link && validHardware.indexOf(link.hardware) !== -1 ? link.hardware : 'auto';
						hardware.value = device;
					}

					var shouldShow = index < Math.max(2, response.links ? response.links.length : 0);
					row.classList.toggle('is-hidden', !shouldShow);
				});

				if (addBtn && container) {
					addBtn.disabled = container.querySelectorAll('.link-row.is-hidden').length === 0;
				}

				var linkCount = response.links ? response.links.length : 0;
				generateStatus.textContent = linkCount > 0
					? '紹介文とストアリンク' + linkCount + '件を反映しました。'
					: '紹介文を反映しました。公式ストアページは見つかりませんでした。';
            }).catch(function (error) {
                generateStatus.textContent = error && error.message ? error.message : 'ゲーム（アプリ）情報の取得に失敗しました。';
            }).finally(function () {
                generateBtn.disabled = false;
            });
        });
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
