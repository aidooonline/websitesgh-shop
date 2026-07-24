<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
?>
<section class="wrap py-20">
	<div class="card-glow relative overflow-hidden p-10 sm:p-16 text-center">
		<div class="absolute inset-0 bg-green-fade"></div>
		<div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[36rem] h-[36rem] rounded-full bg-wgh-green/15 blur-3xl pointer-events-none"></div>
		<div class="relative">
			<h2 class="section-title max-w-2xl mx-auto">Ready to <span class="text-wgh-green">order?</span></h2>
			<p class="text-wgh-ink2 mt-4 max-w-lg mx-auto">Find your next products today. New stock added every week.</p>
			<div class="mt-8 flex flex-wrap gap-3 justify-center">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="btn-primary">Shop products</a>
				
			</div>
		</div>
	</div>
</section>
