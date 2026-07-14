<?php
/**
 * 404 page template.
 *
 * @package Node
 */

get_header();
?>
<main id="primary" class="site-main m3-home-layout">
	<?php node_the_breadcrumbs(); ?>

	<section class="m3-404-lite m3-surface m3-section-spacing" aria-labelledby="node-404-title">
		<p class="m3-404-lite__code">404</p>
		<h1 id="node-404-title" class="m3-404-lite__title">ページが見つかりませんでした</h1>
		<p class="m3-404-lite__message">URLが変更されたか、ページが削除された可能性があります。</p>

		<div class="m3-404-lite__search">
			<form role="search" method="get" class="m3-404-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" class="m3-404-search__field" placeholder="キーワードで検索" value="" name="s" />
				<button type="submit" class="m3-404-search__submit" aria-label="検索">
					<span class="material-symbols-outlined" aria-hidden="true">search</span>
				</button>
			</form>
		</div>

		<nav class="m3-404-lite__links" aria-label="主要ページ">
			<a class="m3-button m3-button--filled" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="material-symbols-outlined" aria-hidden="true">home</span>
				ホームに戻る
			</a>
		</nav>
	</section>
</main>
<?php get_footer(); ?>
