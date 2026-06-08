( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element ) {
		return;
	}

	if ( wp.plugins.getPlugin && wp.plugins.getPlugin( 'node-ai-featured-image' ) ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var Button = wp.components.Button;
	var useSelect = wp.data.useSelect;
	var createElement = wp.element.createElement;

	var BRAND = 'Luminous Core';
	var ACCENT = '#FF9900';

	function buildFeaturedImagePrompt( title, excerpt ) {
		var lines = [
			'以下のブログ記事用アイキャッチ画像を生成してください。',
			'',
			'【サイト】' + BRAND + '（テクニカルブログ）',
			'【記事タイトル】' + ( title || '（未入力）' ),
		];

		if ( excerpt ) {
			lines.push( '【記事概要】' + excerpt );
		}

		lines.push(
			'',
			'【要件】',
			'- アスペクト比 16:9（1200×675px 相当）',
			'- Material 3 Expressive な温かみのあるデザイン',
			'- ブランドカラー: オレンジ (' + ACCENT + ') をアクセントに',
			'- テキスト・ロゴ・ウォーターマークは入れない',
			'- 記事内容を象徴する抽象的・象徴的なビジュアル',
			'- 生成AIのウォーターマークを除去しないこと（Luminous Core ガイドライン準拠）',
			'',
			'生成後、画像をダウンロードして WordPress の「アイキャッチ画像」に手動で設定してください。'
		);

		return lines.join( '\n' );
	}

	function copyPromptAndOpen( service, prompt, setStatus ) {
		var urls = {
			gemini: 'https://gemini.google.com/app',
			chatgpt: 'https://chatgpt.com/',
		};

		function openTab() {
			window.open( urls[ service ], '_blank', 'noopener,noreferrer' );
			if ( setStatus ) {
				setStatus( 'プロンプトをコピーし、' + ( service === 'gemini' ? 'Gemini' : 'ChatGPT' ) + ' を開きました。' );
			}
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard
				.writeText( prompt )
				.then( openTab )
				.catch( openTab );
			return;
		}

		openTab();
	}

	function FeaturedImagePanel() {
		var title = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
		}, [] );

		var excerpt = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) || '';
		}, [] );

		var statusTimer = wp.element.useState( '' );
		var status = statusTimer[ 0 ];
		var setStatus = statusTimer[ 1 ];

		var prompt = buildFeaturedImagePrompt( title, excerpt );

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'node-ai-featured-image-panel',
				title: 'AI アイキャッチ',
				icon: 'format-image',
			},
			createElement(
				'p',
				{ style: { fontSize: '12px', color: '#646970', marginTop: 0 } },
				'プロンプトをコピーして外部AIで画像を生成し、手動でアイキャッチに設定します。'
			),
			createElement(
				'div',
				{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: function () {
							copyPromptAndOpen( 'gemini', prompt, setStatus );
						},
					},
					'Geminiで生成する'
				),
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: function () {
							copyPromptAndOpen( 'chatgpt', prompt, setStatus );
						},
					},
					'ChatGPTで生成する'
				)
			),
			status
				? createElement(
						'p',
						{ style: { fontSize: '12px', color: '#00a32a', marginBottom: 0 } },
						status
				  )
				: null
		);
	}

	registerPlugin( 'node-ai-featured-image', {
		render: FeaturedImagePanel,
	} );
} )( window.wp );
