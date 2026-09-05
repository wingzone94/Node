<?php

declare( strict_types=1 );
/**
 * Luna Frontier 2.0 / SkyAlow — Topic Nav
 *
 * ヘッダーの真下に置く、主要トピックと特集への導線。
 *
 * 編集部おすすめの棚に特集とトピックを統合。標準は特集4件を優先し、合計6件まで。
 * 特集は専用メニューを優先し、未設定なら親テーマのSPOTLIGHTを使う。
 *
 * SPOTLIGHT はここへ統合したので、ホームの独立セクションは表示しない
 * （同じリンクを 1 ページに二度出さない。CSS 側で非表示にしている）。
 *
 * トピックの項目は管理画面（外観 → メニュー）の「トピック（Luna Frontier）」で編集できる。
 * 未設定のあいだは記事数の多い上位カテゴリで自動的に埋める。
 *
 * ブランドクロームの一部なので Dynamic Color は流し込まない（§23 / §38）。
 * リンクの羅列なので JS は使わない。
 *
 * @package LunaFrontier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * トピック名から Material Symbols のアイコン名を決める。
 *
 * スラッグは日本語がパーセントエンコードされていて鍵に使えないため、
 * 表示名のキーワードで判定する。`luna_frontier_topic_icon` で差し替え可能。
 */
function luna_frontier_topic_icon( string $label ): string {
	$map = array(
		'AI'         => 'auto_awesome', // Gemini 風の 4 方向スパークル
		'ゲーム'     => 'sports_esports',
		'ガジェット' => 'devices',
		'ニュース'   => 'newspaper',
		'スマート'   => 'smartphone',
		'PC'         => 'computer',
		'音楽'       => 'music_note',
		'動画'       => 'movie',
		'小説'       => 'menu_book',
		'フード'     => 'restaurant',
		'雑記'       => 'edit_note',
	);

	$icon = 'label';

	foreach ( $map as $needle => $candidate ) {
		if ( false !== mb_stripos( $label, $needle ) ) {
			$icon = $candidate;
			break;
		}
	}

	return (string) apply_filters( 'luna_frontier_topic_icon', $icon, $label );
}

$lf_topics = array();

if ( has_nav_menu( 'luna_topics' ) ) {
	$lf_menu_id = (int) ( get_nav_menu_locations()['luna_topics'] ?? 0 );

	foreach ( (array) wp_get_nav_menu_items( $lf_menu_id ) as $lf_item ) {
		if ( ! $lf_item instanceof WP_Post || (int) $lf_item->menu_item_parent ) {
			continue;
		}

		$lf_topics[] = array(
			'label'   => (string) $lf_item->title,
			'url'     => (string) $lf_item->url,
			'current' => ( 'taxonomy' === $lf_item->type && is_category( (int) $lf_item->object_id ) ),
		);
	}
} else {
	$lf_terms = get_categories(
		array(
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 6,
			'exclude'    => array( (int) get_option( 'default_category' ) ),
		)
	);

	foreach ( $lf_terms as $lf_term ) {
		$lf_topics[] = array(
			'label'   => $lf_term->name,
			'url'     => (string) get_category_link( $lf_term ),
			'current' => is_category( $lf_term->term_id ),
		);
	}
}

$lf_limit = max( 1, min( 6, (int) apply_filters( 'luna_frontier_recommendation_limit', 6 ) ) );
$lf_feature_limit = max( 0, min( 4, $lf_limit, (int) apply_filters( 'luna_frontier_spotlight_limit', 4 ) ) );
$lf_spotlight = array();

if ( has_nav_menu( 'luna_spotlight' ) ) {
	$lf_menu_id = (int) ( get_nav_menu_locations()['luna_spotlight'] ?? 0 );
	foreach ( (array) wp_get_nav_menu_items( $lf_menu_id ) as $lf_item ) {
		if ( $lf_item instanceof WP_Post && ! (int) $lf_item->menu_item_parent ) {
			$lf_spotlight[] = array( 'name' => $lf_item->title, 'url' => $lf_item->url );
		}
	}
} elseif ( function_exists( 'node_get_spotlight_categories' ) ) {
	$lf_spotlight = node_get_spotlight_categories();
}

$lf_current_term = get_queried_object();
$lf_current_url = $lf_current_term instanceof WP_Term ? get_term_link( $lf_current_term ) : '';
$lf_current_url = is_wp_error( $lf_current_url ) ? '' : untrailingslashit( $lf_current_url );

// 特集を先に採用し、同じURLのトピックを重複表示しない。
$lf_recommendations = array();
$lf_seen = array();
$lf_feature_count = 0;
foreach ( $lf_spotlight as $lf_feature ) {
	$lf_url = esc_url_raw( (string) $lf_feature['url'] );
	$lf_key = untrailingslashit( $lf_url );
	if ( $lf_feature_count >= $lf_feature_limit ) {
		break;
	}
	if ( '' === $lf_url || isset( $lf_seen[ $lf_key ] ) ) {
		continue;
	}
	$lf_seen[ $lf_key ] = true;
	$lf_recommendations[] = array(
		'label' => (string) $lf_feature['name'],
		'url' => $lf_url,
		'current' => $lf_current_url === $lf_key,
		'feature' => true,
	);
	++$lf_feature_count;
}
foreach ( $lf_topics as $lf_topic ) {
	$lf_url = esc_url_raw( $lf_topic['url'] );
	$lf_key = untrailingslashit( $lf_url );
	if ( count( $lf_recommendations ) >= $lf_limit ) {
		break;
	}
	if ( '' === $lf_url || isset( $lf_seen[ $lf_key ] ) ) {
		continue;
	}
	$lf_seen[ $lf_key ] = true;
	$lf_recommendations[] = array_merge( $lf_topic, array( 'url' => $lf_url, 'feature' => false ) );
}
if ( empty( $lf_recommendations ) ) {
	return;
}
?>
<nav class="lf-topic-nav" aria-label="編集部おすすめ">
	<div class="lf-topic-nav__inner">
		<div class="lf-topic-nav__group lf-topic-nav__group--recommendations<?php echo $lf_feature_count ? ' lf-topic-nav__group--spotlight' : ''; ?>">
			<span class="lf-topic-nav__pick lf-topic-nav__pick--editors">編集部おすすめ</span>
			<ul class="lf-topic-nav__list">
				<?php foreach ( $lf_recommendations as $lf_item ) : ?>
					<li class="lf-topic-nav__item<?php echo $lf_item['feature'] ? ' lf-topic-nav__item--feature' : ''; ?><?php echo $lf_item['current'] ? ' is-current' : ''; ?>">
						<a href="<?php echo esc_url( $lf_item['url'] ); ?>"<?php echo $lf_item['current'] ? ' aria-current="page"' : ''; ?>>
							<?php if ( $lf_item['feature'] ) : ?>
								<span class="lf-topic-nav__eyebrow">SPOTLIGHT</span>
							<?php else : ?>
								<span class="material-symbols-outlined lf-topic-nav__icon" aria-hidden="true"><?php echo esc_html( luna_frontier_topic_icon( $lf_item['label'] ) ); ?></span>
							<?php endif; ?>
							<span class="lf-topic-nav__label"><?php echo esc_html( $lf_item['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</nav>
