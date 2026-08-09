<?php

declare( strict_types=1 );
/**
 * Luna Frontier 2.0 / SkyAlow — Reading Aside / 副紙面（Phase 5）
 *
 * 指示書 §26。
 * 通常の WordPress Sidebar ではなく「記事を読むための副紙面」。
 * 優先順位: TOC → Series → Library → Article context → Category archive。
 * Sticky にするのは原則 TOC だけ。
 *
 * DOM への差し込みは親テーマの luminous_after_article_header アクションを使う
 * （親 single.php を子でコピーせずに済ませるため）。配置は CSS Grid が担当する。
 *
 * @package LunaFrontier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 記事本文の直前に Reading Aside を出力する。
 */
function luna_frontier_render_reading_aside( int $post_id ): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$toc_items = function_exists( 'node_get_article_toc_items' )
		? node_get_article_toc_items( $post_id )
		: array();

	$series = function_exists( 'node_series_get_toc_data' )
		? node_series_get_toc_data( $post_id )
		: null;

	$categories = function_exists( 'node_get_post_categories_for_display' )
		? node_get_post_categories_for_display( $post_id )
		: array();

	// 中身が何も無いなら空の紙を作らない（§31 の「空 Surface 禁止」と同じ方針）。
	if ( empty( $toc_items ) && empty( $series['items'] ) && empty( $categories ) ) {
		return;
	}

	$current_page = function_exists( 'node_get_current_multipage_number' )
		? (int) node_get_current_multipage_number()
		: 1;
	?>
	<aside class="lf-reading-aside" aria-label="この記事の読み進め情報">

		<?php if ( ! empty( $toc_items ) ) : ?>
			<nav class="lf-aside-block lf-aside-block--toc" aria-labelledby="lf-aside-toc-title">
				<h2 class="lf-aside-block__title lf-system-label" id="lf-aside-toc-title">Contents</h2>
				<ol class="lf-toc">
					<?php foreach ( $toc_items as $item ) : ?>
						<?php
						$item_page = (int) ( $item['page'] ?? 1 );
						$anchor    = '#' . ltrim( (string) $item['id'], '#' );
						$href      = ( $item_page === $current_page || ! function_exists( 'node_get_multipage_url' ) )
							? $anchor
							: node_get_multipage_url( $item_page, $post_id ) . $anchor;
						?>
						<li class="lf-toc__item" data-level="<?php echo esc_attr( (string) $item['level'] ); ?>">
							<a class="lf-toc__link" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( (string) $item['text'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
		<?php endif; ?>

		<?php if ( ! empty( $series['items'] ) && is_array( $series['items'] ) ) : ?>
			<section class="lf-aside-block" aria-labelledby="lf-aside-series-title">
				<h2 class="lf-aside-block__title lf-system-label" id="lf-aside-series-title">Series</h2>
				<?php if ( ! empty( $series['term'] ) && $series['term'] instanceof WP_Term ) : ?>
					<p class="lf-aside-block__lead"><?php echo esc_html( $series['term']->name ); ?></p>
				<?php endif; ?>
				<ol class="lf-aside-list">
					<?php foreach ( $series['items'] as $series_item ) : ?>
						<li class="lf-aside-list__item<?php echo ! empty( $series_item['is_current'] ) ? ' is-current' : ''; ?>">
							<?php if ( ! empty( $series_item['is_current'] ) ) : ?>
								<span aria-current="true"><?php echo esc_html( (string) $series_item['title'] ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( (string) $series_item['url'] ); ?>"><?php echo esc_html( (string) $series_item['title'] ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $categories ) ) : ?>
			<section class="lf-aside-block" aria-labelledby="lf-aside-cat-title">
				<h2 class="lf-aside-block__title lf-system-label" id="lf-aside-cat-title">Category</h2>
				<ul class="lf-aside-list lf-aside-list--inline">
					<?php foreach ( $categories as $category ) : ?>
						<li class="lf-aside-list__item">
							<a href="<?php echo esc_url( (string) get_category_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>
	<?php
}
add_action( 'luminous_after_article_header', 'luna_frontier_render_reading_aside', 30 );
