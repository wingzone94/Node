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
	const { TextareaControl, ToggleControl } = wp.components;

	const weightedLength = ( value ) => {
		const withoutUrls = value.replace( /https?:\/\/\S+/g, ( url ) => 'x'.repeat( 23 ) );
		return Array.from( withoutUrls ).reduce(
			( total, character ) => total + ( character.codePointAt( 0 ) <= 0x7f ? 1 : 2 ),
			0
		);
	};

	function XPostPanel() {
		const meta = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );
		const text = typeof meta[ config.textMetaKey ] === 'string' ? meta[ config.textMetaKey ] : '';
		const skip = meta[ config.skipMetaKey ] === '1';
		const length = weightedLength( text );
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
			createElement( TextareaControl, {
				label: '投稿文（この記事のみ）',
				value: text,
				rows: 8,
				onChange: ( value ) => updateMeta( config.textMetaKey, value ),
				help: '空欄なら「設定 → 外部連携」の全体テンプレートを使用します。',
			} ),
			createElement(
				'p',
				{
					className: 'components-base-control__help',
					style: overLimit ? { color: '#b32d2e' } : undefined,
				},
				`Xの重み付き文字数: ${ length } / ${ config.limit }${
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
