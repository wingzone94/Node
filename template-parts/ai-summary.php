<?php
/**
 * AI Summary Component
 *
 * @param string $summary 要約テキスト
 * @param string $mode 'card' または 'single'
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$summary = $args['summary'] ?? '';
$mode    = $args['mode'] ?? 'card';

// 空なら何も出さない
if ( empty( $summary ) ) return;

// カード用（短い要約 + ネイティブ折りたたみ）
if ( $mode === 'card' ) :
?>
<details class="ai-summary-card">
	<summary>AI 要約</summary>
	<p><?php echo esc_html( $summary ); ?></p>
</details>

<?php
// シングル用（長い要約 + シマー + フェードイン）
else :
?>
<div class="ai-summary-single">
	<div class="ai-summary-label">AI 要約</div>

	<div class="ai-summary-content" data-ai-summary>
		<div class="ai-summary-shimmer"></div>
		<p class="ai-summary-text is-hidden"><?php echo esc_html( $summary ); ?></p>
	</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
	const wrap = document.querySelector("[data-ai-summary]");
	if (!wrap) return;

	const shimmer = wrap.querySelector(".ai-summary-shimmer");
	const text = wrap.querySelector(".ai-summary-text");

	// シマーを 600ms でフェードアウト
	setTimeout(() => {
		shimmer.style.opacity = 0;
		setTimeout(() => {
			shimmer.remove();
			text.classList.remove("is-hidden");
		}, 300);
	}, 600);
});
</script>

<?php endif; ?>
