<?php
// Interactivity: CERO Z 年齢確認 — スタブ
if ( ! defined( 'ABSPATH' ) ) exit;
function luminous_cero_z_render_dialog( int $post_id ): void {
	?>
	<dialog id="cero-z-dialog" class="node-dialog">
		<div class="node-dialog__content">
			<h2>年齢制限の確認</h2>
			<p>この記事には18歳以上の方のみ閲覧可能な表現が含まれています。</p>
			<div class="node-dialog__actions">
				<button id="cero-z-decline" class="m3-button m3-button--text">戻る</button>
				<button id="cero-z-accept" class="m3-button m3-button--filled">閲覧する</button>
			</div>
		</div>
	</dialog>
	<?php
}
