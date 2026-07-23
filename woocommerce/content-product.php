<?php
/**
 * Product card (loop) - v2.
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $product;
if ( empty( $product ) || ! $product->is_visible() ) { return; }
$terms = get_the_terms( $product->get_id(), 'product_cat' );
$brand = ( $terms && ! is_wp_error( $terms ) ) ? str_replace( ' Laptops', '', $terms[0]->name ) : '';
?>
<li <?php wc_product_class( '', $product ); ?>>
	<div class="pcard">
		<a href="<?php the_permalink(); ?>" class="pcard__media">
			<?php
			if ( $product->is_on_sale() ) { echo '<span class="absolute top-3 left-3 z-10 chip-grad !bg-wgh-gold !text-wgh-ink">SALE</span>'; }
			if ( has_post_thumbnail() ) { echo get_the_post_thumbnail( $product->get_id(), 'large' ); }
			else { echo wghs_placeholder_svg( 'w-full h-full' ); }
			?>
		</a>
		<div class="pcard__body">
			<?php if ( $brand ) : ?><span class="pcard__brand"><?php echo esc_html( $brand ); ?></span><?php endif; ?>
			<a href="<?php the_permalink(); ?>"><h3 class="pcard__title"><?php echo esc_html( $product->get_name() ); ?></h3></a>
			<div class="mt-auto pt-2 flex flex-col gap-2">
				<div class="flex items-center justify-between gap-2 flex-wrap">
					<?php echo wp_kses_post( $product->get_price_html() ); ?>
					<?php
					if ( $product->is_in_stock() ) {
						$qty = $product->get_stock_quantity();
						echo '<span class="pcard__stock">In stock' . ( $qty ? ' &middot; ' . (int) $qty : '' ) . '</span>';
					} else { echo '<span class="pcard__stock is-out">Out of stock</span>'; }
					?>
				</div>
				<div class="pcard__actions flex flex-col gap-2">
					<a href="<?php the_permalink(); ?>" class="btn-ghost text-xs !py-2.5"><?php esc_html_e( 'View laptop', 'wghshop' ); ?></a>
					<?php woocommerce_template_loop_add_to_cart(); ?>
				</div>
			</div>
		</div>
	</div>
</li>
