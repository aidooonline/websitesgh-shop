<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
// Product-grid banner: a few in-stock products with quick specs.
$grid = array();
if ( function_exists( 'wc_get_products' ) ) {
	$ids = get_posts( array( 'post_type'=>'product','post_status'=>'publish','posts_per_page'=>4,'fields'=>'ids',
		'tax_query'=>array(array('taxonomy'=>'product_visibility','field'=>'name','terms'=>'featured')) ) );
	if ( ! $ids ) { $ids = get_posts( array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>4,'fields'=>'ids','orderby'=>'date','order'=>'DESC') ); }
	foreach ( $ids as $pid ) { $p = wc_get_product( $pid ); if ( $p ) { $grid[] = $p; } }
}
?>
<section class="relative overflow-hidden border-b border-wgh-line bg-grid">
	<div class="absolute -top-40 -left-32 w-[44rem] h-[44rem] rounded-full bg-wgh-green/10 blur-3xl pointer-events-none"></div>
	<div class="absolute -bottom-40 -right-32 w-[40rem] h-[40rem] rounded-full bg-wgh-greenV/10 blur-3xl pointer-events-none"></div>
	<div class="wrap relative grid lg:grid-cols-2 gap-12 items-center py-14 sm:py-20">
		<div class="animate-riseIn">
			<span class="chip-grad mb-5"><?php echo esc_html( wghs_opt( 'wghs_hero_eyebrow', 'Plug Into Quality' ) ); ?></span>
			<h1 class="text-4xl sm:text-5xl font-bold leading-[1.08]">
				<?php echo esc_html( wghs_opt( 'wghs_hero_title', 'Premium UK Used Laptops' ) ); ?>
				<span class="gradient-text"><?php echo esc_html( wghs_opt( 'wghs_hero_title2', 'Delivered Across Ghana.' ) ); ?></span>
			</h1>
			<p class="mt-5 text-wgh-ink2 text-base sm:text-lg max-w-xl leading-relaxed">
				<?php echo esc_html( wghs_opt( 'wghs_hero_sub', 'Tested, graded and warranty-backed HP, Dell and Lenovo business laptops. Pay by MoMo, bank transfer or on delivery in Accra.' ) ); ?>
			</p>
			<div class="mt-8 flex flex-wrap gap-3">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="btn-primary">Browse laptops
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<a href="<?php echo esc_url( home_url( '/how-to-order' ) ); ?>" class="btn-ghost">How to order</a>
			</div>
			<div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 text-sm text-wgh-ink2">
				<span class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> Tested &amp; graded</span>
				<span class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> 1&ndash;3 month warranty</span>
				<span class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> Pay on delivery (Accra)</span>
			</div>
		</div>

		<!-- Product-grid banner -->
		<div class="animate-riseIn" style="animation-delay:.12s">
			<?php if ( $grid ) : ?>
			<div class="grid grid-cols-2 gap-3 sm:gap-4">
				<?php foreach ( $grid as $i => $p ) :
					$img = get_the_post_thumbnail_url( $p->get_id(), 'large' ); ?>
					<a href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>"
						class="card-glow group p-3 flex flex-col hover:border-wgh-green/50 hover:shadow-glow transition<?php echo 0 === $i ? ' animate-floaty' : ''; ?>">
						<span class="relative block aspect-[4/3] rounded-lg overflow-hidden bg-gradient-to-br from-wgh-greenPale to-wgh-bg">
							<?php if ( $img ) : ?>
								<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p->get_name() ); ?>" class="w-full h-full object-contain p-2 group-hover:scale-105 transition duration-500" loading="lazy">
							<?php else : ?>
								<?php echo wghs_placeholder_svg( 'w-full h-full' ); ?>
							<?php endif; ?>
						</span>
						<span class="mt-2.5 font-display text-xs font-semibold text-wgh-ink leading-snug line-clamp-2"><?php echo esc_html( $p->get_name() ); ?></span>
						<span class="mt-1 gradient-text font-display font-bold text-sm"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="mt-4 card px-5 py-3 flex items-center justify-between">
				<span class="flex items-center gap-2 text-sm"><span class="text-2xl font-display font-bold gradient-text">195+</span><span class="text-wgh-ink2 leading-tight text-xs">laptops in stock<br>ready to ship</span></span>
				<a href="<?php echo esc_url( $shop_url ); ?>" class="menu-link text-wgh-green">View all &rarr;</a>
			</div>
			<?php else : ?>
				<div class="card overflow-hidden aspect-[5/4] flex items-center justify-center text-wgh-ink2">Add products to see them here</div>
			<?php endif; ?>
		</div>
	</div>
</section>
