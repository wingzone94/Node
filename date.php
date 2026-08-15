<?php
/**
 * Date archive template (year / month / day).
 *
 * @package Node
 */

get_header();

$node_date_map     = node_get_date_archive_map();
$node_date_queried = node_get_queried_date_archive();
$node_date_year    = $node_date_queried['year'];
$node_date_month   = $node_date_queried['month'];
$node_date_day     = $node_date_queried['day'];

if ( is_day() ) {
	$node_date_view = 'day';
} elseif ( is_month() ) {
	$node_date_view = 'month';
} else {
	$node_date_view = 'year';
}

$node_date_count = isset( $GLOBALS['wp_query']->found_posts ) ? (int) $GLOBALS['wp_query']->found_posts : 0;

if ( 'day' === $node_date_view ) {
	$node_date_title = sprintf( '%d年%d月%d日', $node_date_year, $node_date_month, $node_date_day );
} elseif ( 'month' === $node_date_view ) {
	$node_date_title = sprintf( '%d年%d月', $node_date_year, $node_date_month );
} else {
	$node_date_title = sprintf( '%d年', $node_date_year );
}

// Adjacent periods.
$node_date_prev = null;
$node_date_next = null;
if ( 'year' === $node_date_view ) {
	$node_date_prev = node_get_adjacent_archive_year( $node_date_year, -1 );
	$node_date_next = node_get_adjacent_archive_year( $node_date_year, 1 );
} elseif ( 'month' === $node_date_view ) {
	$node_date_prev = node_get_adjacent_archive_month( $node_date_year, $node_date_month, -1 );
	$node_date_next = node_get_adjacent_archive_month( $node_date_year, $node_date_month, 1 );
}

// Current sort.
$node_date_sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : '';
$node_date_sort_options = array(
	''              => '投稿日の新しい順',
	'date-asc'      => '投稿日の古い順',
	'modified-desc' => '更新日の新しい順',
	'modified-asc'  => '更新日の古い順',
);
$node_date_base_url = '';
if ( 'day' === $node_date_view ) {
	$node_date_base_url = get_day_link( $node_date_year, $node_date_month, $node_date_day );
} elseif ( 'month' === $node_date_view ) {
	$node_date_base_url = get_month_link( $node_date_year, $node_date_month );
} else {
	$node_date_base_url = get_year_link( $node_date_year );
}

// Archive jump options (year > months) — title doubles as navigator.
$node_date_dropdown = array();
$node_date_years_desc = array_reverse( array_keys( $node_date_map ), true );
foreach ( $node_date_years_desc as $node_date_y ) {
	$node_date_dropdown[] = array(
		'url'     => get_year_link( $node_date_y ),
		'label'   => sprintf( '%d年（%d件）', $node_date_y, array_sum( $node_date_map[ $node_date_y ] ) ),
		'current' => ( 'year' === $node_date_view && $node_date_y === $node_date_year ),
		'group'   => false,
	);
	$months_desc = array_reverse( array_keys( $node_date_map[ $node_date_y ] ), true );
	foreach ( $months_desc as $node_date_m ) {
		$node_date_dropdown[] = array(
			'url'     => get_month_link( $node_date_y, $node_date_m ),
			'label'   => sprintf( '%d月（%d件）', $node_date_m, $node_date_map[ $node_date_y ][ $node_date_m ] ),
			'current' => ( 'month' === $node_date_view && $node_date_y === $node_date_year && $node_date_m === $node_date_month ),
			'group'   => $node_date_y,
		);
	}
}
?>

<main id="primary" class="site-main m3-date-archive">

	<?php node_the_breadcrumbs(); ?>

	<div class="m3-date-bar">
		<?php if ( $node_date_prev ) : ?>
			<a class="m3-date-bar__nav" href="<?php echo esc_url( 'year' === $node_date_view ? get_year_link( $node_date_prev['year'] ) : get_month_link( $node_date_prev['year'], $node_date_prev['month'] ) ); ?>" aria-label="前の期間">
				<span class="material-symbols-outlined">chevron_left</span>
			</a>
		<?php endif; ?>

		<div class="m3-date-bar__title-wrap">
			<h1 class="m3-date-bar__title"><?php echo esc_html( $node_date_title ); ?></h1>
			<select class="m3-date-bar__title-select" data-date-jump aria-label="アーカイブ一覧">
				<?php
				$node_date_cur_group = null;
				foreach ( $node_date_dropdown as $node_date_opt ) :
					if ( false === $node_date_opt['group'] ) :
						if ( null !== $node_date_cur_group ) {
							echo '</optgroup>';
						}
						$node_date_cur_group = null;
						?>
						<option value="<?php echo esc_attr( $node_date_opt['url'] ); ?>"<?php echo $node_date_opt['current'] ? ' selected' : ''; ?>>
							<?php echo esc_html( $node_date_opt['label'] ); ?>
						</option>
						<?php
					else :
						if ( $node_date_opt['group'] !== $node_date_cur_group ) {
							if ( null !== $node_date_cur_group ) {
								echo '</optgroup>';
							}
							$node_date_cur_group = $node_date_opt['group'];
							echo '<optgroup label="' . esc_attr( $node_date_cur_group . '年' ) . '">';
						}
						?>
						<option value="<?php echo esc_attr( $node_date_opt['url'] ); ?>"<?php echo $node_date_opt['current'] ? ' selected' : ''; ?>>
							<?php echo esc_html( $node_date_opt['label'] ); ?>
						</option>
						<?php
					endif;
				endforeach;
				if ( null !== $node_date_cur_group ) {
					echo '</optgroup>';
				}
				?>
			</select>
			<span class="material-symbols-outlined m3-date-bar__title-chevron" aria-hidden="true">expand_more</span>
		</div>

		<span class="m3-date-bar__count"><?php echo esc_html( $node_date_count ); ?>件</span>

		<?php if ( $node_date_next ) : ?>
			<a class="m3-date-bar__nav" href="<?php echo esc_url( 'year' === $node_date_view ? get_year_link( $node_date_next['year'] ) : get_month_link( $node_date_next['year'], $node_date_next['month'] ) ); ?>" aria-label="次の期間">
				<span class="material-symbols-outlined">chevron_right</span>
			</a>
		<?php endif; ?>

		<span class="m3-date-bar__spacer"></span>

		<div class="m3-date-sort">
			<span class="material-symbols-outlined m3-date-sort__icon" aria-hidden="true">sort</span>
			<select class="m3-date-sort__select" data-date-sort aria-label="並べ替え">
				<?php foreach ( $node_date_sort_options as $node_sort_val => $node_sort_label ) : ?>
					<option value="<?php echo esc_attr( $node_sort_val ? add_query_arg( 'sort', $node_sort_val, $node_date_base_url ) : $node_date_base_url ); ?>"<?php echo $node_date_sort === $node_sort_val ? ' selected' : ''; ?>>
						<?php echo esc_html( $node_sort_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="m3-date-bar__toggle" role="radiogroup" aria-label="表示切替">
			<button type="button" class="m3-date-toggle" data-date-view="grid" aria-pressed="false" aria-label="グリッド表示">
				<span class="material-symbols-outlined">grid_view</span>
			</button>
			<button type="button" class="m3-date-toggle is-active" data-date-view="list" aria-pressed="true" aria-label="リスト表示">
				<span class="material-symbols-outlined">view_list</span>
			</button>
		</div>
	</div>

	<section class="m3-date-feed" aria-label="<?php echo esc_attr( $node_date_title . 'の記事' ); ?>" data-date-feed>
		<?php if ( have_posts() ) : ?>
			<?php
			// 日付ごとに記事をグルーピング（クエリ順を保持）。
			$node_date_groups = array();
			while ( have_posts() ) :
				the_post();
				$node_g_key = get_the_date( 'Y-m-d' );
				if ( ! isset( $node_date_groups[ $node_g_key ] ) ) {
					$node_date_groups[ $node_g_key ] = array(
						'label' => get_the_date( 'Y年n月j日' ),
						'posts' => array(),
					);
				}
				$node_g_cats    = get_the_category();
				$node_g_primary = function_exists( 'node_get_primary_category' ) ? node_get_primary_category() : ( $node_g_cats[0] ?? null );
				$node_g_props   = ( $node_g_primary && function_exists( 'node_get_category_label_props' ) )
					? node_get_category_label_props( $node_g_primary )
					: null;
				$node_date_groups[ $node_g_key ]['posts'][] = array(
					'url'       => get_permalink(),
					'title'     => get_the_title(),
					'primary'   => $node_g_primary,
					'color'     => $node_g_props['color'] ?? '',
					'on_color'  => $node_g_props['on_color'] ?? ( $node_g_props['text'] ?? '' ),
				);
			endwhile;
			rewind_posts();
			?>

			<div class="m3-post-grid m3-archive-post-grid" data-date-grid hidden>
				<div class="m3-post-grid__container m3-archive-post-grid__cards">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/card', null, array(
							'card_class' => 'card-standard',
						) );
					endwhile;
					?>
				</div>
			</div>

			<div class="m3-date-groups" data-date-list>
				<?php foreach ( $node_date_groups as $node_g ) : ?>
					<section class="m3-date-group">
						<header class="m3-date-group__header">
							<span class="m3-date-group__icon" aria-hidden="true">
								<span class="material-symbols-outlined">calendar_today</span>
							</span>
							<h2 class="m3-date-group__title"><?php echo esc_html( $node_g['label'] ); ?></h2>
							<span class="m3-date-group__count"><?php echo count( $node_g['posts'] ); ?>件</span>
						</header>

						<div class="m3-date-group__items">
							<?php foreach ( $node_g['posts'] as $node_p ) :
								$node_p_untitled = '' === trim( $node_p['title'] );
								$node_p_style    = $node_p['color']
									? sprintf( '--date-item-accent:%s;', esc_attr( $node_p['color'] ) )
									: '';
							?>
								<a class="m3-date-item<?php echo $node_p['color'] ? '' : ' m3-date-item--no-cat'; ?>"
								   href="<?php echo esc_url( $node_p['url'] ); ?>"
								   <?php echo $node_p_style ? 'style="' . $node_p_style . '"' : ''; ?>>
									<span class="m3-date-item__body">
										<span class="m3-date-item__title<?php echo $node_p_untitled ? ' is-untitled' : ''; ?>">
											<?php echo $node_p_untitled ? 'タイトル未設定' : esc_html( $node_p['title'] ); ?>
										</span>
										<?php if ( $node_p['primary'] ) : ?>
											<span class="m3-date-item__pill"><?php echo esc_html( $node_p['primary']->name ); ?></span>
										<?php endif; ?>
									</span>
									<span class="material-symbols-outlined m3-date-item__chevron" aria-hidden="true">chevron_right</span>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="m3-no-results m3-archive-no-results">
				<div class="m3-no-results__inner">
					<div class="m3-no-results__icon">
						<span class="material-symbols-outlined">inbox</span>
					</div>
					<p class="m3-no-results__text"><?php esc_html_e( 'この期間に公開された記事はありません。', 'node' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<?php get_template_part( 'template-parts/archive/pagination' ); ?>
</main>

<script>
(function(){
	var grid = document.querySelector('[data-date-grid]');
	var list = document.querySelector('[data-date-list]');
	var btns = document.querySelectorAll('[data-date-view]');
	if (grid && list && btns.length) {
		var key = 'node-date-view';
		var saved = localStorage.getItem(key) || 'list';
		function apply(mode) {
			if (mode !== 'grid') mode = 'list';
			grid.hidden = mode !== 'grid';
			list.hidden = mode !== 'list';
			btns.forEach(function(b) {
				var active = b.getAttribute('data-date-view') === mode;
				b.classList.toggle('is-active', active);
				b.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			localStorage.setItem(key, mode);
		}
		apply(saved);
		btns.forEach(function(b) {
			b.addEventListener('click', function() { apply(b.getAttribute('data-date-view')); });
		});
	}
	document.querySelectorAll('[data-date-jump],[data-date-sort]').forEach(function(sel) {
		sel.addEventListener('change', function() {
			if (this.value) window.location.href = this.value;
		});
	});
})();
</script>

<?php
get_footer();
