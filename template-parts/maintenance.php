<?php
/**
 * メンテナンス画面。
 *
 * テーマのヘッダー・フッターやプラグイン由来のウィジェットに依存させず、
 * この1ファイルで完結させる（メンテナンス中＝どこかが不調な可能性がある場面のため、
 * 依存を増やすほど「メンテナンス画面自体が壊れる」危険が上がる）。
 * そのためスタイルもビルド済みCSSではなくインラインで持つ。
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$node_mt_message  = node_maintenance_get_message();
$node_mt_eta      = node_maintenance_get_eta();
$node_mt_progress = node_maintenance_get_progress();
$node_mt_is_admin = current_user_can( 'manage_options' );
$node_mt_logo     = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( 'メンテナンス中 – ' . get_bloginfo( 'name' ) ); ?></title>
	<style>
		:root {
			--mt-bg: #FFF8F0;
			--mt-surface: #FFFFFF;
			--mt-text: #3D2E1F;
			--mt-muted: #7A6A5A;
			--mt-primary: #FF9900;
			--mt-track: #F0E4D6;
			--mt-border: rgba(61, 46, 31, 0.10);
		}
		@media (prefers-color-scheme: dark) {
			:root {
				--mt-bg: #1A1512;
				--mt-surface: #241D18;
				--mt-text: #F5EDE4;
				--mt-muted: #B7A695;
				--mt-primary: #FFAA33;
				--mt-track: #3A2F26;
				--mt-border: rgba(245, 237, 228, 0.12);
			}
		}
		* { box-sizing: border-box; }
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
			background: var(--mt-bg);
			color: var(--mt-text);
			font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Noto Sans JP", "Yu Gothic", sans-serif;
			line-height: 1.8;
			-webkit-font-smoothing: antialiased;
		}
		.mt-card {
			width: 100%;
			max-width: 560px;
			background: var(--mt-surface);
			border: 1px solid var(--mt-border);
			border-radius: 28px;
			padding: 48px 40px;
			text-align: center;
			box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
		}
		.mt-logo { max-width: 180px; height: auto; margin: 0 auto 24px; display: block; }
		.mt-sitename {
			margin: 0 0 24px;
			font-size: 1.05rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			color: var(--mt-muted);
		}
		.mt-icon {
			width: 64px;
			height: 64px;
			margin: 0 auto 20px;
			display: block;
			color: var(--mt-primary);
			animation: mt-rotate 6s linear infinite;
		}
		@keyframes mt-rotate { to { transform: rotate(360deg); } }
		@media (prefers-reduced-motion: reduce) {
			.mt-icon { animation: none; }
		}
		h1 { margin: 0 0 16px; font-size: 1.6rem; font-weight: 900; letter-spacing: 0.02em; }
		.mt-message { margin: 0 0 32px; font-size: 1rem; color: var(--mt-muted); white-space: pre-wrap; }
		.mt-eta { margin: 0 0 8px; font-size: 0.85rem; font-weight: 700; color: var(--mt-muted); letter-spacing: 0.04em; }
		.mt-countdown {
			margin: 0 0 20px;
			font-size: 2rem;
			font-weight: 900;
			font-variant-numeric: tabular-nums;
			color: var(--mt-primary);
		}
		.mt-progress {
			width: 100%;
			height: 10px;
			border-radius: 999px;
			background: var(--mt-track);
			overflow: hidden;
		}
		.mt-progress__bar {
			height: 100%;
			border-radius: 999px;
			background: var(--mt-primary);
			transition: width 1s linear;
		}
		.mt-progress__labels {
			display: flex;
			justify-content: space-between;
			margin-top: 8px;
			font-size: 0.75rem;
			color: var(--mt-muted);
		}
		.mt-admin {
			margin-top: 36px;
			padding-top: 24px;
			border-top: 1px solid var(--mt-border);
		}
		.mt-admin__note { margin: 0 0 12px; font-size: 0.8rem; color: var(--mt-muted); }
		.mt-admin__link {
			display: inline-block;
			padding: 10px 24px;
			border-radius: 999px;
			background: var(--mt-primary);
			color: #FFFFFF;
			font-size: 0.9rem;
			font-weight: 700;
			text-decoration: none;
		}
		.mt-admin__link:hover { opacity: 0.85; }
		@media (max-width: 480px) {
			.mt-card { padding: 36px 24px; border-radius: 22px; }
			h1 { font-size: 1.35rem; }
			.mt-countdown { font-size: 1.6rem; }
		}
	</style>
</head>
<body>
	<main class="mt-card">
		<?php if ( '' !== $node_mt_logo ) : ?>
			<img class="mt-logo" src="<?php echo esc_url( $node_mt_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<?php else : ?>
			<p class="mt-sitename"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<?php endif; ?>

		<svg class="mt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<circle cx="12" cy="12" r="3"></circle>
			<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
		</svg>

		<h1>メンテナンス中です</h1>
		<p class="mt-message"><?php echo esc_html( $node_mt_message ); ?></p>

		<?php if ( null !== $node_mt_eta ) : ?>
			<p class="mt-eta">復旧予定 <?php echo esc_html( wp_date( 'n月j日 H:i', $node_mt_eta ) ); ?></p>
			<p class="mt-countdown" id="mt-countdown" data-eta="<?php echo esc_attr( (string) $node_mt_eta ); ?>" data-now="<?php echo esc_attr( (string) time() ); ?>">--:--</p>
			<?php if ( null !== $node_mt_progress ) : ?>
				<div
					class="mt-progress"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="<?php echo esc_attr( (string) $node_mt_progress ); ?>"
					aria-label="復旧予定までの進捗"
				>
					<div class="mt-progress__bar" id="mt-progress-bar" style="width: <?php echo esc_attr( (string) $node_mt_progress ); ?>%;"></div>
				</div>
				<div class="mt-progress__labels">
					<span>開始</span>
					<span>復旧予定</span>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $node_mt_is_admin ) : ?>
			<div class="mt-admin">
				<p class="mt-admin__note">管理者としてログイン中です。この画面は訪問者にも表示されています。</p>
				<a class="mt-admin__link" href="<?php echo esc_url( admin_url( 'options-general.php?page=luminous-settings#node-maintenance' ) ); ?>">管理画面へ移動してメンテナンスを解除</a>
			</div>
		<?php endif; ?>
	</main>

	<?php if ( null !== $node_mt_eta ) : ?>
	<script>
	( function () {
		var el = document.getElementById( 'mt-countdown' );
		if ( ! el ) { return; }

		var eta = parseInt( el.getAttribute( 'data-eta' ), 10 );
		// サーバー時刻とクライアント時刻のずれを補正する（端末の時計は当てにしない）。
		var offset = ( parseInt( el.getAttribute( 'data-now' ), 10 ) * 1000 ) - Date.now();
		var bar = document.getElementById( 'mt-progress-bar' );
		var startedAt = bar ? eta - ( eta - ( ( Date.now() + offset ) / 1000 ) ) / ( 1 - ( parseFloat( bar.style.width ) || 0 ) / 100 ) : null;

		function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }

		function tick() {
			var now = ( Date.now() + offset ) / 1000;
			var left = Math.max( 0, Math.floor( eta - now ) );

			if ( left <= 0 ) {
				el.textContent = 'まもなく復旧します';
				if ( bar ) { bar.style.width = '100%'; }
				// 復旧予定を過ぎたら定期的に再読み込みして復旧を検知する。
				setTimeout( function () { location.reload(); }, 30000 );
				return;
			}

			var h = Math.floor( left / 3600 );
			var m = Math.floor( ( left % 3600 ) / 60 );
			var s = left % 60;
			el.textContent = ( h > 0 ? h + ':' + pad( m ) : m ) + ':' + pad( s );

			if ( bar && startedAt && eta > startedAt ) {
				var pct = Math.max( 0, Math.min( 100, ( now - startedAt ) / ( eta - startedAt ) * 100 ) );
				bar.style.width = pct + '%';
				bar.parentElement.setAttribute( 'aria-valuenow', Math.round( pct ) );
			}

			setTimeout( tick, 1000 );
		}

		tick();
	} )();
	</script>
	<?php endif; ?>
</body>
</html>
