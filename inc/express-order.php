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

	/* WhatsApp previews the FIRST url in a message. We lead with the first
	 * product's PAGE url, not the raw image file.
	 *
	 * A direct .jpg link looks like the obvious choice, but LiteSpeed and
	 * similar layers often serve WebP to crawlers, and a crawler that asks for
	 * a jpg and receives something else renders no thumbnail, which is exactly
	 * the empty preview card we saw. A page url is the mechanism WhatsApp is
	 * designed around: it reads og:image, which the theme now guarantees.
	 *
	 * The first product's url is therefore omitted from its own list entry, so
	 * the same link never appears twice. */
	$lead_url = '';
	$lead_id  = 0;
	foreach ( $items as $item ) {
		$product = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $product ) { continue; }
		$lead_url = get_permalink( $product->get_id() );
		$lead_id  = $product->get_id();
		break;
	}

	$lines = array();
	if ( $lead_url ) {
		$lines[] = $lead_url;
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
		// The lead product's link is already at the top; do not repeat it.
		if ( $product->get_id() !== $lead_id ) {
			$lines[] = get_permalink( $product->get_id() );
		}
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


/* --------------------------------------------------------------------------
 * Empty the cart AFTER the order has gone to WhatsApp.
 *
 * Why it matters: without this the basket persists, so the buyer's next visit
 * starts with items they have already ordered, and their second WhatsApp
 * message repeats the first order. That produces duplicate orders and confused
 * customers.
 *
 * Why the ordering is delicate: WhatsApp must open FIRST. If the cart is
 * cleared before the link opens, the message can be rebuilt from an empty cart
 * and the customer sends a blank order. So every path opens WhatsApp, then
 * fires a beacon to clear the cart, then settles the page.
 *
 * sendBeacon is used deliberately: on a phone, tapping the link backgrounds the
 * browser to launch WhatsApp, and normal fetches get throttled or dropped when
 * a tab is backgrounded. sendBeacon is designed to survive exactly that.
 * ------------------------------------------------------------------------ */
add_action( 'wp_ajax_wghs_clear_cart', 'wghs_ajax_clear_cart' );
add_action( 'wp_ajax_nopriv_wghs_clear_cart', 'wghs_ajax_clear_cart' );
function wghs_ajax_clear_cart() {
	check_ajax_referer( 'wghs_clear_cart', 'nonce' );
	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->empty_cart();
	}
	wp_send_json_success( array( 'cleared' => true ) );
}

add_action( 'wp_footer', 'wghs_clear_cart_script', 60 );
function wghs_clear_cart_script() {
	if ( is_admin() || ! function_exists( 'WC' ) ) { return; }
	$ajax  = esc_url_raw( admin_url( 'admin-ajax.php' ) );
	$nonce = wp_create_nonce( 'wghs_clear_cart' );
	$done  = esc_url_raw( add_query_arg( 'order_sent', '1', wc_get_cart_url() ) );
	?>
	<script>
	(function () {
		'use strict';
		var AJAX  = '<?php echo $ajax; // phpcs:ignore ?>';
		var NONCE = '<?php echo esc_js( $nonce ); ?>';
		var DONE  = '<?php echo $done; // phpcs:ignore ?>';

		/* Called only AFTER WhatsApp has been opened. Never before. */
		window.wghsClearCart = function () {
			var body = new FormData();
			body.append('action', 'wghs_clear_cart');
			body.append('nonce', NONCE);
			var sent = false;
			try {
				sent = navigator.sendBeacon(AJAX, body);
			} catch (e) { sent = false; }
			if (!sent) {
				try {
					fetch(AJAX, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true });
				} catch (e2) { /* nothing more we can do */ }
			}
			/* Settle the page on the emptied cart with a confirmation. The delay
			   gives WhatsApp time to launch before we navigate away. */
			setTimeout(function () { window.location.href = DONE; }, 1500);
		};

		/* Path 1: a returning buyer taps the cart button directly. The lead
		   popup does not intercept because the cookie already exists, so the
		   native navigation opens WhatsApp and we clear straight after. */
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || typeof t.closest !== 'function') { return; }
			var a = t.closest('a[data-wghs-event="cart_whatsapp"]');
			if (!a) { return; }
			if (window.wghsLeadWillIntercept && window.wghsLeadWillIntercept(a)) { return; } // popup handles it
			setTimeout(window.wghsClearCart, 400);
		}, false);
	}());
	</script>
	<?php
}

/* An emptied cart with no explanation looks like a bug and loses trust right
 * after the most important action on the site. Confirm what happened instead. */
add_action( 'woocommerce_before_cart', 'wghs_order_sent_notice', 5 );
add_action( 'woocommerce_cart_is_empty', 'wghs_order_sent_notice', 5 );
function wghs_order_sent_notice() {
	if ( empty( $_GET['order_sent'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
	$wa = function_exists( 'wghs_wa_link' ) ? wghs_wa_link( '' ) : '';
	?>
	<div class="wghs-sent">
		<div class="wghs-sent__tick" aria-hidden="true">&#10003;</div>
		<div>
			<h3 class="wghs-sent__h"><?php esc_html_e( 'Your order is on WhatsApp', 'wghshop' ); ?></h3>
			<p class="wghs-sent__p"><?php esc_html_e( 'We have your basket. Send the message if it did not send by itself, and we will confirm the price and delivery. You pay the rider, not us.', 'wghshop' ); ?></p>
			<p class="wghs-sent__actions">
				<?php if ( $wa ) : ?>
					<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open WhatsApp', 'wghshop' ); ?></a>
				<?php endif; ?>
				<a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Keep shopping', 'wghshop' ); ?></a>
			</p>
		</div>
	</div>
	<?php
}


/* --------------------------------------------------------------------------
 * One-tap bundle: put the rail's products into the cart and show the cart.
 *
 * The sidebar already shows products relevant to the article being read. That
 * is a warm, contextual shortlist, and until now the only thing a reader could
 * do with it was click one product at a time. This turns the whole shortlist
 * into a basket in a single tap, then drops them on the cart where the WhatsApp
 * order button lives.
 *
 * Adds to the existing basket rather than replacing it, because a reader who
 * already chose something should not lose it.
 * ------------------------------------------------------------------------ */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['wghs_bundle'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }

	$raw = sanitize_text_field( wp_unslash( $_GET['wghs_bundle'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	if ( ! $ids ) { return; }

	$added = 0;
	foreach ( array_slice( $ids, 0, 10 ) as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) { continue; }
		// Skip anything already in the basket so a second tap does not double up.
		$dupe = false;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === (int) $id ) { $dupe = true; break; }
		}
		if ( $dupe ) { continue; }
		if ( WC()->cart->add_to_cart( $id ) ) { $added++; }
	}

	wp_safe_redirect( add_query_arg( 'bundled', (int) $added, wc_get_cart_url() ) );
	exit;
} );

/**
 * Products for the article rail, matched to what is being read.
 *
 * Was a random three, which is warm-ish but not relevant. Now it maps the
 * post's category to the matching product category so a reader of the fan
 * runtime guide sees fans, not a wireless mouse. Falls back to random only
 * when nothing matches, so the rail is never empty.
 *
 * @param int $limit How many products.
 * @return WC_Product[]
 */
function wghs_rail_products( $limit = 3 ) {
	if ( ! function_exists( 'wc_get_products' ) ) { return array(); }

	$map = array(
		'kitchen & home'         => 'kitchen-home',
		'kitchen and home'       => 'kitchen-home',
		'phones & audio'         => 'phones-audio',
		'phones and audio'       => 'phones-audio',
		'lighting & power'       => 'lighting-power',
		'lighting and power'     => 'lighting-power',
		'laundry & garment care' => 'laundry',
		'personal care'          => 'personal-care',
	);

	$slug = '';
	if ( is_singular( 'post' ) ) {
		foreach ( (array) get_the_category() as $cat ) {
			$key = strtolower( $cat->name );
			if ( isset( $map[ $key ] ) ) { $slug = $map[ $key ]; break; }
		}
	}

	$args = array(
		'status'       => 'publish',
		'limit'        => $limit,
		'orderby'      => 'rand',
		'stock_status' => 'instock',
	);
	if ( $slug ) {
		$scoped = wc_get_products( array_merge( $args, array( 'category' => array( $slug ) ) ) );
		if ( count( $scoped ) >= $limit ) { return $scoped; }
		// Top up from the general pool if the category is thin.
		$rest = wc_get_products( array_merge( $args, array( 'limit' => $limit ) ) );
		$seen = wp_list_pluck( $scoped, 'get_id' );
		foreach ( $rest as $p ) {
			if ( count( $scoped ) >= $limit ) { break; }
			if ( ! in_array( $p->get_id(), array_map( 'intval', $seen ), true ) ) { $scoped[] = $p; }
		}
		if ( $scoped ) { return $scoped; }
	}
	return wc_get_products( $args );
}

/* Confirmation when a bundle lands in the cart, so the jump is explained. */
add_action( 'woocommerce_before_cart', 'wghs_bundled_notice', 6 );
function wghs_bundled_notice() {
	if ( ! isset( $_GET['bundled'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
	$n = (int) $_GET['bundled']; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $n < 1 ) { return; }
	?>
	<div class="wghs-sent">
		<div class="wghs-sent__tick" aria-hidden="true">&#10003;</div>
		<div>
			<h3 class="wghs-sent__h"><?php
				/* translators: %d: number of products added. */
				printf( esc_html( _n( '%d item added from the guide', '%d items added from the guide', $n, 'wghshop' ) ), $n );
			?></h3>
			<p class="wghs-sent__p"><?php esc_html_e( 'Remove anything you do not want, then send the order on WhatsApp. You pay the rider when it reaches you, not before.', 'wghshop' ); ?></p>
		</div>
	</div>
	<?php
}
