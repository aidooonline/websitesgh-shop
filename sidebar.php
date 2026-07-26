<?php
/**
 * Blog sidebar. Desktop only, sticky, right hand rail.
 *
 * The ad slot is a plain container. The wgh-ad-slots plugin fills it at runtime
 * from GET /wp-json/wgh-ad-slots/v1/serve and reports clicks to
 * POST /wp-json/wgh-ad-slots/v1/click/{id}, matching the contract already running
 * on websitesgh.com. If the plugin is not active the container stays empty and
 * collapses, so nothing breaks.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<aside class="wghs-rail hidden lg:block" aria-label="<?php esc_attr_e( 'Sidebar', 'wghshop' ); ?>">
	<div class="wghs-rail__sticky">

		<?php // Ad slot 1. Filled by the wgh-ad-slots plugin. ?>
		<div class="wghs-railcard wghs-railcard--flush">
			<p class="wghs-railcard__label"><?php esc_html_e( 'Sponsored', 'wghshop' ); ?></p>
			<div class="wgh-adslot wgh-adslot--sidebar"
				data-slot="sidebar"
				data-rotate="<?php echo esc_attr( apply_filters( 'wghs_ad_rotate_ms', 8000 ) ); ?>"></div>
		</div>

		<?php // Shop the story. Products, not links. This is the money block. ?>
		<?php
		// Picked to match what is being read, not at random, and the ids are
		// reused below so the order button can basket the whole shortlist.
		$picks     = function_exists( 'wghs_rail_products' ) ? wghs_rail_products( 3 ) : array();
		$pick_ids  = array();
		foreach ( $picks as $rp ) { $pick_ids[] = (int) $rp->get_id(); }
		if ( function_exists( 'wc_get_products' ) ) {
			if ( $picks ) : ?>
			<div class="wghs-railcard wghs-railcard--shop">
				<p class="wghs-railcard__label"><?php esc_html_e( 'Shop this guide', 'wghshop' ); ?></p>
				<ul class="wghs-railshop">
					<?php foreach ( $picks as $p ) : ?>
						<li>
							<a href="<?php echo esc_url( $p->get_permalink() ); ?>">
								<span class="wghs-railshop__img">
									<?php
									$img = $p->get_image( 'thumbnail', array( 'loading' => 'lazy' ) );
									echo $img ? wp_kses_post( $img ) : wghs_placeholder_svg( 'w-full h-full' );
									?>
								</span>
								<span class="wghs-railshop__meta">
									<span class="wghs-railshop__name"><?php echo esc_html( wp_trim_words( $p->get_name(), 7 ) ); ?></span>
									<span class="wghs-railshop__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="wghs-railcta" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
					<?php esc_html_e( 'See all products', 'wghshop' ); ?>
				</a>
			</div>
			<?php endif;
		}
		?>

		<?php // Trust. Restates the one objection that stops people buying online here. ?>
		<div class="wghs-railcard wghs-railcard--trust">
			<p class="wghs-railcard__label"><?php esc_html_e( 'How ordering works', 'wghshop' ); ?></p>
			<ol class="wghs-railsteps">
				<li><?php esc_html_e( 'Tap the button, the items go to your cart', 'wghshop' ); ?></li>
				<li><?php esc_html_e( 'We call you to confirm', 'wghshop' ); ?></li>
				<li><?php esc_html_e( 'Check it, then pay the rider', 'wghshop' ); ?></li>
			</ol>
			<?php
			/* One tap puts the shortlist above into the cart and lands the reader
			 * on the cart, where the WhatsApp order button is. Far better than
			 * sending them to an empty chat and asking them to describe what they
			 * want. Falls back to a plain WhatsApp link if there are no picks. */
			if ( ! empty( $pick_ids ) ) :
				$bundle_url = add_query_arg( 'wghs_bundle', implode( ',', $pick_ids ), home_url( '/' ) );
				?>
				<a class="wghs-railcta wghs-railcta--wa" href="<?php echo esc_url( $bundle_url ); ?>"
					rel="nofollow" data-wghs-event="rail_bundle">
					<?php esc_html_e( 'Order, pay on delivery', 'wghshop' ); ?>
				</a>
			<?php else :
				$wa = function_exists( 'wghs_wa_link' ) ? wghs_wa_link( '' ) : '';
				if ( $wa ) : ?>
					<a class="wghs-railcta wghs-railcta--wa" href="<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Order, pay on delivery', 'wghshop' ); ?>
					</a>
				<?php endif;
			endif; ?>
		</div>

		<?php // Ad slot 2. Lower rail, catches the long scroll on detail pages. ?>
		<div class="wghs-railcard wghs-railcard--flush">
			<div class="wgh-adslot wgh-adslot--sidebar-b"
				data-slot="sidebar-b"
				data-rotate="<?php echo esc_attr( apply_filters( 'wghs_ad_rotate_ms', 8000 ) ); ?>"></div>
		</div>

	</div>
</aside>
