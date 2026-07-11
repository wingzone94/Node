/**
 * Node Blocks — エディタスクリプト（1.1.6 先行移植版）
 *
 * node/notice ブロック: お知らせ / 注意 / 重要 / 補足のコールアウト。
 * v1.2 のエディタスクリプトから notice 部分のみを抽出した先行実装。
 * （node/embed 等の v1.2 専用ブロックは含まない）
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };

	// node/notice — お知らせ / 注意 / 重要 / 補足 のコールアウトブロック。
	// 静的 save（保存時に blockquote ベースの HTML を確定）。フロントの配色は
	// テーマの src/styles/_notice.css（style.css にバンドル）が担当し、エディタ
	// 内はここでのインラインスタイルで近似プレビューする。
	// ------------------------------------------------------------------

	var blockEditor = wp.blockEditor || wp.editor;

	if ( ! ( blockEditor && blockEditor.InnerBlocks ) ) {
		// wp-block-editor が未ロードだと node/notice を登録できない。
		// （enqueue の依存に 'wp-block-editor' が無い古い状態＝opcache 等）
		if ( window.console && console.warn ) {
			console.warn( 'node/notice: wp.blockEditor が利用できないためブロックを登録できませんでした。' );
		}
	} else {
		var InnerBlocks = blockEditor.InnerBlocks;
		var InspectorControls = blockEditor.InspectorControls;
		var useBlockProps = blockEditor.useBlockProps;
		var PlainText = blockEditor.PlainText;
		var PanelBody = wp.components.PanelBody;
		var SelectControl = wp.components.SelectControl;

		// タイトル（最上部）は必ず Inter＋Noto Sans JP の組み合わせ（テーマの
		// --font-heading）＋太字。エディタ canvas に theme 変数が無い場合に
		// 備えてフォールバックを持たせる。
		var NOTICE_TITLE_FONT = 'var(--font-heading, "Inter", "Noto Sans JP", sans-serif)';

		// タイプ別の既定ラベル＋エディタプレビュー配色（テーマの _notice.css の
		// ライトトークンと一致させる）。エディタプレビューのアイコンはインライン
		// SVG（noticeIcon）で描画（canvas に Material Symbols フォントが無いため）。
		// 保存 HTML（save）は Material Symbols の <span> を出力する。
		var NOTICE_TYPES = {
			info:    { label: 'お知らせ', icon: 'info',    accent: '#0B57D0', title: '#0842A0', bg: '#E8F0FE', border: '#A8C7FA' },
			warning: { label: '注意',     icon: 'warning', accent: '#A15C00', title: '#7A4500', bg: '#FFF3DB', border: '#F0D08A' },
			alert:   { label: '重要',     icon: 'report',  accent: '#B3520E', title: '#8C3E08', bg: '#FEF0E6', border: '#F0B88A' },
			memo:    { label: '補足',     icon: 'edit_note', accent: '#5F6368', title: '#3C4043', bg: '#F1F3F4', border: '#DADCE0' },
		};

		function noticeConfig( type ) {
			return NOTICE_TYPES[ type ] || NOTICE_TYPES.info;
		}

		// エディタプレビュー用：バリアントごとに形状の異なるインライン SVG アイコン。
		// save では使わない（save は Material Symbols の cfg.icon を出力）。
		function noticeIconChildren( type ) {
			if ( 'warning' === type ) {
				return [
					el( 'path', { key: 's', d: 'M12 3.5 L21.5 20 L2.5 20 Z' } ),
					el( 'line', { key: 'l', x1: 12, y1: 9, x2: 12, y2: 14 } ),
					el( 'line', { key: 'd', x1: 12, y1: 17, x2: 12, y2: 17 } ),
				];
			}
			if ( 'alert' === type ) {
				// 八角形＋!（重要）
				return [
					el( 'path', { key: 'o', d: 'M8 2.75 H16 L21.25 8 V16 L16 21.25 H8 L2.75 16 V8 Z' } ),
					el( 'line', { key: 'l', x1: 12, y1: 8, x2: 12, y2: 13 } ),
					el( 'line', { key: 'd', x1: 12, y1: 16, x2: 12, y2: 16 } ),
				];
			}
			if ( 'memo' === type ) {
				// 鉛筆（補足）
				return [
					el( 'path', { key: 'p', d: 'M12 20h9' } ),
					el( 'path', { key: 'e', d: 'M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z' } ),
				];
			}
			// info
			return [
				el( 'circle', { key: 'c', cx: 12, cy: 12, r: 9 } ),
				el( 'line', { key: 'l', x1: 12, y1: 11, x2: 12, y2: 16 } ),
				el( 'line', { key: 'd', x1: 12, y1: 8, x2: 12, y2: 8 } ),
			];
		}

		function noticeIcon( type, extraProps ) {
			var props = {
				className: 'm3-notice__icon',
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
				width: 22,
				height: 22,
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
				'aria-hidden': 'true',
				focusable: 'false',
			};
			if ( extraProps ) {
				Object.assign( props, extraProps );
			}
			return el( 'svg', props, noticeIconChildren( type ) );
		}

		// role="note" を基本とし、重要度の高い alert は aria-live="polite" を併用
		// （割り込みの強い role="alert" は避ける）。
		function noticeA11y( type ) {
			var a11y = { role: 'note' };
			if ( 'alert' === type ) {
				a11y[ 'aria-live' ] = 'polite';
			}
			return a11y;
		}

		var NOTICE_ALLOWED = [ 'core/paragraph', 'core/list', 'core/heading', 'core/image', 'core/quote', 'core/table', 'core/buttons' ];
		var NOTICE_TEMPLATE = [ [ 'core/paragraph', {} ] ];

		// PHP 側の register_block_type がサーバー登録するため、
		// クライアント側の registerBlockType が「already registered」で
		// 失敗しないよう一度 unregister してから edit/save 付きで再登録する。
		if ( wp.blocks.getBlockType( 'node/notice' ) ) {
			wp.blocks.unregisterBlockType( 'node/notice' );
		}

		registerBlockType( 'node/notice', {
			title: __( 'Node お知らせ', 'luminous-blocks' ),
			description: __( 'お知らせ・注意・重要・補足のコールアウトを本文中に表示します。', 'luminous-blocks' ),
			icon: 'info-outline',
			category: 'node',
			keywords: [ __( 'お知らせ', 'luminous-blocks' ), __( '注意', 'luminous-blocks' ), __( '重要', 'luminous-blocks' ), __( '補足', 'luminous-blocks' ), 'notice', 'callout', 'admonition' ],
			attributes: {
				type:  { type: 'string', default: 'info' },
				title: { type: 'string', default: '' },
				shape: { type: 'string', default: 'rounded' },
			},
			supports: {
				html: false,
				anchor: true,
			},

			edit: function ( props ) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				var type = attributes.type || 'info';
				var shape = attributes.shape || 'rounded';
				var cfg = noticeConfig( type );

				// お知らせ（info）タイプは1記事1個制限。他タイプは制限なし。
				var select = wp.data && wp.data.select;
				var infoLimitHit = false;
				if ( 'info' === type && select ) {
					var allBlocks = select( 'core/block-editor' ).getBlocks();
					var infoCount = 0;
					for ( var bi = 0; bi < allBlocks.length; bi++ ) {
						if ( 'node/notice' === allBlocks[ bi ].name && 'info' === ( allBlocks[ bi ].attributes.type || 'info' ) && allBlocks[ bi ].clientId !== props.clientId ) {
							infoCount++;
						}
					}
					infoLimitHit = infoCount > 0;
				}

				// エディタ canvas はテーマの _notice.css を読み込まないため、
				// フロントのトークンに一致するインラインスタイルで近似プレビューする。
				var blockProps = useBlockProps( Object.assign( {
					className: 'm3-notice m3-notice--' + type + ( 'square' === shape ? ' m3-notice--square' : '' ),
					style: {
						margin: '1.5rem 0',
						padding: '16px 20px',
						background: cfg.bg,
						border: '1px solid ' + cfg.border,
						borderRadius: 'square' === shape ? '0' : '16px',
						color: 'var(--md-sys-color-on-surface, #2b1700)',
						fontStyle: 'normal',
					},
				}, noticeA11y( type ) ) );

				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( '種別', 'luminous-blocks' ), initialOpen: true },
							el( SelectControl, {
								label: __( 'タイプ', 'luminous-blocks' ),
								value: type,
								options: [
									{ label: __( 'お知らせ（青）', 'luminous-blocks' ), value: 'info' },
									{ label: __( '注意（黄）', 'luminous-blocks' ), value: 'warning' },
									{ label: __( '重要（橙）', 'luminous-blocks' ), value: 'alert' },
									{ label: __( '補足（灰）', 'luminous-blocks' ), value: 'memo' },
								],
								onChange: function ( value ) {
									setAttributes( { type: value } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( SelectControl, {
								label: __( '形', 'luminous-blocks' ),
								value: shape,
								options: [
									{ label: __( '角丸', 'luminous-blocks' ), value: 'rounded' },
									{ label: __( '四角', 'luminous-blocks' ), value: 'square' },
								],
								onChange: function ( value ) {
									setAttributes( { shape: value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					),
					infoLimitHit ? el(
						'div',
						{ style: { padding: '8px 12px', marginBottom: '8px', background: '#fff3cd', border: '1px solid #ffc107', borderRadius: '8px', fontSize: '13px', color: '#664d03' } },
						'⚠ お知らせ（info）タイプは1記事につき1つまでです。タイプを変更してください。'
					) : null,
					el(
						'blockquote',
						blockProps,
						el(
							'div',
							{
								className: 'm3-notice__header',
								// アイコンとタイトルの行ボックスを一致させて高さを揃える
								style: { display: 'flex', alignItems: 'flex-start', gap: '8px', marginBottom: '8px', fontSize: '1.05rem', lineHeight: 1.4 },
							},
							noticeIcon( type, { style: { color: cfg.accent, width: '1.4em', height: '1.4em' } } ),
							el( PlainText, {
								className: 'm3-notice__title',
								value: attributes.title || '',
								onChange: function ( value ) {
									setAttributes( { title: value } );
								},
								placeholder: cfg.label,
								style: { color: cfg.title, fontFamily: NOTICE_TITLE_FONT, fontWeight: 700, background: 'transparent', lineHeight: 1.4 },
							} )
						),
						el(
							'div',
							{ className: 'm3-notice__body' },
							el( InnerBlocks, {
								allowedBlocks: NOTICE_ALLOWED,
								template: NOTICE_TEMPLATE,
								templateLock: false,
							} )
						)
					)
				);
			},

			save: function ( props ) {
				var attributes = props.attributes;
				var type = attributes.type || 'info';
				var shape = attributes.shape || 'rounded';
				var cfg = noticeConfig( type );
				var title = ( attributes.title && attributes.title.trim() ) ? attributes.title : cfg.label;

				var blockProps = useBlockProps.save( Object.assign( {
					className: 'm3-notice m3-notice--' + type + ( 'square' === shape ? ' m3-notice--square' : '' ),
				}, noticeA11y( type ) ) );

				return el(
					'blockquote',
					blockProps,
					el(
						'div',
						{ className: 'm3-notice__header' },
						el( 'span', { className: 'material-symbols-outlined m3-notice__icon', 'aria-hidden': 'true' }, cfg.icon ),
						el( 'span', { className: 'm3-notice__title' }, title )
					),
					el(
						'div',
						{ className: 'm3-notice__body' },
						el( InnerBlocks.Content )
					)
				);
			},
		} );
	}
} )( window.wp );
