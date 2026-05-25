<?php
get_header();

$paged = max( 1, (int) get_query_var( 'paged' ) );
?>

<main id="primary" class="site-main">
	<?php node_the_breadcrumbs(); ?>

	<section class="m3-archive-header m3-surface m3-section-spacing" aria-labelledby="headline-archive-title">
		<div class="m3-headlines__header" style="border-bottom: none; margin-bottom: 0;">
			<h1 id="headline-archive-title" class="m3-headlines__title m3-section-title" style="margin-bottom: 0;">
				<span class="material-symbols-outlined" aria-hidden="true" style="font-size: 1.2em; vertical-align: middle;">campaign</span>
				HEADLINE <span class="m3-section-title__sub">速報</span>
			</h1>
		</div>
		<?php
		$news_cat = get_term_by( 'name', 'ニュース', 'category' );
		if ( $news_cat && category_description( $news_cat->term_id ) ) :
		?>
			<p class="m3-archive-header__desc" style="margin-top: 16px; color: var(--md-sys-color-on-surface-variant);">
				<?php echo category_description( $news_cat->term_id ); ?>
			</p>
		<?php endif; ?>
	</section>

	<div class="m3-post-grid">
		<?php if ( have_posts() ) : ?>
			<div class="m3-post-grid__container m3-post-grid--list m3-post-grid--2col-list">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part(
						'template-parts/card',
						null,
						array(
							'card_class' => 'card-standard',
							'show_ai'    => false,
						)
					);
				endwhile;
				?>
			</div>
		<?php else : ?>
			<div class="m3-no-results" style="padding: 24px;">
				<p>現在、該当する記事はありません。</p>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="m3-navigation">
			<?php
			the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
				'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
			) );
			?>
		</div>
	<?php endif; ?>
</main>

<?php get_footer(); ?>
