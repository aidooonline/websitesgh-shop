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
$cart  = $product->add_to_cart_url();
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
					<?php if ( $product->is_in_stock() ) : ?>
						<a class="pcard__wa" href="<?php echo esc_url( $cart ); ?>" rel="nofollow"
							data-wghs-event="card_get_it_now" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
							<span><?php esc_html_e( 'Get it now', 'wghshop' ); ?></span>
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
