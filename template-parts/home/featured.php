<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_products' ) ) { return; }
$q = new WP_Query( array( 'post_type'=>'product','post_status'=>'publish','posts_per_page'=>8,
	'tax_query'=>array(array('taxonomy'=>'product_visibility','field'=>'name','terms'=>'featured')) ) );
if ( ! $q->have_posts() ) { wp_reset_postdata(); $q = new WP_Query( array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>8,'orderby'=>'date','order'=>'DESC') ); }
if ( ! $q->have_posts() ) { wp_reset_postdata(); return; }
?>
<section class="wrap py-16 sm:py-20 border-t border-wgh-line">
	<div class="flex items-end justify-between mb-8 gap-4">
		<div><span class="eyebrow">Hand-picked</span><h2 class="section-title mt-2">Featured <span class="text-wgh-green">products</span></h2></div>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="menu-link hidden sm:block text-wgh-green">Shop all &rarr;</a>
	</div>
	<ul class="products">
		<?php while ( $q->have_posts() ) { $q->the_post(); wc_get_template_part( 'content', 'product' ); } wp_reset_postdata(); ?>
	</ul>
</section>
