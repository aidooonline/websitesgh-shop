<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! taxonomy_exists( 'product_cat' ) ) { return; }
$cats = wghs_brand_terms( 6 );
if ( ! $cats ) { return; }
?>
<section class="wrap py-16 sm:py-20">
	<div class="flex items-end justify-between mb-8 gap-4">
		<div>
			<span class="eyebrow">Shop by brand</span>
			<h2 class="section-title mt-2">Choose your <span class="gradient-text">brand</span></h2>
		</div>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="menu-link hidden sm:block text-wgh-green">All laptops &rarr;</a>
	</div>
	<div class="flex gap-3 sm:gap-4 overflow-x-auto pb-2 -mx-5 px-5 sm:mx-0 sm:px-0 sm:overflow-visible [scrollbar-width:none] [-ms-overflow-style:none]">
		<?php foreach ( $cats as $cat ) :
			$thumb_id = (int) get_term_meta( $cat->term_id, 'thumbnail_id', true ); ?>
			<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="card-glow group shrink-0 w-20 sm:w-auto sm:flex-1 p-3 flex flex-col items-center text-center hover:-translate-y-1 hover:border-wgh-green/50 hover:shadow-glow transition">
				<span class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg overflow-hidden bg-gradient-to-br from-wgh-greenPale to-wgh-bg flex items-center justify-center mb-2">
					<?php wghs_image_or_placeholder( $thumb_id, 'medium', 'w-full h-full object-cover group-hover:scale-105 transition duration-500' ); ?>
				</span>
				<span class="font-display font-semibold text-xs text-wgh-ink group-hover:text-wgh-green transition-colors line-clamp-1"><?php echo esc_html( str_replace( ' Laptops', '', $cat->name ) ); ?></span>
				<span class="text-[10px] text-wgh-ink2 mt-0.5"><?php echo esc_html( $cat->count ); ?> in stock</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
