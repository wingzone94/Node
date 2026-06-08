<?php
/**
 * ニュース（HEADLINE）カテゴリ専用アーカイブテンプレート
 *
 * @package Node
 */

get_header();
?>

<main id="primary" class="site-main m3-archive-layout">
	<?php node_the_breadcrumbs(); ?>

	<?php
	get_template_part(
		'template-parts/archive/header',
		null,
		array(
			'title'     => 'HEADLINE',
			'subtitle'  => '速報',
			'header_id' => 'headline-archive-title',
			'type'      => 'category',
		)
	);
	?>

	<section class="m3-headlines m3-surface m3-section-spacing m3-archive-headlines">
		<div class="m3-headlines__list" role="list">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card-headline' );
				endwhile;
				?>
			<?php else : ?>
				<div class="m3-no-results m3-archive-no-results">
					<p><?php esc_html_e( '現在、ニュース記事はありません。', 'node' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php get_template_part( 'template-parts/archive/pagination' ); ?>
</main>

<?php
get_footer();
