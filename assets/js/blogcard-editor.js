/**
 * node/blogcard ブロック — エディタ上でブログカードのプレビューを表示する。
 *
 * フロントと同じ node_render_blogcard() の出力を REST（node/v1/blogcard-preview）
 * から受け取ってそのまま描画するため、エディタで見えるものが公開後の表示になる。
 *
 * ビルド対象外の手動配置アセット（assets/js/blocks.js と同じ扱い）。
 * ES5 相当で書き、wp.element.createElement を直接使う（JSX なし）。
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.blocks || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerBlockType = wp.blocks.registerBlockType;
	var createBlock = wp.blocks.createBlock;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var Button = wp.components.Button;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var PanelBody = wp.components.PanelBody;
	var Notice = wp.components.Notice;
	var blockEditor = wp.blockEditor || wp.editor;
	var InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
	var useBlockProps = blockEditor ? blockEditor.useBlockProps : null;
	var apiFetch = wp.apiFetch;
	var subscribe = wp.data.subscribe;
	var select = wp.data.select;
	var dispatch = wp.data.dispatch;
	var __ = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };

	// ------------------------------------------------------------------
	// ストア判定（PHP の node_store_provider() と対になる最小実装）
	// ここでの用途は「貼り付け時に自動でカードブロックへ変換するか」の判断のみ。
	// 実際のカード生成・ストア名の決定はすべてサーバー側が行う。
	// ------------------------------------------------------------------

	function hostOf( url ) {
		try {
			return new URL( url ).hostname.toLowerCase().replace( /^www\./, '' );
		} catch ( e ) {
			return '';
		}
	}

	function pathOf( url ) {
		try {
			return new URL( url ).pathname.toLowerCase();
		} catch ( e ) {
			return '';
		}
	}

	function hostMatches( host, domains ) {
		for ( var i = 0; i < domains.length; i++ ) {
			if ( host === domains[ i ] || host.slice( -( domains[ i ].length + 1 ) ) === '.' + domains[ i ] ) {
				return true;
			}
		}
		return false;
	}

	function isStoreUrl( url ) {
		if ( ! /^https?:\/\//i.test( url ) ) {
			return false;
		}

		var host = hostOf( url );
		var path = pathOf( url );

		if ( ! host ) {
			return false;
		}

		if ( hostMatches( host, [ 'store-jp.nintendo.com', 'store.nintendo.co.jp', 'ec.nintendo.com', 'store.nintendo.com' ] ) ) {
			return true;
		}
		if ( hostMatches( host, [ 'nintendo.com' ] ) && path.indexOf( '/store/' ) !== -1 ) {
			return true;
		}
		if ( hostMatches( host, [ 'nintendo.co.jp' ] ) && path.indexOf( '/software/' ) !== -1 ) {
			return true;
		}
		if ( hostMatches( host, [ 'store.playstation.com' ] ) ) {
			return true;
		}
		if ( hostMatches( host, [ 'xbox.com' ] ) && path.indexOf( '/games/store/' ) !== -1 ) {
			return true;
		}
		if ( hostMatches( host, [ 'marketplace.xbox.com', 'apps.microsoft.com' ] ) ) {
			return true;
		}
		if ( hostMatches( host, [ 'store.steampowered.com', 's.team' ] ) ) {
			return true;
		}

		return false;
	}

	function plainText( content ) {
		var div = document.createElement( 'div' );
		div.innerHTML = String( content || '' );
		return ( div.textContent || '' ).trim();
	}

	// ------------------------------------------------------------------
	// プレビュー
	// ------------------------------------------------------------------

	function BlogcardPreview( props ) {
		var url = props.url;
		var overrides = props.overrides || {};

		var htmlState = useState( '' );
		var html = htmlState[ 0 ];
		var setHtml = htmlState[ 1 ];
		var loadingState = useState( false );
		var isLoading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];
		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];

		useEffect( function () {
			if ( ! url ) {
				setHtml( '' );
				return;
			}

			var cancelled = false;
			setLoading( true );
			setError( '' );

			var path = '/node/v1/blogcard-preview?url=' + encodeURIComponent( url );
			path += '&show_image=' + ( false === overrides.showImage ? '0' : '1' );
			if ( overrides.title ) {
				path += '&title=' + encodeURIComponent( overrides.title );
			}
			if ( overrides.description ) {
				path += '&description=' + encodeURIComponent( overrides.description );
			}
			if ( overrides.image ) {
				path += '&image=' + encodeURIComponent( overrides.image );
			}

			apiFetch( { path: path } )
				.then( function ( res ) {
					if ( cancelled ) {
						return;
					}
					setHtml( ( res && res.html ) || '' );
					setLoading( false );
				} )
				.catch( function ( err ) {
					if ( cancelled ) {
						return;
					}
					setError( ( err && err.message ) || __( 'プレビューを取得できませんでした', 'node' ) );
					setLoading( false );
				} );

			return function () {
				cancelled = true;
			};
		}, [ url, overrides.title, overrides.description, overrides.image, overrides.showImage ] );

		if ( isLoading ) {
			return el(
				'div',
				{ className: 'node-blogcard-preview node-blogcard-preview--loading' },
				el( Spinner, null ),
				el( 'span', null, __( 'カードを取得しています…', 'node' ) )
			);
		}

		if ( error ) {
			return el(
				Notice,
				{ status: 'warning', isDismissible: false },
				error
			);
		}

		if ( ! html ) {
			return el( 'p', { className: 'node-blogcard-preview__empty' }, url );
		}

		// サーバーが生成したカードHTMLをそのまま表示する（クリックはブロック選択に使うため無効化）。
		return el( 'div', {
			className: 'node-blogcard-preview',
			dangerouslySetInnerHTML: { __html: html },
		} );
	}

	// ------------------------------------------------------------------
	// 旧ブロック（node-library/blog-card）をインサーターから隠す
	//
	// 同じ「ブログカード」が2つ並ぶのを防ぐ。node-library 側でも inserter:false を
	// 指定しているが、単体プラグインとして古い版が入っている環境でも効くよう、
	// 登録フィルターでも落とす（登録は残すので既存記事の表示は壊れない）。
	// ------------------------------------------------------------------

	if ( wp.hooks && wp.hooks.addFilter ) {
		wp.hooks.addFilter(
			'blocks.registerBlockType',
			'node/hide-legacy-blogcard',
			function ( settings, name ) {
				if ( 'node-library/blog-card' !== name ) {
					return settings;
				}

				var supports = settings.supports || {};
				supports.inserter = false;
				settings.supports = supports;

				return settings;
			}
		);
	}

	// ------------------------------------------------------------------
	// ブロック登録
	// PHP 側の register_block_type が render_callback 付きで先に登録するため、
	// 一度 unregister してから JS 側の edit を持つ定義で登録し直す。
	// ------------------------------------------------------------------

	if ( wp.blocks.getBlockType( 'node/blogcard' ) ) {
		wp.blocks.unregisterBlockType( 'node/blogcard' );
	}

	registerBlockType( 'node/blogcard', {
		title: __( 'ブログカード', 'node' ),
		description: __( 'URL をブログカードとして表示します。エディタ上で公開後と同じ見た目を確認できます。', 'node' ),
		icon: 'admin-links',
		category: 'embed',
		keywords: [ 'blogcard', __( 'カード', 'node' ), __( 'リンク', 'node' ), 'store', __( 'ストア', 'node' ) ],
		attributes: {
			url: { type: 'string', default: '' },
			title: { type: 'string', default: '' },
			description: { type: 'string', default: '' },
			image: { type: 'string', default: '' },
			showImage: { type: 'boolean', default: true },
		},
		supports: {
			html: false,
		},

		transforms: {
			from: [
				{
					// 旧ブロック（node-library/blog-card）からの移行。
					// フロント出力は元から同じ node_render_blogcard() なので見た目は変わらない。
					type: 'block',
					blocks: [ 'node-library/blog-card' ],
					transform: function ( attrs ) {
						return createBlock( 'node/blogcard', { url: String( attrs.url || '' ) } );
					},
				},
				{
					type: 'block',
					blocks: [ 'core/embed' ],
					isMatch: function ( attrs ) {
						return /^https?:\/\//i.test( String( attrs.url || '' ) );
					},
					transform: function ( attrs ) {
						return createBlock( 'node/blogcard', { url: String( attrs.url || '' ) } );
					},
				},
				{
					type: 'block',
					blocks: [ 'core/paragraph' ],
					isMatch: function ( attrs ) {
						return /^https?:\/\/\S+$/i.test( plainText( attrs.content ) );
					},
					transform: function ( attrs ) {
						return createBlock( 'node/blogcard', { url: plainText( attrs.content ) } );
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/paragraph' ],
					transform: function ( attrs ) {
						return createBlock( 'core/paragraph', { content: attrs.url } );
					},
				},
			],
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var draftState = useState( attributes.url || '' );
			var draft = draftState[ 0 ];
			var setDraft = draftState[ 1 ];

			useEffect( function () {
				if ( attributes.url ) {
					setDraft( attributes.url );
				}
			}, [ attributes.url ] );

			var blockProps = useBlockProps ? useBlockProps() : {};

			if ( ! attributes.url ) {
				return el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							icon: 'admin-links',
							label: __( 'ブログカード', 'node' ),
							instructions: __( 'カードにしたいページの URL を入力してください。', 'node' ),
						},
						el(
							'form',
							{
								style: { display: 'flex', gap: '8px', width: '100%', alignItems: 'flex-start' },
								onSubmit: function ( event ) {
									event.preventDefault();
									var trimmed = draft.trim();
									if ( /^https?:\/\//i.test( trimmed ) ) {
										setAttributes( { url: trimmed } );
									}
								},
							},
							el( TextControl, {
								value: draft,
								placeholder: 'https://',
								onChange: setDraft,
								__nextHasNoMarginBottom: true,
							} ),
							el( Button, { variant: 'primary', type: 'submit' }, __( '適用', 'node' ) )
						)
					)
				);
			}

			return el(
				Fragment,
				null,
				InspectorControls
					? el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( 'カードの内容', 'node' ), initialOpen: true },
							el( TextControl, {
								label: __( 'URL', 'node' ),
								value: attributes.url,
								onChange: function ( value ) {
									setAttributes( { url: value } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( ToggleControl, {
								label: __( 'サムネイル画像を表示', 'node' ),
								help: attributes.showImage
									? __( 'リンク先から画像を取得できた場合に表示します。', 'node' )
									: __( '画像を表示せず、テキストだけのカードにします。', 'node' ),
								checked: !! attributes.showImage,
								onChange: function ( value ) {
									setAttributes( { showImage: !! value } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( TextControl, {
								label: __( 'タイトル（手動指定）', 'node' ),
								help: __( 'ストア側の制限でタイトルを取得できない場合に指定します。空なら取得結果を使います。', 'node' ),
								value: attributes.title,
								onChange: function ( value ) {
									setAttributes( { title: value } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( TextareaControl, {
								label: __( '説明（手動指定）', 'node' ),
								value: attributes.description,
								onChange: function ( value ) {
									setAttributes( { description: value } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( TextControl, {
								label: __( '画像URL（手動指定）', 'node' ),
								value: attributes.image,
								onChange: function ( value ) {
									setAttributes( { image: value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					)
					: null,
				el(
					'div',
					blockProps,
					el( BlogcardPreview, {
						url: attributes.url,
						overrides: {
							title: attributes.title,
							description: attributes.description,
							image: attributes.image,
							showImage: attributes.showImage,
						},
					} )
				)
			);
		},

		save: function () {
			// 動的ブロック（PHP の render_callback が出力する）。
			return null;
		},
	} );

	// ------------------------------------------------------------------
	// ストアURLの貼り付け → node/blogcard へ自動変換
	//
	// Gutenberg はURLを貼ると core/embed を作るが、ストアは oEmbed に対応していない
	// ためエディタ上では「埋め込めませんでした」になる。対象が確実なストアURLの場合
	// だけカードブロックへ置き換える（他のURLは従来どおり core/embed のまま）。
	// ------------------------------------------------------------------

	var replacedIds = {};

	subscribe( function () {
		var editor = select( 'core/block-editor' );
		if ( ! editor ) {
			return;
		}

		var blocks = editor.getBlocks();

		for ( var i = 0; i < blocks.length; i++ ) {
			var block = blocks[ i ];

			if ( 'core/embed' !== block.name || replacedIds[ block.clientId ] ) {
				continue;
			}

			var url = String( block.attributes.url || '' );
			if ( ! isStoreUrl( url ) ) {
				continue;
			}

			replacedIds[ block.clientId ] = true;
			dispatch( 'core/block-editor' ).replaceBlock(
				block.clientId,
				createBlock( 'node/blogcard', { url: url } )
			);
			return;
		}
	} );
}() );
