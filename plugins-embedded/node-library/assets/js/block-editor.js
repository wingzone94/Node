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

    function ensureBlogCardPreviewStyle() {
        if (document.getElementById('node-library-blog-card-preview-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'node-library-blog-card-preview-style';
        style.textContent = [
            '.node-library-blog-card-preview{--md-sys-color-primary:#ff9900;--md-sys-color-on-surface:#2b1700;--md-sys-color-surface-container-low:#fff;--md-sys-color-surface-container:#ffe8d1;--md-sys-color-outline-variant:#d6c2b1;--m3-radius-small:12px;--m3-radius-medium:16px;--m3-radius-large:28px;--m3-radius-full:1000px;--m3-spacing-xs:4px;--m3-spacing-s:8px;--m3-spacing-m:16px;--m3-motion-easing:cubic-bezier(.2,0,0,1);max-width:760px;}',
            '.node-library-blog-card-preview .m3-blogcard-wrap{margin:16px 0;}',
            '.node-library-blog-card-preview .m3-blogcard{position:relative;display:block;padding:8px;color:inherit;background:var(--md-sys-color-surface-container-low);border:1px solid var(--md-sys-color-outline-variant);border-radius:28px;box-shadow:0 1px 3px rgba(43,23,0,.14);overflow:hidden;}',
            '.node-library-blog-card-preview .m3-blogcard--internal{border-left-color:var(--md-sys-color-primary);}',
            '.node-library-blog-card-preview .m3-blogcard__body{position:relative;z-index:1;display:flex;gap:16px;align-items:stretch;}',
            '.node-library-blog-card-preview .m3-blogcard__image{flex-shrink:0;width:128px;align-self:stretch;min-height:96px;overflow:hidden;border-radius:16px;background:var(--md-sys-color-surface-container);}',
            '.node-library-blog-card-preview .m3-blogcard__image img{width:100%!important;height:100%!important;max-width:100%!important;margin:0!important;display:block;object-fit:cover;border-radius:inherit!important;}',
            '.node-library-blog-card-preview .m3-blogcard__text{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:4px;padding:4px 8px 4px 4px;}',
            '.node-library-blog-card-preview .m3-blogcard__overlay{display:inline-flex;align-items:flex-start;gap:4px;color:inherit;text-decoration:none;}',
            '.node-library-blog-card-preview .m3-blogcard__title{font-size:.9375rem;font-weight:700;line-height:1.4;color:var(--md-sys-color-on-surface);display:-webkit-box;line-clamp:2;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}',
            '.node-library-blog-card-preview .m3-blogcard__external-icon{flex-shrink:0;margin-top:2px;font-size:16px;color:rgba(43,23,0,.58);}',
            '.node-library-blog-card-preview .m3-blogcard__description{margin:0;font-size:.75rem;line-height:1.5;color:rgba(43,23,0,.68);display:-webkit-box;line-clamp:2;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}',
            '.node-library-blog-card-preview .m3-blogcard__footer{display:flex;align-items:center;gap:8px;margin-top:2px;min-height:36px;}',
            '.node-library-blog-card-preview .m3-blogcard__source{display:inline-flex;align-items:center;gap:6px;flex:1;min-width:0;}',
            '.node-library-blog-card-preview .m3-blogcard__favicon{width:16px!important;height:16px!important;max-width:16px!important;margin:0!important;object-fit:cover;border-radius:4px!important;flex-shrink:0;}',
            '.node-library-blog-card-preview .m3-blogcard__sitename{font-size:.75rem;font-weight:700;color:var(--md-sys-color-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
            '.node-library-blog-card-preview .m3-blogcard__actions{display:inline-flex;align-items:center;gap:2px;flex-shrink:0;}',
            '.node-library-blog-card-preview .m3-blogcard__action{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;padding:0;border:0;border-radius:999px;background:transparent;color:rgba(43,23,0,.6);pointer-events:none;}',
            '.node-library-blog-card-preview .m3-blogcard__action--share{display:none;}',
            '.node-library-blog-card-preview .m3-blogcard__fallback{display:inline-block;padding:8px 16px;border-radius:12px;background:var(--md-sys-color-surface-container);color:var(--md-sys-color-primary);font-weight:700;word-break:break-all;text-decoration:underline;text-underline-offset:3px;}',
            '@media (max-width:600px){.node-library-blog-card-preview .m3-blogcard__body{flex-direction:column;}.node-library-blog-card-preview .m3-blogcard__image{width:100%;min-height:160px;}.node-library-blog-card-preview .m3-blogcard__text{padding:4px;}}'
        ].join('');
        document.head.appendChild(style);
    }

    function BlogCardPreview(props) {
        var url = props.url;
        ensureBlogCardPreviewStyle();
        var htmlState = element.useState('');
        var html = htmlState[0];
        var setHtml = htmlState[1];
        var loadingState = element.useState(false);
        var isLoading = loadingState[0];
        var setIsLoading = loadingState[1];
        var errorState = element.useState('');
        var error = errorState[0];
        var setError = errorState[1];

        element.useEffect(function () {
            if (!url) {
                setHtml('');
                setError('');
                return;
            }

            var cancelled = false;
            setIsLoading(true);
            setError('');

            apiFetch({ path: '/node-library/v1/blog-card-preview?url=' + encodeURIComponent(url) })
                .then(function (res) {
                    if (cancelled) return;
                    setHtml((res && res.html) || '');
                    setIsLoading(false);
                })
                .catch(function (err) {
                    if (cancelled) return;
                    setHtml('');
                    setError((err && err.message) ? err.message : 'プレビューを取得できませんでした。');
                    setIsLoading(false);
                });

            return function () {
                cancelled = true;
            };
        }, [url]);

        if (isLoading) {
            return el('div', { className: 'node-library-blog-card-preview node-library-blog-card-preview--loading' },
                el(Spinner),
                el('span', null, 'カードを取得しています…')
            );
        }

        if (error) {
            return el('div', {
                className: 'node-library-blog-card-preview node-library-blog-card-preview--error',
                style: {
                    marginTop: '12px',
                    padding: '10px 12px',
                    background: '#fcf0f1',
                    border: '1px solid #f0b6bd',
                    borderRadius: '8px',
                    color: '#8a2424',
                    fontSize: '12px'
                }
            }, error);
        }

        if (!html) {
            return null;
        }

        return el('div', {
            className: 'node-library-blog-card-preview',
            style: { marginTop: '12px' },
            onClick: function (event) {
                event.preventDefault();
                event.stopPropagation();
            },
            dangerouslySetInnerHTML: { __html: html }
        });
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
            var urlState = element.useState(attributes.url || '');
            var urlInput = urlState[0];
            var setUrlInput = urlState[1];

            element.useEffect(function () {
                setUrlInput(attributes.url || '');
            }, [attributes.url]);

            var applyUrl = function () {
                var nextUrl = (urlInput || '').trim();
                if (!/^https?:\/\//i.test(nextUrl)) {
                    return;
                }
                setAttributes({ url: nextUrl });
            };

            return el('div', { className: 'node-library-block-editor' },
                el(Placeholder, {
                    icon: 'admin-links',
                    label: 'ブログカード',
                    instructions: '表示したい記事やページの URL を入力してください。'
                },
                    el('form', {
                        style: { display: 'flex', gap: '8px', width: '100%', alignItems: 'flex-end' },
                        onSubmit: function (event) {
                            event.preventDefault();
                            applyUrl();
                        }
                    },
                        el('div', { style: { flex: '1 1 auto' } },
                            el(TextControl, {
                                label: '記事 URL',
                                value: urlInput,
                                placeholder: 'https://luminous-core.net/...',
                                onChange: setUrlInput
                            })
                        ),
                        el(Button, {
                            isPrimary: true,
                            type: 'submit',
                            disabled: !/^https?:\/\//i.test((urlInput || '').trim())
                        }, '追加')
                    ),
                    el('p', { style: { fontSize: '12px', color: '#646970', margin: '8px 0 0' } },
                        'ヒント: URL だけの行を本文に書くと、保存後に自動でブログカード化されます（ショートコード不要）。'
                    ),
                    attributes.url ? el(BlogCardPreview, { url: attributes.url }) : null
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
