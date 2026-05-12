(function(blocks, element, components, data, apiFetch, blockEditor) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var SelectControl = components.SelectControl;
    var SearchControl = components.SearchControl;
    var TextControl = components.TextControl;
    var CheckboxControl = components.CheckboxControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var Spinner = components.Spinner;
    var Placeholder = components.Placeholder;
    var Button = components.Button;
    var useSelect = data.useSelect;

    // --- Node Library Block ---
    registerBlockType('node-library/item-card', {
        title: 'Node Library Card',
        icon: 'database-add',
        category: 'common',
        attributes: { libraryId: { type: 'number', default: 0 } },
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var [searchTerm, setSearchTerm] = element.useState('');
            var [results, setResults] = element.useState([]);
            var [isLoading, setIsLoading] = element.useState(false);

            element.useEffect(function() {
                if (searchTerm.length < 2) { setResults([]); return; }
                setIsLoading(true);
                apiFetch({ path: '/wp/v2/node_library?search=' + encodeURIComponent(searchTerm) })
                    .then(function(items) { setResults(items); setIsLoading(false); })
                    .catch(function() { setIsLoading(false); });
            }, [searchTerm]);

            var selectedItem = useSelect(function(select) {
                return attributes.libraryId ? select('core').getEntityRecord('postType', 'node_library', attributes.libraryId) : null;
            }, [attributes.libraryId]);

            return el('div', { className: 'node-library-block-editor' },
                el(Placeholder, {
                    icon: 'database-add',
                    label: 'Node Library 連携',
                    instructions: 'ライブラリからゲーム・アプリを検索して埋め込みます。'
                },
                    el('div', { style: { width: '100%' } },
                        el(SearchControl, { label: 'タイトルで検索', value: searchTerm, onChange: setSearchTerm }),
                        isLoading && el(Spinner),
                        results.length > 0 && el(SelectControl, {
                            label: '結果から選択',
                            options: [{ label: '-- 選択してください --', value: '' }].concat(
                                results.map(function(item) { return { label: item.title.rendered, value: item.id }; })
                            ),
                            onChange: function(id) { setAttributes({ libraryId: parseInt(id) }); },
                            value: attributes.libraryId
                        }),
                        selectedItem && el('div', { style: { marginTop: '15px', padding: '10px', background: '#f0f0f0', borderRadius: '8px' } },
                            el('strong', null, '選択中: '), selectedItem.title.rendered
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('a', { href: '/wp-admin/post-new.php?post_type=node_library', target: '_blank', className: 'components-button is-secondary' }, '新規登録ページを開く (別タブ)')
                        )
                    )
                )
            );
        },
        save: function() { return null; }
    });

    // --- Blog Card Block ---
    registerBlockType('node-library/blog-card', {
        title: 'Blog Card',
        icon: 'admin-links',
        category: 'common',
        attributes: { url: { type: 'string', default: '' } },
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            return el('div', { className: 'node-library-block-editor' },
                el(Placeholder, {
                    icon: 'admin-links',
                    label: 'ブログカード埋め込み',
                    instructions: '表示したい記事のURLを入力してください。'
                },
                    el(TextControl, { label: '記事URL', value: attributes.url, placeholder: 'https://...', onChange: function(val) { setAttributes({ url: val }); } })
                )
            );
        },
        save: function() { return null; }
    });

    // --- Product Card Block ---
    registerBlockType('node-library/product-card', {
        title: 'Product Card (Auto)',
        icon: 'cart',
        category: 'common',
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
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var [urlInput, setUrlInput] = element.useState('');
            var [isFetching, setIsFetching] = element.useState(false);

            var fetchProductInfo = function() {
                if (!urlInput) return;
                setIsFetching(true);
                apiFetch({ path: '/node-library/v1/fetch-ogp?url=' + encodeURIComponent(urlInput) })
                    .then(function(data) {
                        var cleanTitle = data.title.replace(/^Amazon\s*\|\s*/i, '').replace(/[\s\|]+Amazon\.co\.jp$/i, '');
                        setAttributes({ title: cleanTitle, imageUrl: data.image });
                        if (data.asin) {
                            setAttributes({ asin: data.asin, showAmazonDisclosure: true });
                        } else if (urlInput.includes('rakuten.co.jp')) {
                            setAttributes({ rakutenUrl: urlInput, showRakutenDisclosure: true });
                        }
                        setIsFetching(false);
                        setUrlInput('');
                    })
                    .catch(function(err) {
                        setIsFetching(false);
                        var msg = err.message || '情報の取得に失敗しました。';
                        alert('エラー: ' + msg);
                    });
            };

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'ディスクロージャー設定' },
                        el(CheckboxControl, {
                            label: 'Amazon用の注釈を表示',
                            checked: attributes.showAmazonDisclosure,
                            onChange: function(val) { setAttributes({ showAmazonDisclosure: val }); }
                        }),
                        el(CheckboxControl, {
                            label: '楽天用の注釈を表示',
                            checked: attributes.showRakutenDisclosure,
                            onChange: function(val) { setAttributes({ showRakutenDisclosure: val }); }
                        })
                    )
                ),
                el('div', { className: 'node-library-block-editor', key: 'editor' },
                    el(Placeholder, {
                        icon: 'cart',
                        label: '商品リンクカード (自動取得)',
                        instructions: 'Amazonや楽天のURLを貼り付けると情報を自動取得します。'
                    },
                        el('div', { style: { width: '100%', display: 'flex', gap: '10px', marginBottom: '20px' } },
                            el(TextControl, {
                                label: 'URLを貼り付け',
                                value: urlInput,
                                placeholder: 'https://www.amazon.co.jp/...',
                                onChange: setUrlInput,
                                style: { flex: 1, marginBottom: 0 }
                            }),
                            el(Button, {
                                isPrimary: true,
                                onClick: fetchProductInfo,
                                disabled: isFetching,
                                style: { alignSelf: 'flex-end' }
                            }, isFetching ? el(Spinner) : '情報を取得')
                        ),
                        el('div', { style: { width: '100%', borderTop: '1px solid #ddd', paddingTop: '15px' } },
                            el(TextControl, { label: '商品名', value: attributes.title, onChange: function(val) { setAttributes({ title: val }); } }),
                            el(TextControl, { label: '参考価格', value: attributes.price, placeholder: '￥2,980', onChange: function(val) { setAttributes({ price: val }); } }),
                            el(TextControl, { label: '画像URL', value: attributes.imageUrl, onChange: function(val) { setAttributes({ imageUrl: val }); } }),
                            el(TextControl, { label: 'Amazon ASIN', value: attributes.asin, placeholder: 'B0XXXXXXXX', onChange: function(val) { setAttributes({ asin: val }); } }),
                            el(TextControl, { label: '楽天 URL', value: attributes.rakutenUrl, onChange: function(val) { setAttributes({ rakutenUrl: val }); } })
                        )
                    )
                )
            ];
        },
        save: function() { return null; }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.data,
    window.wp.apiFetch,
    window.wp.blockEditor
);
