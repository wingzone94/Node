( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.data || ! wp.element || ! wp.components ) {
		return;
	}

	const PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { useSelect, useDispatch } = wp.data;
	const { createElement } = wp.element;
	const { SelectControl, TextareaControl, ToggleControl } = wp.components;

	function XPostPanel() {
		const meta = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );
		const text = typeof meta[ config.textMetaKey ] === 'string' ? meta[ config.textMetaKey ] : '';
		const skip = meta[ config.skipMetaKey ] === '1';
		const templateOptions = Array.isArray( config.templates ) ? config.templates : [];
		const defaultTemplate = templateOptions.length > 0 ? templateOptions[ 0 ].value : 'default';
		const template = templateOptions.some( ( option ) => option.value === meta[ config.templateMetaKey ] )
			? meta[ config.templateMetaKey ]
			: defaultTemplate;
		const length = Array.from( text ).length;
		const overLimit = length > config.limit;

		const updateMeta = ( key, value ) => {
			editPost( {
				meta: {
					...meta,
					[ key ]: value,
				},
			} );
		};

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'node-connect-x-post',
				title: 'X自動投稿',
				className: 'node-connect-x-post-panel',
			},
			createElement( SelectControl, {
				label: '投稿文言テンプレート',
				value: template,
				options: templateOptions,
				onChange: ( value ) => updateMeta( config.templateMetaKey, value ),
				help: 'このブログの外部連携設定に保存したテンプレートから選択します。',
			} ),
			createElement( TextareaControl, {
				label: '投稿文（この記事のみ）',
				value: text,
				rows: 8,
				onChange: ( value ) =>
					updateMeta( config.textMetaKey, Array.from( value ).slice( 0, config.limit ).join( '' ) ),
				help: '140文字以内で編集できます。空欄なら選択した投稿文言テンプレートを使用します。',
			} ),
			createElement(
				'p',
				{
					className: 'components-base-control__help',
					style: overLimit ? { color: '#b32d2e' } : undefined,
				},
				`文字数: ${ length } / ${ config.limit }${
					overLimit ? '（上限超過分は保存時に省略されます）' : ''
				}`
			),
			createElement( ToggleControl, {
				label: 'この記事は自動投稿しない',
				checked: skip,
				onChange: ( checked ) => updateMeta( config.skipMetaKey, checked ? '1' : '' ),
			} )
		);
	}

	registerPlugin( 'node-connect-x-post', {
		render: XPostPanel,
		icon: 'share',
	} );
} )( window.wp, window.nodeConnectXEditor || {} );
