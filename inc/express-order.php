<?php
/**
 * Express ordering. Two steps, maximum.
 *
 * The primary journey everywhere is WhatsApp:
 *   Path A: product page > Order on WhatsApp. One tap, prefilled with the
 *           product, price, URL and the attribution ref.
 *   Path B: add to cart (for multi-item orders) > cart page > Send order on
 *           WhatsApp. The message carries every line item, quantities and the
 *           total. No checkout form in the way.
 *
 * WhatsApp is the primary path. A quiet text link under the cart button
 * keeps the checkout form available for buyers without WhatsApp or those
 * paying by MoMo ahead. The primary journey stays at two steps.
 *
 * The ref-stamping script in inc/attribution.php fires on ANY wa.me link, so
 * both paths are tracked with no extra wiring.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the WhatsApp message for the whole cart: every line, qty, total.
 *
 * @return string Empty when the cart is empty or Woo is absent.
 */
function wghs_wa_cart_message() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}
	$lines = array( __( 'Hello, I want to order:', 'wghshop' ), '' );
	foreach ( WC()->cart->get_cart() as $item ) {
		$product = $item['data'];
		if ( ! $product ) { continue; }
		$lines[] = sprintf(
			'%dx %s - GHS %s',
			(int) $item['quantity'],
			$product->get_name(),
			number_format( (float) $product->get_price() * (int) $item['quantity'], 2 )
		);
	}
	$lines[] = '';
	$lines[] = sprintf( __( 'Total: GHS %s', 'wghshop' ), number_format( (float) WC()->cart->get_total( 'edit' ), 2 ) );
	$lines[] = '';
	$lines[] = __( 'My name is:', 'wghshop' );
	$lines[] = __( 'My location is:', 'wghshop' );
	return implode( "\n", $lines );
}

/**
 * Cart: replace the proceed-to-checkout button with Send order on WhatsApp.
 */
add_action( 'init', function () {
	remove_all_actions( 'woocommerce_proceed_to_checkout' );
	add_action( 'woocommerce_proceed_to_checkout', 'wghs_cart_whatsapp_button', 20 );
}, 20 );

function wghs_cart_whatsapp_button() {
	$msg = wghs_wa_cart_message();
	if ( ! $msg || ! function_exists( 'wghs_wa_link' ) || ! function_exists( 'wghs_wa_number' ) || ! wghs_wa_number() ) {
		// No number configured: fall back to the standard button rather than a dead end.
		if ( function_exists( 'woocommerce_button_proceed_to_checkout' ) ) { woocommerce_button_proceed_to_checkout(); }
		return;
	}
	?>
	<a class="wghs-wa-order__btn wghs-cartwa" href="<?php echo esc_url( wghs_wa_link( $msg ) ); ?>"
		target="_blank" rel="noopener" data-wghs-event="cart_whatsapp">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Z"/></svg>
		<span><?php esc_html_e( 'Send order on WhatsApp', 'wghshop' ); ?></span>
	</a>
	<p class="wghs-cartwa__note">
		<?php esc_html_e( 'Your full order goes in one message. We confirm, deliver, and you pay the rider.', 'wghshop' ); ?>
	</p>
	<p class="wghs-cartwa__alt">
		<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
			<?php esc_html_e( 'No WhatsApp? Order with the form instead. Same pay on delivery.', 'wghshop' ); ?>
		</a>
	</p>
	<?php
}

/**
 * Product page: WhatsApp is the primary action, above the add to cart form.
 * inc/whatsapp-product.php registered it at priority 32 (below the form);
 * move it to 29 so it renders first, and soften the add to cart button into
 * the secondary action for multi-item buyers.
 */
add_action( 'init', function () {
	if ( function_exists( 'wghs_wa_product_button' ) ) {
		remove_action( 'woocommerce_single_product_summary', 'wghs_wa_product_button', 32 );
		add_action( 'woocommerce_single_product_summary', 'wghs_wa_product_button', 29 );
	}
}, 25 );

add_filter( 'woocommerce_product_single_add_to_cart_text', function () {
	return __( 'Add to cart for a bigger order', 'wghshop' );
} );

/**
 * The sticky mobile order bar should follow the same rule: straight to
 * WhatsApp, not to the cart. Replace its target.
 */
add_filter( 'wghs_orderbar_url', function ( $url, $product ) {
	if ( function_exists( 'wghs_wa_product_link' ) ) {
		$wa = wghs_wa_product_link( $product );
		if ( $wa ) { return $wa; }
	}
	return $url;
}, 10, 2 );
