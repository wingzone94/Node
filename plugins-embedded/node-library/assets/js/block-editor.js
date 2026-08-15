(function (blocks, element, components, data, apiFetch, blockEditor) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var CheckboxControl = components.CheckboxControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var Spinner = components.Spinner;
    var Placeholder = components.Placeholder;
    var Button = components.Button;
    var useSelect = data.useSelect;

    var editorConfig = window.nodeLibraryEditor || {};
    var adminNewUrl = editorConfig.adminNewUrl || '/wp-admin/post-new.php?post_type=node_library';
    var adminListUrl = editorConfig.adminListUrl || '/wp-admin/edit.php?post_type=node_library';

    if (blocks.registerBlockCategory) {
        blocks.registerBlockCategory('node', {
            title: 'Node',
            icon: 'database-add'
        });
    }

    function adminLinks() {
        return el('div', { style: { marginTop: '12px', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
            el(Button, { isSecondary: true, href: adminListUrl, target: '_blank' }, 'ライブラリ一覧'),
            el(Button, { isSecondary: true, href: adminNewUrl, target: '_blank' }, '新規項目を追加')
        );
    }

    function libraryPreview(item) {
        if (!item) return null;
        return el('div', {
            style: {
                marginTop: '14px',
                padding: '12px',
                background: '#f6f7f7',
                border: '1px solid #dcdcde',
                borderRadius: '10px'
            }
        },
            el('div', { style: { fontWeight: '700', marginBottom: '6px' } }, item.title),
            item.summary
                ? el('div', { style: { fontSize: '12px', color: '#50575e', lineHeight: '1.5' } }, item.summary)
                : el('div', { style: { fontSize: '12px', color: '#8c8f94' } }, '（紹介文なし）'),
            item.link_count > 0
                ? el('div', { style: { fontSize: '11px', color: '#8c8f94', marginTop: '6px' } }, 'リンク: ' + item.link_count + '件')
                : el('div', { style: { fontSize: '11px', color: '#d63638', marginTop: '6px' } }, 'ストアリンク未設定')
        );
    }

    // --- ライブラリカード ---
    registerBlockType('node-library/item-card', {
        title: 'ライブラリカード',
        description: 'Node Library に登録したゲーム・アプリ情報を本文に表示します。',
        icon: 'database-add',
        category: 'node',
        keywords: ['node', 'library', 'game', 'app', 'ライブラリ', 'ゲーム'],
        attributes: { libraryId: { type: 'number', default: 0 } },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var itemsState = element.useState([]);
            var allItems = itemsState[0];
            var setAllItems = itemsState[1];
            var loadingState = element.useState(true);
            var isLoading = loadingState[0];
            var setIsLoading = loadingState[1];
            var filterState = element.useState('');
            var filter = filterState[0];
            var setFilter = filterState[1];

            element.useEffect(function () {
                setIsLoading(true);
                apiFetch({ path: '/node-library/v1/items' })
                    .then(function (items) {
                        setAllItems(items || []);
                        setIsLoading(false);
                    })
                    .catch(function () {
                        setAllItems([]);
                        setIsLoading(false);
                    });
            }, []);

            var filtered = allItems.filter(function (item) {
                if (!filter) return true;
                return item.title.toLowerCase().indexOf(filter.toLowerCase()) !== -1;
            });

            var options = [{ label: '— 選択してください —', value: '0' }].concat(
                filtered.map(function (item) {
                    return { label: item.title, value: String(item.id) };
                })
            );

            var selectedItem = allItems.find(function (item) {
                return item.id === attributes.libraryId;
            }) || null;

            return el('div', { className: 'node-library-block-editor' },
                el(Placeholder, {
                    icon: 'database-add',
                    label: 'ライブラリカード',
                    instructions: '登録済みのゲーム・アプリを選ぶと、ストアリンク付きカードを表示します。'
                },
                    isLoading && el(Spinner),
                    !isLoading && allItems.length === 0 && el('p', { style: { margin: '0 0 12px' } },
                        'ライブラリ項目がありません。先に Node Library メニューから登録してください。'
                    ),
                    !isLoading && allItems.length > 0 && el(TextControl, {
                        label: 'タイトルで絞り込み（任意）',
                        value: filter,
                        placeholder: '例: Minecraft',
                        onChange: setFilter
                    }),
                    !isLoading && allItems.length > 0 && el(SelectControl, {
                        label: 'ライブラリ項目',
                        value: String(attributes.libraryId || 0),
                        options: options,
                        onChange: function (id) {
                            setAttributes({ libraryId: parseInt(id, 10) || 0 });
                        }
                    }),
                    libraryPreview(selectedItem),
                    adminLinks()
                )
            );
        },
        save: function () { return null; }
    });

    // --- ブログカード ---
    registerBlockType('node-library/blog-card', {
        title: 'ブログカード',
        description: 'URL から OGP を取得して記事カードを表示します。',
        icon: 'admin-links',
        category: 'node',
        keywords: ['blog', 'card', 'url', 'ogp', 'ブログカード', 'リンク'],
        attributes: { url: { type: 'string', default: '' } },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return el('div', { className: 'node-library-block-editor' },
                el(Placeholder, {
                    icon: 'admin-links',
                    label: 'ブログカード',
                    instructions: '表示したい記事やページの URL を入力してください。'
                },
                    el(TextControl, {
                        label: '記事 URL',
                        value: attributes.url,
                        placeholder: 'https://luminous-core.net/...',
                        onChange: function (val) { setAttributes({ url: val }); }
                    }),
                    el('p', { style: { fontSize: '12px', color: '#646970', margin: '8px 0 0' } },
                        'ヒント: URL だけの行を本文に書くと、保存後に自動でブログカード化されます（ショートコード不要）。'
                    ),
                    attributes.url
                        ? el('div', {
                            style: {
                                marginTop: '12px',
                                padding: '10px 12px',
                                background: '#f0f6fc',
                                borderRadius: '8px',
                                fontSize: '12px',
                                wordBreak: 'break-all'
                            }
                        }, 'プレビュー URL: ', attributes.url)
                        : null
                )
            );
        },
        save: function () { return null; }
    });

    // --- 商品リンクカード ---
    registerBlockType('node-library/product-card', {
        title: '商品リンクカード',
        description: 'Amazon / 楽天の URL から商品情報を取得してカード表示します。',
        icon: 'cart',
        category: 'node',
        keywords: ['product', 'amazon', 'rakuten', '商品', 'アフィリエイト'],
        attributes: {
            title: { type: 'string', default: '' },
            price: { type: 'string', default: '' },
            imageUrl: { type: 'string', default: '' },
            amazonUrl: { type: 'string', default: '' },
            asin: { type: 'string', default: '' },
            rakutenUrl: { type: 'string', default: '' },
            showAmazonDisclosure: { type: 'boolean', default: true },
            showRakutenDisclosure: { type: 'boolean', default: true }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var urlState = element.useState('');
            var urlInput = urlState[0];
            var setUrlInput = urlState[1];
            var fetchState = element.useState(false);
            var isFetching = fetchState[0];
            var setIsFetching = fetchState[1];

            var fetchProductInfo = function () {
                if (!urlInput) return;
                setIsFetching(true);
                apiFetch({ path: '/node-library/v1/fetch-ogp?url=' + encodeURIComponent(urlInput) })
                    .then(function (data) {
                        var cleanTitle = (data.title || '').replace(/^Amazon\s*\|\s*/i, '').replace(/[\s|]+Amazon\.co\.jp$/i, '');
                        setAttributes({ title: cleanTitle, imageUrl: data.image || '' });
                        if (data.asin) {
                            setAttributes({ asin: data.asin, showAmazonDisclosure: true });
                        } else if (urlInput.indexOf('rakuten.co.jp') !== -1) {
                            setAttributes({ rakutenUrl: urlInput, showRakutenDisclosure: true });
                        }
                        setIsFetching(false);
                        setUrlInput('');
                    })
                    .catch(function (err) {
                        setIsFetching(false);
                        var msg = (err && err.message) ? err.message : '情報の取得に失敗しました。';
                        window.alert('エラー: ' + msg);
                    });
            };

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'ディスクロージャー設定', initialOpen: true },
                        el(CheckboxControl, {
                            label: 'Amazon 用の注釈を表示',
                            checked: attributes.showAmazonDisclosure,
                            onChange: function (val) { setAttributes({ showAmazonDisclosure: val }); }
                        }),
                        el(CheckboxControl, {
                            label: '楽天用の注釈を表示',
                            checked: attributes.showRakutenDisclosure,
                            onChange: function (val) { setAttributes({ showRakutenDisclosure: val }); }
                        })
                    )
                ),
                el('div', { className: 'node-library-block-editor', key: 'editor' },
                    el(Placeholder, {
                        icon: 'cart',
                        label: '商品リンクカード',
                        instructions: 'Amazon や楽天の商品 URL を貼り付けて「情報を取得」を押してください。'
                    },
                        el('div', { style: { width: '100%', display: 'flex', gap: '10px', marginBottom: '16px', alignItems: 'flex-end' } },
                            el(TextControl, {
                                label: '商品 URL',
                                value: urlInput,
                                placeholder: 'https://www.amazon.co.jp/...',
                                onChange: setUrlInput,
                                style: { flex: 1, marginBottom: 0 }
                            }),
                            el(Button, {
                                isPrimary: true,
                                onClick: fetchProductInfo,
                                disabled: isFetching || !urlInput
                            }, isFetching ? el(Spinner) : '情報を取得')
                        ),
                        attributes.title || attributes.imageUrl
                            ? el('div', {
                                style: {
                                    marginBottom: '16px',
                                    padding: '10px 12px',
                                    background: '#f6f7f7',
                                    borderRadius: '8px',
                                    border: '1px solid #dcdcde'
                                }
                            },
                                attributes.imageUrl
                                    ? el('img', {
                                        src: attributes.imageUrl,
                                        alt: '',
                                        style: { maxWidth: '120px', maxHeight: '80px', objectFit: 'cover', borderRadius: '6px', marginBottom: '8px', display: 'block' }
                                    })
                                    : null,
                                attributes.title
                                    ? el('div', { style: { fontWeight: '700', fontSize: '13px' } }, attributes.title)
                                    : null
                            )
                            : null,
                        el('div', { style: { width: '100%', borderTop: '1px solid #ddd', paddingTop: '14px' } },
                            el(TextControl, { label: '商品名', value: attributes.title, onChange: function (val) { setAttributes({ title: val }); } }),
                            el(TextControl, { label: '参考価格', value: attributes.price, placeholder: '￥2,980', onChange: function (val) { setAttributes({ price: val }); } }),
                            el(TextControl, { label: '画像 URL', value: attributes.imageUrl, onChange: function (val) { setAttributes({ imageUrl: val }); } }),
                            el(TextControl, { label: 'Amazon ASIN', value: attributes.asin, placeholder: 'B0XXXXXXXX', onChange: function (val) { setAttributes({ asin: val }); } }),
                            el(TextControl, { label: '楽天 URL', value: attributes.rakutenUrl, onChange: function (val) { setAttributes({ rakutenUrl: val }); } })
                        )
                    )
                )
            ];
        },
        save: function () { return null; }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.data,
    window.wp.apiFetch,
    window.wp.blockEditor
);
