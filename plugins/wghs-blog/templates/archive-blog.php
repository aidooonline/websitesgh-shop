<?php
/**
 * WebsitesGH Shop Blog and Archive hub template.
 * Reuses the Aurora theme header, footer and component classes for a native look.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$tpgb_tag = isset( $_GET['tag'] ) ? sanitize_title( wp_unslash( $_GET['tag'] ) ) : '';
$tpgb_q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$tpgb_pg  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$tpgb_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => tpgb_per_page(),
	'paged'               => $tpgb_pg,
	'ignore_sticky_posts' => 1,
);
if ( $tpgb_tag ) {
	$tpgb_args['tax_query'] = array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tpgb_tag ) );
}
if ( '' !== $tpgb_q ) {
	$tpgb_args['s'] = $tpgb_q;
}

$tpgb_query   = new WP_Query( $tpgb_args );
$tpgb_filtered = ( $tpgb_tag || '' !== $tpgb_q || $tpgb_pg > 1 );
$tpgb_base_url = tpgb_page_url();

get_header();
?>
<div class="wrap py-14 tpgb">

	<header class="tpgb-head mb-8">
		<span class="eyebrow"><?php echo esc_html( tpgb_eyebrow() ); ?></span>
		<h1 class="section-title mt-2"><?php echo esc_html( get_the_title( tpgb_page_id() ) ); ?></h1>
		<p class="tpgb-intro"><?php echo esc_html( tpgb_intro() ); ?></p>
	</header>

	<div class="tpgb-toolbar">
		<form role="search" method="get" class="tpgb-search" action="<?php echo esc_url( $tpgb_base_url ); ?>">
			<span class="tpgb-search-ico" aria-hidden="true">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3" stroke-linecap="round"/></svg>
			</span>
			<input type="search" name="q" class="input tpgb-search-input" value="<?php echo esc_attr( $tpgb_q ); ?>" placeholder="<?php esc_attr_e( 'Search guides and reviews...', 'wghs-blog' ); ?>">
			<button type="submit" class="btn-primary tpgb-search-btn"><?php esc_html_e( 'Search', 'wghs-blog' ); ?></button>
		</form>

		<?php $tpgb_terms = tpgb_filter_terms( 8 ); ?>
		<?php if ( $tpgb_terms ) : ?>
		<nav class="tpgb-pills" aria-label="<?php esc_attr_e( 'Filter by topic', 'wghs-blog' ); ?>">
			<a class="chip tpgb-pill<?php echo ( ! $tpgb_tag ? ' tpgb-active' : '' ); ?>" href="<?php echo esc_url( $tpgb_base_url ); ?>"><?php esc_html_e( 'All', 'wghs-blog' ); ?></a>
			<?php foreach ( $tpgb_terms as $t ) : ?>
				<a class="chip tpgb-pill<?php echo ( $tpgb_tag === $t->slug ? ' tpgb-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( 'tag', $t->slug, $tpgb_base_url ) ); ?>"><?php echo esc_html( ucwords( $t->name ) ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $tpgb_q ) : ?>
		<p class="tpgb-resultline">
			<?php
			printf(
				/* translators: 1: result count, 2: search term */
				esc_html__( '%1$s result(s) for "%2$s"', 'wghs-blog' ),
				(int) $tpgb_query->found_posts,
				esc_html( $tpgb_q )
			);
			?>
			<a class="tpgb-clear" href="<?php echo esc_url( $tpgb_base_url ); ?>"><?php esc_html_e( 'clear', 'wghs-blog' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( $tpgb_query->have_posts() ) : ?>

		<?php
		$tpgb_i        = 0;
		$tpgb_feature  = ( ! $tpgb_filtered ); // Only feature on the clean first view.
		$tpgb_grid_open = false;
		while ( $tpgb_query->have_posts() ) : $tpgb_query->the_post();
			$tpgb_i++;
			$tpgb_term = tpgb_primary_term();
			$tpgb_rt   = tpgb_reading_time();

			if ( $tpgb_feature && 1 === $tpgb_i ) : ?>
				<article class="card-glow tpgb-featured">
					<a class="tpgb-featured-media" href="<?php the_permalink(); ?>"><?php tpgb_thumb( 'large', 'tpgb-thumb-img' ); ?></a>
					<div class="tpgb-featured-body">
						<div class="tpgb-meta">
							<?php if ( $tpgb_term ) : ?><span class="tpgb-badge"><?php echo esc_html( ucwords( $tpgb_term->name ) ); ?></span><?php endif; ?>
							<span><?php echo esc_html( get_the_date() ); ?></span>
							<span class="tpgb-dot">&middot;</span>
							<span><?php echo esc_html( $tpgb_rt ); ?> <?php esc_html_e( 'min read', 'wghs-blog' ); ?></span>
						</div>
						<a href="<?php the_permalink(); ?>"><h2 class="font-display font-semibold text-wgh-ink tpgb-featured-title"><?php the_title(); ?></h2></a>
						<p class="tpgb-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 40 ) ); ?></p>
						<a href="<?php the_permalink(); ?>" class="menu-link text-wgh-green tpgb-readmore"><?php esc_html_e( 'Read the full guide', 'wghs-blog' ); ?> &rarr;</a>
					</div>
				</article>
				<?php
				continue;
			endif;

			if ( ! $tpgb_grid_open ) {
				echo '<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 tpgb-grid">';
				$tpgb_grid_open = true;
			}
			?>
			<article class="card-glow tpgb-card">
				<a href="<?php the_permalink(); ?>" class="tpgb-card-media"><?php tpgb_thumb( 'large', 'tpgb-thumb-img' ); ?></a>
				<div class="tpgb-card-body">
					<div class="tpgb-meta">
						<?php if ( $tpgb_term ) : ?><span class="tpgb-badge"><?php echo esc_html( ucwords( $tpgb_term->name ) ); ?></span><?php endif; ?>
						<span><?php echo esc_html( get_the_date() ); ?></span>
						<span class="tpgb-dot">&middot;</span>
						<span><?php echo esc_html( $tpgb_rt ); ?> <?php esc_html_e( 'min', 'wghs-blog' ); ?></span>
					</div>
					<a href="<?php the_permalink(); ?>"><h2 class="font-display font-semibold text-wgh-ink tpgb-card-title"><?php the_title(); ?></h2></a>
					<p class="tpgb-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
					<a href="<?php the_permalink(); ?>" class="menu-link text-wgh-green tpgb-readmore"><?php esc_html_e( 'Read more', 'wghs-blog' ); ?> &rarr;</a>
				</div>
			</article>
		<?php endwhile; ?>
		<?php if ( $tpgb_grid_open ) { echo '</div>'; } ?>

		<?php
		$tpgb_total = (int) $tpgb_query->max_num_pages;
		if ( $tpgb_total > 1 ) :
			$tpgb_links = paginate_links( array(
				'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $tpgb_base_url ) ),
				'format'    => '',
				'current'   => $tpgb_pg,
				'total'     => $tpgb_total,
				'add_args'  => array_filter( array( 'tag' => $tpgb_tag, 'q' => $tpgb_q ) ),
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'type'      => 'plain',
				'mid_size'  => 1,
			) );
			if ( $tpgb_links ) {
				echo '<nav class="tpgb-pagination" aria-label="' . esc_attr__( 'Posts', 'wghs-blog' ) . '">' . $tpgb_links . '</nav>'; // phpcs:ignore
			}
		endif;
		?>

	<?php else : ?>
		<div class="card-glow tpgb-empty">
			<p class="text-wgh-ink font-display"><?php esc_html_e( 'Nothing matches that yet.', 'wghs-blog' ); ?></p>
			<p class="text-wgh-ink2"><?php esc_html_e( 'Try another topic, or browse everything.', 'wghs-blog' ); ?></p>
			<a class="btn-primary tpgb-empty-btn" href="<?php echo esc_url( $tpgb_base_url ); ?>"><?php esc_html_e( 'Browse all articles', 'wghs-blog' ); ?></a>
		</div>
	<?php endif; wp_reset_postdata(); ?>

</div>
<?php
get_footer();
