<?php
/**
 * AI Summary Component
 *
 * @param array $args {
 *     @type string $summary    要約テキスト
 *     @type string $mode       'card' または 'single'
 *     @type string $tone_color AI判定のカラー
 *     @type array  $keywords   AI抽出のキーワード
 * }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$summary    = $args['summary'] ?? '';
$mode       = $args['mode'] ?? 'card';
$tone_color = '#FF9800'; // AIエリアをオレンジで統一
$keywords   = $args['keywords'] ?? [];

// 空なら何も出さない
if ( empty( $summary ) ) return;

// カード用（短い要約 + ネイティブ折りたたみ）は article-card.php で実装済みのため、
// ここでは主にシングル用（詳細ページ用）の演出を強化します。
if ( $mode === 'single' ) :
?>
<div class="ai-summary-single" id="m3-ai-summary" style="--ai-vibe-color: <?php echo esc_attr( $tone_color ); ?>;">
    <div class="ai-summary-inner">
        <div class="ai-summary-header">
            <div class="ai-summary-label">
                <span class="material-symbols-outlined m3-expressive-icon">auto_awesome</span>
                <span class="m3-expressive-title">Intelligence Summary</span>
            </div>
        </div>

        <div class="ai-summary-content" data-ai-summary>
            <div class="ai-summary-shimmer"></div>
            <div class="ai-summary-text-wrapper">
                <p class="ai-summary-text is-hidden"><?php echo esc_html( $summary ); ?></p>
            </div>
        </div>

        <div class="ai-summary-credit">
            <span class="m3-credit-text">by Gemini</span>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-ai-summary]").forEach((wrap) => {
        const shimmer = wrap.querySelector(".ai-summary-shimmer");
        const text = wrap.querySelector(".ai-summary-text");
        if (!shimmer || !text) return;

        // 擬似的な読み込み演出 (Material 3 Motion)
        setTimeout(() => {
            shimmer.style.opacity = '0';
            setTimeout(() => {
                shimmer.remove();
                text.classList.remove("is-hidden");
                text.style.animation = "aiFadeInUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards";
            }, 300);
        }, 800);
    });
});
</script>

<style>
@keyframes aiFadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php endif; ?>
