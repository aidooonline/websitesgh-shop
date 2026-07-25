<?php
/**
 * Home hero. Works with zero product photos: the visual side is a clean
 * illustrated collage on the brand palette, and it upgrades to real product
 * images automatically once they exist. No undefined classes, no empty box.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

// Up to four in-stock products for the visual. Real photo if present, else illustration.
$grid = array();
if ( function_exists( 'wc_get_products' ) ) {
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 4, 'fields' => 'ids', 'orderby' => 'rand' ) );
	foreach ( $ids as $pid ) { $p = wc_get_product( $pid ); if ( $p ) { $grid[] = $p; } }
}
?>
<section class="relative overflow-hidden border-b border-wgh-line bg-green-fade">
	<div class="wrap relative grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-2 lg:gap-14 lg:py-20">

		<div>
			<span class="eyebrow"><?php echo esc_html( wghs_opt( 'wghs_hero_eyebrow', 'We check the numbers' ) ); ?></span>
			<h1 class="mt-3 text-4xl font-extrabold leading-[1.06] sm:text-5xl">
				<?php echo esc_html( wghs_opt( 'wghs_hero_title', 'Appliances and electronics' ) ); ?>
				<span class="text-wgh-green"><?php echo esc_html( wghs_opt( 'wghs_hero_title2', 'delivered across Ghana.' ) ); ?></span>
			</h1>
			<p class="mt-5 max-w-xl text-base leading-relaxed text-wgh-ink2 sm:text-lg">
				<?php echo esc_html( wghs_opt( 'wghs_hero_sub', 'Blenders, kettles, irons, power banks and more. Genuine, new, delivered fast. You inspect it before you pay the rider.' ) ); ?>
			</p>
			<div class="mt-8 flex flex-wrap gap-3">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="btn-primary">
					<?php esc_html_e( 'Browse products', 'wghshop' ); ?>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</a>
				<a href="<?php echo esc_url( home_url( '/how-to-order/' ) ); ?>" class="btn-ghost"><?php esc_html_e( 'How to order', 'wghshop' ); ?></a>
			</div>
			<ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-wgh-ink2">
				<li class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> <?php esc_html_e( 'Pay on delivery', 'wghshop' ); ?></li>
				<li class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> <?php esc_html_e( 'Same day in Accra', 'wghshop' ); ?></li>
				<li class="flex items-center gap-2"><span class="text-wgh-green">&#10003;</span> <?php esc_html_e( 'Genuine and new', 'wghshop' ); ?></li>
			</ul>
		</div>

		<div class="relative">
			<?php if ( $grid ) : ?>
			<div class="grid grid-cols-2 gap-3 sm:gap-4">
				<?php foreach ( $grid as $i => $p ) :
					$img = get_the_post_thumbnail_url( $p->get_id(), 'large' ); ?>
					<a href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>"
						class="group flex flex-col rounded-xl border border-wgh-line bg-white p-3 shadow-soft transition hover:-translate-y-0.5 hover:border-wgh-green hover:shadow-card">
						<span class="relative block aspect-[4/3] overflow-hidden rounded-lg bg-wgh-line2">
							<?php if ( $img ) : ?>
								<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p->get_name() ); ?>" class="h-full w-full object-contain p-2 transition duration-500 group-hover:scale-105" loading="lazy">
							<?php else : ?>
								<?php echo wghs_placeholder_svg( 'h-full w-full', $p->get_name() ); ?>
							<?php endif; ?>
						</span>
						<span class="mt-2.5 line-clamp-2 text-xs font-semibold leading-snug text-wgh-ink"><?php echo esc_html( $p->get_name() ); ?></span>
						<span class="mt-1 text-sm font-bold text-wgh-goldInk"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="mt-4 flex items-center justify-between rounded-xl border border-wgh-line bg-white px-5 py-3 shadow-soft">
				<span class="flex items-center gap-2 text-sm">
					<span class="text-2xl font-extrabold text-wgh-green">50+</span>
					<span class="text-xs leading-tight text-wgh-ink2"><?php esc_html_e( 'products in stock', 'wghshop' ); ?><br><?php esc_html_e( 'ready to ship', 'wghshop' ); ?></span>
				</span>
				<a href="<?php echo esc_url( $shop_url ); ?>" class="text-sm font-semibold text-wgh-green hover:text-wgh-greenV"><?php esc_html_e( 'View all', 'wghshop' ); ?> &rarr;</a>
			</div>
			<?php else : ?>
			<div class="rounded-2xl border border-wgh-line bg-white p-8 shadow-soft">
				<div class="grid grid-cols-2 gap-4">
					<?php foreach ( array( 'blender', 'kettle', 'power', 'iron' ) as $k ) : ?>
						<span class="block aspect-square rounded-xl bg-wgh-greenPale/60"><?php echo wghs_art( $k, 'h-full w-full' ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
