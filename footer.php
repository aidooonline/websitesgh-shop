<?php
/**
 * Footer (v2) + on-page WhatsApp chat box.
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
?>
</main>

<footer class="border-t border-wgh-line bg-wgh-bg mt-20">
	<div class="wrap py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-4">
		<div>
			<?php wghs_logo(); ?>
			<p class="mt-4 text-sm text-wgh-ink2 max-w-xs leading-relaxed">
				<?php esc_html_e( 'Quality UK used products for students, professionals and businesses across Ghana. Tested, graded and warranty-backed.', 'wghshop' ); ?>
			</p>
			<div class="flex gap-3 mt-5">
				<?php
				$socials = array( 'wghs_fb' => 'Facebook', 'wghs_ig' => 'Instagram', 'wghs_tiktok' => 'TikTok', 'wghs_x' => 'X' );
				foreach ( $socials as $key => $name ) {
					$url = wghs_opt( $key );
					if ( $url ) {
						printf( '<a href="%s" target="_blank" rel="noopener" class="chip hover:border-wgh-green hover:text-wgh-green">%s</a>', esc_url( $url ), esc_html( $name ) );
					}
				}
				?>
			</div>
		</div>

		<div>
			<h4 class="text-sm font-display font-semibold text-wgh-ink mb-4"><?php esc_html_e( 'Shop', 'wghshop' ); ?></h4>
			<ul class="space-y-2.5 text-sm text-wgh-ink2">
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'All products', 'wghshop' ); ?></a></li>
				<?php
				if ( taxonomy_exists( 'product_cat' ) ) {
					$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 6 ) );
					if ( ! is_wp_error( $cats ) ) {
						foreach ( $cats as $cat ) {
							printf( '<li><a class="hover:text-wgh-green" href="%s">%s</a></li>', esc_url( get_term_link( $cat ) ), esc_html( $cat->name ) );
						}
					}
				}
				?>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/deals' ) ); ?>"><?php esc_html_e( 'Deals & Offers', 'wghshop' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h4 class="text-sm font-display font-semibold text-wgh-ink mb-4"><?php esc_html_e( 'Help', 'wghshop' ); ?></h4>
			<ul class="space-y-2.5 text-sm text-wgh-ink2">
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/how-to-order' ) ); ?>"><?php esc_html_e( 'How to Order', 'wghshop' ); ?></a></li>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/warranty-policy' ) ); ?>"><?php esc_html_e( 'Warranty Policy', 'wghshop' ); ?></a></li>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/return-policy' ) ); ?>"><?php esc_html_e( 'Return Policy', 'wghshop' ); ?></a></li>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/delivery-policy' ) ); ?>"><?php esc_html_e( 'Delivery Policy', 'wghshop' ); ?></a></li>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/privacy-policy' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'wghshop' ); ?></a></li>
				<li><a class="hover:text-wgh-green" href="<?php echo esc_url( home_url( '/terms' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'wghshop' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h4 class="text-sm font-display font-semibold text-wgh-ink mb-4"><?php esc_html_e( 'Contact', 'wghshop' ); ?></h4>
			<ul class="space-y-2.5 text-sm text-wgh-ink2">
				<?php if ( wghs_opt( 'wghs_phone' ) ) : ?><li><a class="hover:text-wgh-green" href="tel:<?php echo esc_attr( wghs_opt( 'wghs_phone' ) ); ?>"><?php echo esc_html( wghs_opt( 'wghs_phone' ) ); ?></a></li><?php endif; ?>
				<?php if ( wghs_wa_number() ) : ?><li><a class="hover:text-wgh-green" target="_blank" rel="noopener" href="<?php echo wghs_wa_link( __( 'Hi WebsitesGH Shop!', 'wghshop' ) ); ?>"><?php esc_html_e( 'WhatsApp us', 'wghshop' ); ?></a></li><?php endif; ?>
				<?php if ( wghs_opt( 'wghs_email' ) ) : ?><li><a class="hover:text-wgh-green" href="mailto:<?php echo esc_attr( wghs_opt( 'wghs_email' ) ); ?>"><?php echo esc_html( wghs_opt( 'wghs_email' ) ); ?></a></li><?php endif; ?>
				<?php if ( wghs_opt( 'wghs_address' ) ) : ?><li><?php echo esc_html( wghs_opt( 'wghs_address' ) ); ?></li><?php endif; ?>
			</ul>
			<div class="flex flex-wrap gap-2 mt-5">
				<span class="chip">MoMo</span>
				<span class="chip">Bank Transfer</span>
				<span class="chip">Pay on Delivery</span>
			</div>
		</div>
	</div>

	<div class="border-t border-wgh-line">
		<div class="wrap py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-wgh-ink2">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'We check the numbers.', 'wghshop' ); ?></p>
			<p class="font-mono uppercase tracking-widest"><?php esc_html_e( 'Accra · Tema · Nationwide courier', 'wghshop' ); ?></p>
		</div>
	</div>
</footer>

<?php if ( function_exists( 'WC' ) && function_exists( 'wghs_minicart_items_html' ) ) : ?>
<!-- Mini-cart drawer -->
<div id="wghs-cart-drawer" class="fixed inset-0 z-50 hidden">
	<div class="absolute inset-0 bg-black/70" data-close-cart></div>
	<aside class="absolute right-0 top-0 h-full w-[380px] max-w-[92vw] bg-wgh-line2 border-l border-wgh-line flex flex-col translate-x-full transition-transform duration-300" id="wghs-cart-panel">
		<div class="flex items-center justify-between px-5 py-4 border-b border-wgh-line">
			<h3 class="font-display font-bold text-lg"><?php esc_html_e( 'Your cart', 'wghshop' ); ?></h3>
			<button type="button" data-close-cart class="text-wgh-ink/80 hover:text-wgh-ink p-1" aria-label="<?php esc_attr_e( 'Close cart', 'wghshop' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
			</button>
		</div>
		<div class="flex-1 overflow-y-auto px-5">
			<?php echo wghs_minicart_items_html(); ?>
		</div>
		<div class="p-5 border-t border-wgh-line flex flex-col gap-2.5">
			<?php if ( function_exists( 'wghs_wa_cart_url' ) && wghs_wa_number() ) : ?>
				<a href="<?php echo esc_url( wghs_wa_cart_url() ); ?>" class="btn-wa w-full"><?php esc_html_e( 'Checkout', 'wghshop' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="btn-ghost w-full text-sm"><?php esc_html_e( 'View full cart', 'wghshop' ); ?></a>
		</div>
	</aside>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
