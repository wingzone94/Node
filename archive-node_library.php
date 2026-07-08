<?php
/**
 * Archive template for Node Library.
 *
 * @package Node
 */

get_header();
?>

<main id="primary" class="site-main m3-archive-layout">
	<?php node_the_breadcrumbs(); ?>

	<?php get_template_part( 'template-parts/archive/header' ); ?>

	<section class="m3-archive-feed" aria-label="ライブラリ一覧">
		<?php if ( have_posts() ) : ?>
			<div class="m3-post-grid m3-archive-post-grid">
				<div class="m3-post-grid__container m3-archive-post-grid__cards m3-post-grid--list">
					<?php
					while ( have_posts() ) :
						the_post();
						$lib_id    = get_the_ID();
						$game_info = array(
							'title'   => get_the_title(),
							'type'    => get_post_meta( $lib_id, '_node_library_type', true ) ?: 'game',
							'summary' => get_post_meta( $lib_id, '_node_library_summary', true ),
							'links'   => get_post_meta( $lib_id, '_node_library_links', true ),
						);
						
						if ( defined( 'NODE_LIBRARY_DIR' ) ) {
							include NODE_LIBRARY_DIR . 'templates/card-library.php';
						}
					endwhile;
					?>
				</div>
			</div>
		<?php else : ?>
			<div class="m3-no-results m3-archive-no-results">
				<div class="m3-no-results__inner">
					<div class="m3-no-results__icon">
						<span class="material-symbols-outlined">inbox</span>
					</div>
					<p class="m3-no-results__text"><?php esc_html_e( '現在、該当するライブラリ項目はありません。', 'node' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<?php get_template_part( 'template-parts/archive/pagination' ); ?>
</main>

<?php
get_footer();
