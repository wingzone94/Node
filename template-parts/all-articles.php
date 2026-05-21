<?php
get_header();

$paged            = max( 1, (int) get_query_var( 'paged' ) );
$per_page         = max( 1, (int) NODE_ALL_ARTICLES_PER_PAGE );
$total_limit      = max( 1, (int) NODE_ALL_ARTICLES_TOTAL_LIMIT );
$published_posts  = wp_count_posts( 'post' );
$total_published  = (int) ( $published_posts->publish ?? 0 );
$total_displaying = min( $total_published, $total_limit );
$total_pages      = max( 1, (int) ceil( $total_displaying / $per_page ) );

if ( $paged > $total_pages ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}

$offset     = ( $paged - 1 ) * $per_page;
$remaining  = max( 0, $total_displaying - $offset );
$page_count = min( $per_page, $remaining );

$articles_query = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'posts_per_page'      => $page_count,
		'offset'              => $offset,
		'no_found_rows'       => true,
	)
);

$archive_base = trailingslashit( node_get_all_articles_url() );
?>

<main id="primary" class="site-main">
	<?php node_the_breadcrumbs(); ?>

	<section class="m3-archive-header m3-surface m3-section-spacing" aria-labelledby="all-articles-title">
		<div class="m3-headlines__header" style="border-bottom: none; margin-bottom: 0;">
			<h1 id="all-articles-title" class="m3-headlines__title m3-section-title" style="margin-bottom: 0;">
				<span class="material-symbols-outlined" aria-hidden="true" style="font-size: 1.2em; vertical-align: middle;">article</span>
				ALL ARTICLES <span class="m3-section-title__sub">記事一覧</span>
			</h1>
		</div>
		<p class="m3-archive-header__desc" style="margin-top: 16px; color: var(--md-sys-color-on-surface-variant);">
			公開記事 <?php echo esc_html( number_format_i18n( $total_published ) ); ?> 件中、
			最新 <?php echo esc_html( number_format_i18n( $total_displaying ) ); ?> 件を表示しています。
		</p>
	</section>

	<div class="m3-post-grid">
		<?php if ( $articles_query->have_posts() ) : ?>
			<div class="m3-post-grid__container m3-post-grid--list">
				<?php
				while ( $articles_query->have_posts() ) :
					$articles_query->the_post();
					get_template_part(
						'template-parts/card',
						null,
						array(
							'card_class' => 'card-standard',
							'show_ai'    => false,
						)
					);
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<div class="m3-no-results">
				<p>現在、該当する記事はありません。</p>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="m3-navigation">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => esc_url_raw( $archive_base . '%_%' ),
						'format'    => 'page/%#%/',
						'current'   => $paged,
						'total'     => $total_pages,
						'mid_size'  => 2,
						'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
						'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
						'type'      => 'plain',
					)
				)
			);
			?>
		</div>
	<?php endif; ?>
</main>

<?php get_footer(); ?>
