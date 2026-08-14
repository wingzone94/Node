<?php
/**
 * Generic archive template (category, tag, author, date, taxonomy).
 *
 * @package Node
 */

get_header();
?>

<main id="primary" class="site-main m3-archive-layout">
	<?php node_the_breadcrumbs(); ?>

	<?php get_template_part( 'template-parts/archive/header' ); ?>

	<?php
	get_template_part(
		'template-parts/archive/loop',
		null,
		array(
			'featured_first' => true,
		)
	);
	?>

	<?php get_template_part( 'template-parts/archive/pagination' ); ?>
</main>

<?php
get_footer();
