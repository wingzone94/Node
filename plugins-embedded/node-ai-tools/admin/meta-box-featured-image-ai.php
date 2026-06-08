<?php
/**
 * クラシックエディタ向け AI アイキャッチ誘導メタボックス
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param WP_Post $post 投稿オブジェクト。
 */
function node_ai_render_featured_image_meta_box( WP_Post $post ): void {
	$title   = $post->post_title;
	$excerpt = $post->post_excerpt;
	?>
	<div class="node-ai-featured-meta">
		<p class="description">
			プロンプトをコピーして外部AIで画像を生成し、手動でアイキャッチに設定します。
		</p>
		<p>
			<button type="button" class="button button-secondary" id="node_ai_featured_gemini"
				data-title="<?php echo esc_attr( $title ); ?>"
				data-excerpt="<?php echo esc_attr( $excerpt ); ?>">
				Geminiで生成する
			</button>
			<button type="button" class="button button-secondary" id="node_ai_featured_chatgpt"
				data-title="<?php echo esc_attr( $title ); ?>"
				data-excerpt="<?php echo esc_attr( $excerpt ); ?>">
				ChatGPTで生成する
			</button>
		</p>
		<p id="node_ai_featured_status" style="font-size:12px;font-weight:600;"></p>
	</div>
	<script>
	(function() {
		function buildPrompt(title, excerpt) {
			var lines = [
				'以下のブログ記事用アイキャッチ画像を生成してください。',
				'',
				'【サイト】Luminous Core（テクニカルブログ）',
				'【記事タイトル】' + (title || '（未入力）')
			];
			if (excerpt) lines.push('【記事概要】' + excerpt);
			lines.push(
				'',
				'【要件】',
				'- アスペクト比 16:9（1200×675px 相当）',
				'- Material 3 Expressive な温かみのあるデザイン',
				'- ブランドカラー: オレンジ (#FF9900) をアクセントに',
				'- テキスト・ロゴ・ウォーターマークは入れない',
				'- 記事内容を象徴する抽象的・象徴的なビジュアル',
				'- 生成AIのウォーターマークを除去しないこと（Luminous Core ガイドライン準拠）',
				'',
				'生成後、画像をダウンロードして WordPress の「アイキャッチ画像」に手動で設定してください。'
			);
			return lines.join('\n');
		}

		function openService(service, btn) {
			var prompt = buildPrompt(btn.getAttribute('data-title') || '', btn.getAttribute('data-excerpt') || '');
			var urls = { gemini: 'https://gemini.google.com/app', chatgpt: 'https://chatgpt.com/' };
			var status = document.getElementById('node_ai_featured_status');
			var label = service === 'gemini' ? 'Gemini' : 'ChatGPT';

			function done() {
				window.open(urls[service], '_blank', 'noopener,noreferrer');
				if (status) status.textContent = 'プロンプトをコピーし、' + label + ' を開きました。';
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(prompt).then(done).catch(done);
			} else {
				done();
			}
		}

		var geminiBtn = document.getElementById('node_ai_featured_gemini');
		var chatgptBtn = document.getElementById('node_ai_featured_chatgpt');
		if (geminiBtn) geminiBtn.addEventListener('click', function() { openService('gemini', geminiBtn); });
		if (chatgptBtn) chatgptBtn.addEventListener('click', function() { openService('chatgpt', chatgptBtn); });
	})();
	</script>
	<?php
}
