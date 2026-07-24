<?php
/**
 * Product card (loop).
 *
 * WhatsApp is the primary action right here on the grid, so the journey from
 * the shop page is ONE tap: see the product, order it. Add to cart is the
 * small secondary action for people building a multi item order.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $product;
if ( empty( $product ) || ! $product->is_visible() ) { return; }

$terms = get_the_terms( $product->get_id(), 'product_cat' );
$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
$wa    = function_exists( 'wghs_wa_product_link' ) ? wghs_wa_product_link( $product ) : '';
?>
<li <?php wc_product_class( '', $product ); ?>>
	<div class="pcard">
		<a href="<?php the_permalink(); ?>" class="pcard__media">
			<?php
			if ( $product->is_on_sale() ) {
				echo '<span class="pcard__sale">' . esc_html__( 'Sale', 'wghshop' ) . '</span>';
			}
			if ( has_post_thumbnail() ) {
				echo get_the_post_thumbnail( $product->get_id(), 'large', array( 'loading' => 'lazy' ) );
			} else {
				echo wghs_placeholder_svg( 'w-full h-full', $product->get_name() );
			}
			?>
		</a>
		<div class="pcard__body">
			<?php if ( $cat ) : ?><span class="pcard__brand"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
			<a href="<?php the_permalink(); ?>"><h3 class="pcard__title"><?php echo esc_html( $product->get_name() ); ?></h3></a>

			<div class="mt-auto pt-3">
				<div class="pcard__pricerow">
					<?php echo wp_kses_post( $product->get_price_html() ); ?>
					<?php
					if ( $product->is_in_stock() ) {
						echo '<span class="pcard__stock">' . esc_html__( 'In stock', 'wghshop' ) . '</span>';
					} else {
						echo '<span class="pcard__stock is-out">' . esc_html__( 'Ask us', 'wghshop' ) . '</span>';
					}
					?>
				</div>

				<div class="pcard__actions">
					<?php if ( $wa && $product->is_in_stock() ) : ?>
						<a class="pcard__wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"
							data-wghs-event="card_whatsapp" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Z"/></svg>
							<span><?php esc_html_e( 'Order on WhatsApp', 'wghshop' ); ?></span>
						</a>
					<?php endif; ?>
					<div class="pcard__minor">
						<a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Details', 'wghshop' ); ?></a>
						<span aria-hidden="true">&middot;</span>
						<?php woocommerce_template_loop_add_to_cart(); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</li>
