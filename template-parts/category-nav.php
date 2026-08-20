<?php

declare(strict_types=1);
/**
 * カテゴリから探す（トップページの回遊導線）
 *
 * トップから行ける先が SPOTLIGHT（特集3件）・HEADLINE（ニュース5件）・LATEST の
 * 記事リストしか無く、「Nintendo Switch の記事だけ読みたい」のような入り方が
 * サイト内に一本も存在しなかった。カード上のカテゴリバッジを踏む以外に
 * カテゴリアーカイブへ辿り着く手段が無い状態を解消する（Node 1.3.1 ユーザー指示）。
 *
 * 並びは記事数の多い順。編集で並べ替える必要が出たら
 * node_get_navigation_categories() 側を差し替える。
 *
 * @package Node
 */

$node_nav_categories = node_get_navigation_categories( NODE_CATEGORY_NAV_LIMIT );

if ( empty( $node_nav_categories ) ) {
	return;
}
?>
<section id="categories" class="m3-category-nav m3-surface m3-section-spacing" aria-labelledby="category-nav-title">
	<div class="m3-headlines__header m3-category-nav__header">
		<h2 id="category-nav-title" class="m3-section-title m3-headlines__title">
			<span class="material-symbols-outlined" aria-hidden="true">category</span>
			CATEGORY <span class="m3-section-title__sub">カテゴリから探す</span>
		</h2>
	</div>

	<ul class="m3-category-nav__list">
		<?php foreach ( $node_nav_categories as $node_nav_category ) : ?>
			<?php $node_nav_color = node_get_category_color( $node_nav_category ); ?>
			<li class="m3-category-nav__item">
				<a href="<?php echo esc_url( (string) get_category_link( $node_nav_category ) ); ?>"
				   class="m3-category-nav__pill m3-ripple-host"
				   style="--node-category-nav-color: <?php echo esc_attr( $node_nav_color ?: 'var(--md-sys-color-primary)' ); ?>;">
					<span class="m3-category-nav__dot" aria-hidden="true"></span>
					<span class="m3-category-nav__name"><?php echo esc_html( $node_nav_category->name ); ?></span>
					<span class="m3-category-nav__count"><?php echo esc_html( number_format_i18n( (int) $node_nav_category->count ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
