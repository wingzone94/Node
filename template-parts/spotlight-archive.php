<?php
/**
 * SPOTLIGHT 過去の特集一覧（/spotlight/）
 *
 * @package Node
 */

get_header();

$spotlight_cats = function_exists( 'node_get_spotlight_categories' ) ? node_get_spotlight_categories() : array();
$spotlight_cat  = get_category_by_slug( 'spotlight' );
?>

<main id="primary" class="site-main m3-spotlight-archive-layout">
	<?php node_the_breadcrumbs(); ?>

	<section class="m3-spotlight-archive-header m3-surface m3-section-spacing" aria-labelledby="spotlight-archive-title">
		<div class="m3-headlines__header m3-spotlight-archive-header__row">
			<h1 id="spotlight-archive-title" class="m3-headlines__title m3-section-title">
				<span class="material-symbols-outlined" aria-hidden="true">local_fire_department</span>
				SPOTLIGHT <span class="m3-section-title__sub">過去の特集</span>
			</h1>
		</div>
		<p class="m3-spotlight-archive-header__desc">
			<?php
			if ( $spotlight_cat && category_description( $spotlight_cat->term_id ) ) {
				echo category_description( $spotlight_cat->term_id );
			} else {
				esc_html_e( 'これまでに掲載した特集テーマです。各特集から記事を読むことができます。', 'node' );
			}
			?>
		</p>
	</section>

	<?php if ( ! empty( $spotlight_cats ) ) : ?>
		<section class="m3-spotlight-archive-catalog m3-surface m3-section-spacing" aria-labelledby="spotlight-catalog-title">
			<h2 id="spotlight-catalog-title" class="m3-spotlight-archive-pills__title">過去の特集一覧</h2>
			<div class="special-features__grid m3-spotlight-archive-grid">
				<?php foreach ( $spotlight_cats as $cat ) : ?>
					<a href="<?php echo esc_url( $cat['url'] ); ?>"
					   class="special-features__item m3-spotlight-archive-card m3-ripple-host"
					   style="--spotlight-color: <?php echo esc_attr( $cat['color'] ); ?>; background-color: <?php echo esc_attr( $cat['color'] ); ?>;"
					   aria-label="<?php echo esc_attr( $cat['name'] ); ?>へ">
						<div class="special-features__content">
							<?php if ( ! empty( $cat['count'] ) ) : ?>
								<span class="m3-spotlight-archive-card__count">
									<?php
									printf(
										/* translators: %d: number of posts */
										esc_html( _n( '%d 件', '%d 件', (int) $cat['count'], 'node' ) ),
										(int) $cat['count']
									);
									?>
								</span>
							<?php endif; ?>
							<h3 class="special-features__item-title"><?php echo esc_html( $cat['name'] ); ?></h3>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<div class="m3-no-results m3-spotlight-archive-empty m3-surface m3-section-spacing">
			<p>現在、掲載中の過去特集はありません。</p>
		</div>
	<?php endif; ?>
</main>

<?php
get_footer();
