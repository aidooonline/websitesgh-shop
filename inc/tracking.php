<?php
/**
 * Tracking and conversions.
 *
 * GA4 with the full ecommerce event set, a Google Ads conversion on the order
 * received page carrying value and currency, and WhatsApp clicks as their own
 * event since WhatsApp is a real order path here, not a social link.
 *
 * All IDs are Customizer fields. Nothing fires until an ID is set, so the
 * theme ships silent and the owner switches tracking on from the admin.
 *
 * Customize > WebsitesGH Shop Settings > Tracking:
 *   wghs_ga4_id          e.g. G-XXXXXXXXXX
 *   wghs_gads_id         e.g. AW-XXXXXXXXX
 *   wghs_gads_label      the conversion label for purchases
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function wghs_ga4_id() { return trim( (string) get_theme_mod( 'wghs_ga4_id', '' ) ); }
function wghs_gads_id() { return trim( (string) get_theme_mod( 'wghs_gads_id', '' ) ); }
function wghs_meta_pixel() { return preg_replace( '/\D/', '', (string) get_theme_mod( 'wghs_meta_pixel', '' ) ); }

add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'wghs_tracking', array(
		'title'    => __( 'Tracking', 'wghshop' ),
		'panel'    => 'wghs_panel',
		'priority' => 60,
	) );
	$fields = array(
		'wghs_ga4_id'     => __( 'GA4 Measurement ID (G-...)', 'wghshop' ),
		'wghs_gads_id'    => __( 'Google Ads ID (AW-...)', 'wghshop' ),
		'wghs_gads_label' => __( 'Google Ads purchase conversion label', 'wghshop' ),
		'wghs_meta_pixel' => __( 'Meta Pixel ID (numbers only)', 'wghshop' ),
	);
	foreach ( $fields as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'wghs_tracking', 'type' => 'text' ) );
	}
}, 30 );

/**
 * gtag loader. One loader, both destinations.
 */
add_action( 'wp_head', function () {
	$ga4  = wghs_ga4_id();
	$gads = wghs_gads_id();
	if ( ! $ga4 && ! $gads ) { return; }
	$first = $ga4 ? $ga4 : $gads;
	?>
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $first ); ?>"></script>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	<?php if ( $ga4 ) : ?>gtag('config', '<?php echo esc_js( $ga4 ); ?>');<?php endif; ?>
	<?php if ( $gads ) : ?>gtag('config', '<?php echo esc_js( $gads ); ?>');<?php endif; ?>
	</script>
	<?php
}, 4 );

/**
 * Item payload for a product.
 *
 * @param WC_Product $product Product.
 * @param int        $qty     Quantity.
 * @return array
 */
function wghs_ga_item( $product, $qty = 1 ) {
	$cats = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	return array(
		'item_id'       => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
		'item_name'     => $product->get_name(),
		'item_category' => $cats ? $cats[0] : '',
		'price'         => (float) $product->get_price(),
		'quantity'      => (int) $qty,
	);
}

/** view_item on single product pages. */
add_action( 'wp_footer', function () {
	if ( ! wghs_ga4_id() || ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) { return; }
	printf(
		'<script>gtag("event","view_item",{currency:"%s",value:%s,items:[%s]});</script>',
		esc_js( get_woocommerce_currency() ),
		wp_json_encode( (float) $product->get_price() ),
		wp_json_encode( wghs_ga_item( $product ) )
	);
} );

/** add_to_cart, server side hook so it fires for both AJAX and page adds. */
add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty ) {
	if ( ! wghs_ga4_id() ) { return; }
	$product = wc_get_product( $product_id );
	if ( ! $product ) { return; }
	// Stash for output on next render; AJAX adds get it via the fragments filter.
	WC()->session->set( 'wghs_pending_atc', wghs_ga_item( $product, $qty ) );
}, 10, 3 );

add_action( 'wp_footer', function () {
	if ( ! wghs_ga4_id() || ! function_exists( 'WC' ) || ! WC()->session ) { return; }
	$item = WC()->session->get( 'wghs_pending_atc' );
	if ( ! $item ) { return; }
	WC()->session->set( 'wghs_pending_atc', null );
	printf(
		'<script>gtag("event","add_to_cart",{currency:"%s",value:%s,items:[%s]});</script>',
		esc_js( get_woocommerce_currency() ),
		wp_json_encode( (float) $item['price'] * (int) $item['quantity'] ),
		wp_json_encode( $item )
	);
}, 20 );

/** begin_checkout. */
add_action( 'woocommerce_before_checkout_form', function () {
	if ( ! wghs_ga4_id() || ! WC()->cart ) { return; }
	$items = array();
	foreach ( WC()->cart->get_cart() as $line ) {
		$items[] = wghs_ga_item( $line['data'], $line['quantity'] );
	}
	printf(
		'<script>gtag("event","begin_checkout",{currency:"%s",value:%s,items:%s});</script>',
		esc_js( get_woocommerce_currency() ),
		wp_json_encode( (float) WC()->cart->get_total( 'edit' ) ),
		wp_json_encode( $items )
	);
} );

/**
 * purchase + Google Ads conversion, on the order received page only, guarded
 * against double firing on refresh with order meta.
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }
	if ( 'yes' === $order->get_meta( '_wghs_tracked' ) ) { return; }
	$order->update_meta_data( '_wghs_tracked', 'yes' );
	$order->save();

	$items = array();
	foreach ( $order->get_items() as $line ) {
		$product = $line->get_product();
		if ( $product ) { $items[] = wghs_ga_item( $product, $line->get_quantity() ); }
	}
	$value    = (float) $order->get_total();
	$currency = $order->get_currency();

	if ( wghs_ga4_id() ) {
		printf(
			'<script>gtag("event","purchase",{transaction_id:"%s",currency:"%s",value:%s,items:%s});</script>',
			esc_js( $order->get_order_number() ),
			esc_js( $currency ),
			wp_json_encode( $value ),
			wp_json_encode( $items )
		);
	}

	$gads  = wghs_gads_id();
	$label = trim( (string) get_theme_mod( 'wghs_gads_label', '' ) );
	if ( $gads && $label ) {
		printf(
			'<script>gtag("event","conversion",{send_to:"%s/%s",value:%s,currency:"%s",transaction_id:"%s"});</script>',
			esc_js( $gads ),
			esc_js( $label ),
			wp_json_encode( $value ),
			esc_js( $currency ),
			esc_js( $order->get_order_number() )
		);
	}
}, 5 );

/**
 * WhatsApp clicks as their own GA4 event. Delegated listener, covers every
 * wa.me link on the page including ones rendered later.
 */
add_action( 'wp_footer', function () {
	if ( ! wghs_ga4_id() ) { return; }
	?>
	<script>
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href*="wa.me"]');
		if (!a) { return; }
		gtag('event', 'whatsapp_click', {
			link_url: a.href,
			page_location: location.href,
			placement: a.getAttribute('data-wghs-event') || 'generic'
		});
	}, true);
	</script>
	<?php
}, 30 );

/* --------------------------------------------------------------------------
 * Meta Pixel. Seeded before any Meta spend so the retargeting audiences
 * (product viewers, cart abandoners, WhatsApp tappers) are already months
 * deep the day the first ad runs. Silent until the ID is set.
 * ------------------------------------------------------------------------ */

add_action( 'wp_head', function () {
	$px = wghs_meta_pixel();
	if ( ! $px ) { return; }
	?>
	<script>
	!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
	n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
	document,'script','https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', '<?php echo esc_js( $px ); ?>');
	fbq('track', 'PageView');
	</script>
	<?php
}, 6 );

/** ViewContent on single products. */
add_action( 'wp_footer', function () {
	if ( ! wghs_meta_pixel() || ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) { return; }
	printf(
		'<script>fbq("track","ViewContent",{content_ids:[%s],content_type:"product",content_name:%s,value:%s,currency:"%s"});</script>',
		wp_json_encode( (string) $product->get_id() ),
		wp_json_encode( $product->get_name() ),
		wp_json_encode( (float) $product->get_price() ),
		esc_js( get_woocommerce_currency() )
	);
} );

/** AddToCart, reusing the session stash from the GA4 hook. */
add_action( 'wp_footer', function () {
	if ( ! wghs_meta_pixel() || ! function_exists( 'WC' ) || ! WC()->session ) { return; }
	$item = WC()->session->get( 'wghs_pending_atc_meta' );
	if ( ! $item ) { return; }
	WC()->session->set( 'wghs_pending_atc_meta', null );
	printf(
		'<script>fbq("track","AddToCart",{content_ids:[%s],content_type:"product",value:%s,currency:"%s"});</script>',
		wp_json_encode( (string) $item['item_id'] ),
		wp_json_encode( (float) $item['price'] * (int) $item['quantity'] ),
		esc_js( get_woocommerce_currency() )
	);
}, 21 );
add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty ) {
	if ( ! wghs_meta_pixel() ) { return; }
	$product = wc_get_product( $product_id );
	if ( $product ) { WC()->session->set( 'wghs_pending_atc_meta', wghs_ga_item( $product, $qty ) ); }
}, 10, 3 );

/** InitiateCheckout. */
add_action( 'woocommerce_before_checkout_form', function () {
	if ( ! wghs_meta_pixel() || ! WC()->cart ) { return; }
	printf(
		'<script>fbq("track","InitiateCheckout",{value:%s,currency:"%s",num_items:%d});</script>',
		wp_json_encode( (float) WC()->cart->get_total( 'edit' ) ),
		esc_js( get_woocommerce_currency() ),
		(int) WC()->cart->get_cart_contents_count()
	);
} );

/** Purchase, same refresh guard pattern via separate meta key. */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	if ( ! wghs_meta_pixel() ) { return; }
	$order = wc_get_order( $order_id );
	if ( ! $order || 'yes' === $order->get_meta( '_wghs_meta_tracked' ) ) { return; }
	$order->update_meta_data( '_wghs_meta_tracked', 'yes' );
	$order->save();
	$ids = array();
	foreach ( $order->get_items() as $line ) { $ids[] = (string) $line->get_product_id(); }
	printf(
		'<script>fbq("track","Purchase",{content_ids:%s,content_type:"product",value:%s,currency:"%s"});</script>',
		wp_json_encode( $ids ),
		wp_json_encode( (float) $order->get_total() ),
		esc_js( $order->get_currency() )
	);
}, 6 );

/** WhatsApp tap as Contact: the almost-ordered retargeting audience. */
add_action( 'wp_footer', function () {
	if ( ! wghs_meta_pixel() ) { return; }
	?>
	<script>
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href*="wa.me"]');
		if (!a || typeof fbq !== 'function') { return; }
		fbq('track', 'Contact', { content_name: a.getAttribute('data-wghs-event') || 'whatsapp' });
	}, true);
	</script>
	<?php
}, 31 );
