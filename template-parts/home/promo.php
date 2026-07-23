<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$img = (int) wghs_opt( 'wghs_promo_image' );
?>
<section class="wrap py-4 sm:py-6">
	<div class="card-glow relative grid lg:grid-cols-2 items-stretch overflow-hidden">
		<div class="absolute inset-0 bg-green-fade"></div>
		<div class="relative p-8 sm:p-12 flex flex-col justify-center">
			<span class="chip-grad mb-4 self-start">Deals &amp; Offers</span>
			<h2 class="section-title">This week&rsquo;s <span class="gradient-text">best prices</span></h2>
			<p class="text-wgh-ink2 mt-4 max-w-md leading-relaxed">Discounted units, bulk pricing for teams and student bundles. Limited stock, refreshed weekly.</p>
			<a href="<?php echo esc_url( home_url( '/deals' ) ); ?>" class="btn-amber mt-7 self-start">See current deals</a>
		</div>
		<div class="relative min-h-[240px] bg-gradient-to-br from-wgh-greenPale to-wgh-bg">
			<?php wghs_image_or_placeholder( $img, 'large', 'absolute inset-0 w-full h-full object-cover' ); ?>
		</div>
	</div>
</section>
