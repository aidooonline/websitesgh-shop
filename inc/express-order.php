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

	$items = WC()->cart->get_cart();

	/* WhatsApp previews the FIRST url in a message, so the first line is the
	 * first product's image. A direct image url renders as a picture in the
	 * chat without depending on og:image, which is the most reliable way to
	 * make the order look like a real product order. */
	$lead_image = '';
	foreach ( $items as $item ) {
		$product = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $product ) { continue; }
		$thumb_id = $product->get_image_id();
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'large' );
			if ( $src ) { $lead_image = $src[0]; break; }
		}
	}

	$lines = array();
	if ( $lead_image ) {
		$lines[] = $lead_image;
		$lines[] = '';
	}
	$lines[] = 'NEW ORDER';
	$lines[] = 'WebsitesGH Shop';
	$lines[] = '';

	$n = 0;
	foreach ( $items as $item ) {
		$product = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $product ) { continue; }
		$n++;
		$qty = (int) $item['quantity'];
		$lines[] = sprintf( '%d. %s', $n, $product->get_name() );
		$lines[] = sprintf( 'Quantity: %d', $qty );
		$lines[] = sprintf( 'Price: GHS %s', number_format( (float) $product->get_price() * $qty, 2 ) );
		$lines[] = get_permalink( $product->get_id() );
		$lines[] = '';
	}

	$lines[] = sprintf( 'TOTAL: GHS %s', number_format( (float) WC()->cart->get_total( 'edit' ), 2 ) );
	$lines[] = '';
	$lines[] = 'MY DETAILS';
	$lines[] = 'Name: ';
	$lines[] = 'Phone: ';
	$lines[] = 'Location: ';
	$lines[] = '';
	$lines[] = 'Pay on delivery';
	return implode( "\n", $lines );
}

/**
 * Cart: replace the proceed-to-checkout button with Send order on WhatsApp.
 * Hooked late on wp so it runs after WooCommerce has attached its own button,
 * then we strip all and add ours. Priority 99 on the render hook guarantees
 * ours is what shows.
 */
add_action( 'wp', function () {
	remove_all_actions( 'woocommerce_proceed_to_checkout' );
	add_action( 'woocommerce_proceed_to_checkout', 'wghs_cart_whatsapp_button', 99 );
}, 99 );

function wghs_cart_whatsapp_button() {
	$msg = wghs_wa_cart_message();
	if ( ! $msg || ! function_exists( 'wghs_wa_link' ) || ! function_exists( 'wghs_wa_number' ) || ! wghs_wa_number() ) {
		// No number configured: fall back to the standard button rather than a dead end.
		if ( function_exists( 'woocommerce_button_proceed_to_checkout' ) ) { woocommerce_button_proceed_to_checkout(); }
		return;
	}
	?>
	<a class="wghs-wa-order__btn wghs-cartwa" href="<?php echo wghs_wa_href( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by wghs_wa_href ?>"
		target="_blank" rel="noopener" data-wghs-event="cart_whatsapp">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Z"/></svg>
		<span><?php esc_html_e( 'Send order on WhatsApp', 'wghshop' ); ?></span>
	</a>
	<p class="wghs-cartwa__note">
		<?php esc_html_e( 'Your full order goes in one message. We confirm, deliver, and you pay the rider.', 'wghshop' ); ?>
	</p>
	<p class="wghs-cartwa__alt">
		<a href="<?php echo esc_url( add_query_arg( 'form', '1', wc_get_checkout_url() ) ); ?>">
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
// Single WhatsApp button re-hook removed; wghs_two_order_buttons renders the order buttons.

/**
 * Two buttons only, both on the product page, no cart in the flow.
 *
 *   Order now      -> WhatsApp, prefilled with this product (label is NOT
 *                     "WhatsApp", per the owner; it just says Order now)
 *   Contact to order -> the contact form, for buyers without WhatsApp
 *
 * The default WooCommerce add-to-cart form is removed completely so there is
 * no route through the cart from a product page.
 */
add_action( 'wp_loaded', function () {
	// Remove WooCommerce's default add-to-cart form UI; we render our own button.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
}, 20 );

/**
 * Product page order block, cart-first.
 *
 *   Get it now   -> adds THIS product to the cart and goes to the cart page,
 *                   where the whole basket is sent to WhatsApp as one message.
 *   Contact to order -> secondary, for buyers who would rather call or use the
 *                   form. Kept but visually quieter so the cart is the main path.
 *
 * Why cart-first: the cart is a measurable middle step. People who add to cart
 * but do not message become a retargeting audience (the add_to_cart pixel event
 * already fires for them). The cart also lets a buyer gather several items into
 * one clean WhatsApp message with the total. Fewer chats than one-tap, but
 * richer data and more serious buyers, which suits the paid-ads strategy.
 */
function wghs_two_order_buttons() {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	$in_stock = $product->is_in_stock();
	$contact  = home_url( '/contact/' );
	// Add-to-cart URL that redirects to the cart page after adding.
	$cart_url = $product->add_to_cart_url();
	?>
	<div class="wghs-order">
		<?php if ( $in_stock ) : ?>
			<a class="wghs-order__primary" href="<?php echo esc_url( $cart_url ); ?>"
				data-wghs-event="get_it_now" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				rel="nofollow">
				<?php esc_html_e( 'Get it now', 'wghshop' ); ?>
			</a>
		<?php else : ?>
			<span class="wghs-order__out"><?php esc_html_e( 'Out of stock, ask us when it returns', 'wghshop' ); ?></span>
		<?php endif; ?>
		<a class="wghs-order__secondary" href="<?php echo esc_url( $contact ); ?>" data-wghs-event="contact_order">
			<?php esc_html_e( 'Contact to order', 'wghshop' ); ?>
		</a>
	</div>
	<p class="wghs-order__note"><?php esc_html_e( 'Add what you want, then send the whole order on WhatsApp from your cart. Pay on delivery.', 'wghshop' ); ?></p>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'wghs_two_order_buttons', 30 );

// Old single WhatsApp button hooks removed at source in whatsapp-product.php.

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

/* --------------------------------------------------------------------------
 * Force the classic (shortcode) Cart and Checkout.
 *
 * WooCommerce's setup wizard creates block based Cart and Checkout pages.
 * Two things break on those:
 *   1. woocommerce_proceed_to_checkout is a shortcode-cart hook, so the
 *      WhatsApp button above never renders and the stock Proceed to Checkout
 *      button survives. That is the long journey the owner asked us to remove.
 *   2. The MoMo gateway is a classic WC_Payment_Gateway with no Blocks
 *      integration, so it does not appear on a block checkout at all.
 *
 * Converting both pages to the shortcodes fixes both, and the classic cart is
 * lighter, which matters on Ghanaian mobile connections. This runs once,
 * automatically, and records a flag so it never fights a deliberate change.
 * ------------------------------------------------------------------------ */

function wghs_force_classic_cart_checkout( $force = false ) {
	if ( ! function_exists( 'wc_get_page_id' ) ) { return array(); }
	$done = array();
	$map  = array(
		'cart'     => '[woocommerce_cart]',
		'checkout' => '[woocommerce_checkout]',
	);
	foreach ( $map as $key => $shortcode ) {
		$id = wc_get_page_id( $key );
		if ( $id <= 0 ) { continue; }
		$page = get_post( $id );
		if ( ! $page ) { continue; }
		// Already classic? Leave it alone.
		if ( false !== strpos( $page->post_content, $shortcode ) ) { continue; }
		// Only rewrite when it is the Woo block, so custom pages are safe.
		if ( ! $force && false === strpos( $page->post_content, 'wp:woocommerce/' . $key ) ) { continue; }
		wp_update_post( array( 'ID' => $id, 'post_content' => $shortcode ) );
		$done[] = $key;
	}
	return $done;
}

add_action( 'admin_init', function () {
	// Version-gated so a fix can force a re-run. Bump when the conversion logic
	// changes or when a live site is found still on block cart/checkout.
	if ( '2' === (string) get_option( 'wghs_classic_cart_done' ) ) { return; }
	if ( ! function_exists( 'wc_get_page_id' ) ) { return; }
	$done = wghs_force_classic_cart_checkout( true ); // force: convert block pages now
	update_option( 'wghs_classic_cart_done', '2' );
	if ( $done ) { set_transient( 'wghs_classic_cart_notice', $done, 60 ); }
} );

add_action( 'admin_notices', function () {
	$done = get_transient( 'wghs_classic_cart_notice' );
	if ( ! $done ) { return; }
	delete_transient( 'wghs_classic_cart_notice' );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf(
			/* translators: %s: comma separated page names. */
			__( 'WebsitesGH Shop switched these pages to the classic shortcode so WhatsApp ordering and the MoMo gateway work: %s.', 'wghshop' ),
			implode( ', ', $done )
		) )
	);
} );


/* Send buyers to the cart immediately after adding, so "Get it now" lands on
 * the cart page where the whole basket goes to WhatsApp. */
add_filter( 'woocommerce_add_to_cart_redirect', function () {
	return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
} );
add_filter( 'option_woocommerce_cart_redirect_after_add', function () { return 'yes'; } );

/* --------------------------------------------------------------------------
 * The flow is product -> cart -> WhatsApp. There is NO checkout page in this
 * journey. The billing form the owner saw is WooCommerce's checkout; we send
 * anyone who lands on it back to the cart, where the WhatsApp button lives.
 *
 * The only reason to allow checkout at all is the rare non-WhatsApp buyer who
 * uses the classic form on purpose. We allow that ONLY when they arrive with
 * ?form=1 (the quiet "No WhatsApp? order with the form" link on the cart).
 * Every other route to checkout bounces to the cart.
 * ------------------------------------------------------------------------ */
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
	// Never block the thank-you page, nor a pay-for-this-order link sent to a
	// customer, nor the order-pay endpoint WooCommerce uses for retries.
	if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) { return; }
	// Deliberate form users pass ?form=1; everyone else goes back to the cart.
	if ( isset( $_GET['form'] ) && '1' === $_GET['form'] ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
	wp_safe_redirect( wc_get_cart_url() );
	exit;
} );


/* Cleaner cart: drop the "added to cart / Continue shopping" notice, the cart
 * IS the next step now, so the notice is just clutter before the WhatsApp
 * button. */
add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
add_filter( 'woocommerce_cart_redirect_after_error', function ( $url ) { return $url; } );
