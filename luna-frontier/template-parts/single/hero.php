<?php

declare( strict_types=1 );
/**
 * Luna Frontier 2.0 / SkyAlow — Article Header（Phase 4）
 *
 * 親テーマ Node 1.3 の template-parts/single/hero.php を子テーマ側で置き換える。
 *
 * 指示書 §30 / §31:
 * - 情報を同じ視覚強度で並べない。優先順位は
 *     Category → Title → Published / Updated → 最小限のメタ → Intelligence Summary。
 * - Intelligence Summary は独立ブロックにせず、この Header の Paper に統合する。
 * - Summary が無いときは空の Surface / 罫 / 不自然な gap を作らない。
 * - Multipage では 1 ページ目だけに出す（親 single.php と同じ条件）。
 *
 * 既存メタ（_node_ai_summary / _node_ai_tone_color / _node_ai_keywords /
 * _node_linked_library_id 等）はそのまま読む。rename しない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lf_post_id   = get_the_ID();
$lf_has_thumb = has_post_thumbnail( $lf_post_id );

// 親 single.php と同じ Multipage 判定。
$lf_is_primary_page = ( 1 === max( 1, (int) get_query_var( 'page' ) ) );

$lf_summary = (string) get_post_meta( $lf_post_id, '_node_ai_summary', true );
$lf_show_summary = ( '' !== trim( $lf_summary ) ) && $lf_is_primary_page;

// Header 内で描画したことを、子の template-parts/ai-summary.php へ伝える（二重表示防止）。
if ( $lf_show_summary ) {
	$GLOBALS['luna_frontier_summary_rendered'] = true;
}

$lf_reading = function_exists( 'node_get_article_ranking_info' )
	? node_get_article_ranking_info( $lf_post_id )
	: array();

$lf_published = get_the_date( 'c', $lf_post_id );
$lf_modified  = get_the_modified_date( 'c', $lf_post_id );
$lf_has_update = ( get_the_date( 'Ymd', $lf_post_id ) !== get_the_modified_date( 'Ymd', $lf_post_id ) );

// Node Library への導線（既存メタをそのまま利用）。
$lf_library_id = absint( get_post_meta( $lf_post_id, '_node_linked_library_id', true ) );
if ( ! $lf_library_id ) {
	$lf_refs       = get_post_meta( $lf_post_id, '_node_library_card_reference', false );
	$lf_library_id = absint( $lf_refs[0] ?? 0 );
}
$lf_library = $lf_library_id ? get_post( $lf_library_id ) : null;
if ( ! $lf_library instanceof WP_Post || 'node_library' !== $lf_library->post_type || 'publish' !== $lf_library->post_status ) {
	$lf_library = null;
}
?>
<header class="lf-article-header<?php echo $lf_has_thumb ? ' lf-article-header--has-media' : ''; ?>">

	<?php if ( $lf_has_thumb ) : ?>
		<div class="lf-article-header__media">
			<?php
			the_post_thumbnail(
				'large',
				array(
					'class'    => 'lf-article-header__image',
					'decoding' => 'async',
					'fetchpriority' => 'high',
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="lf-article-header__inner">

		<?php // 1. Category（Wayfinding。カテゴリ固有色は Dynamic Color で上書きしない。§39） ?>
		<div class="lf-article-header__eyebrow">
			<?php node_the_category_labels(); ?>
		</div>

		<?php // 2. Title ?>
		<h1 class="lf-article-header__title" id="m3-hero-title"><?php the_title(); ?></h1>

		<?php // 3. Published / Updated ＋ 4. 最小限のメタ ?>
		<div class="lf-article-header__meta">
			<time class="lf-meta__item" datetime="<?php echo esc_attr( $lf_published ); ?>">
				<span class="lf-meta__label">公開</span>
				<?php echo esc_html( get_the_date( '', $lf_post_id ) ); ?>
			</time>

			<?php if ( $lf_has_update ) : ?>
				<time class="lf-meta__item" datetime="<?php echo esc_attr( $lf_modified ); ?>">
					<span class="lf-meta__label">更新</span>
					<?php echo esc_html( get_the_modified_date( '', $lf_post_id ) ); ?>
				</time>
			<?php endif; ?>

			<?php if ( ! empty( $lf_reading['chars'] ) && (int) $lf_reading['chars'] > 200 ) : ?>
				<span class="lf-meta__chip" data-rank="<?php echo esc_attr( (string) ( $lf_reading['rank'] ?? '' ) ); ?>">
					<?php echo esc_html( (string) ( $lf_reading['label'] ?? '' ) ); ?>
					<span class="lf-meta__chip-sub"><?php echo esc_html( sprintf( '約%s文字', number_format_i18n( (int) $lf_reading['chars'] ) ) ); ?></span>
				</span>
			<?php endif; ?>

			<?php $lf_author = trim( (string) get_the_author() ); ?>
			<?php if ( '' !== $lf_author ) : ?>
				<span class="lf-meta__item lf-meta__item--author">
					<span class="lf-meta__label">執筆</span>
					<?php echo esc_html( $lf_author ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $lf_library ) : ?>
				<a class="lf-meta__chip lf-meta__chip--link" href="<?php echo esc_url( get_permalink( $lf_library ) ); ?>">
					<span class="lf-system-label">Library</span>
					<?php echo esc_html( get_the_title( $lf_library ) ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php // Disclosure（AI / スポンサー）はメタより更に弱い強度で置く ?>
		<?php if ( function_exists( 'node_the_post_badges' ) ) : ?>
			<div class="lf-article-header__disclosure">
				<?php node_the_post_badges( $lf_post_id, 'compact', array( 'ai', 'sponsor' ) ); ?>
			</div>
		<?php endif; ?>

		<?php // 5. Intelligence Summary（Header の Paper へ統合。Title より目立たせない） ?>
		<?php if ( $lf_show_summary ) : ?>
			<details class="lf-intelligence-summary" id="m3-ai-summary" open>
				<summary class="lf-intelligence-summary__head">
					<span class="material-symbols-outlined lf-intelligence-summary__icon" aria-hidden="true">auto_awesome</span>
					<span class="lf-system-label">Intelligence Summary</span>
				</summary>
				<div class="lf-intelligence-summary__body">
					<p class="lf-intelligence-summary__text"><?php echo esc_html( wp_strip_all_tags( $lf_summary ) ); ?></p>
					<p class="lf-intelligence-summary__credit">by Gemini</p>
				</div>
			</details>
		<?php endif; ?>
	</div>
</header>
