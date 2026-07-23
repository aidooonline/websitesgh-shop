<?php
/**
 * Template Name: Deals & Offers
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$on_sale = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
?>
<section class="bg-grid-faint bg-grid border-b border-wgh-line">
	<div class="wrap py-16 text-center">
		<span class="eyebrow">Limited stock</span>
		<h1 class="section-title mt-3">Deals &amp; Offers</h1>
		<p class="text-wgh-ink2 mt-4 max-w-xl mx-auto">Discounted units and bundles, updated weekly. When they're gone, they're gone.</p>
	</div>
</section>
<div class="wrap py-16">
	<?php
	while ( have_posts() ) : the_post();
		if ( trim( get_the_content() ) ) {
			echo '<div class="prose-aur max-w-none mb-10">';
			the_content();
			echo '</div>';
		}
	endwhile;

	if ( ! empty( $on_sale ) ) {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post__in'       => $on_sale,
			'posts_per_page' => 24,
			'post_status'    => 'publish',
		) );
		if ( $q->have_posts() ) {
			echo '<ul class="products">';
			while ( $q->have_posts() ) { $q->the_post(); wc_get_template_part( 'content', 'product' ); }
			echo '</ul>';
			wp_reset_postdata();
		}
	} else {
		echo '<div class="card p-10 text-center"><p class="text-wgh-ink2">No active deals right now. New offers drop weekly - check back soon or <a class="text-wgh-green" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">browse all laptops</a>.</p></div>';
	}
	?>
</div>
<?php get_footer();
