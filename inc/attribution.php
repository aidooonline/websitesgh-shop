<?php
/**
 * Attribution engine.
 *
 * Closes the WhatsApp gap. The flow:
 *
 *   1. A visitor lands from an ad. Their gclid (or gbraid/wbraid on iOS) and
 *      UTMs are stored in a first-party cookie for 90 days.
 *   2. They tap any WhatsApp link. A beacon logs the click to a custom table:
 *      time, click ID, product, price, placement, UTMs. This is the funnel
 *      record the owner reviews.
 *   3. They order on-site instead? The gclid rides the checkout: it is saved
 *      to the order AND auto-logged as a CONVERTED attribution row with the
 *      order value. Zero clicks of work for on-site sales.
 *   4. WhatsApp sales are confirmed by the owner in WooCommerce > Attribution
 *      with one click per sale (value editable inline).
 *   5. Export produces the exact CSV Google Ads ingests for offline click
 *      conversions, GMT timezone (Ghana is GMT year round), and marks rows
 *      exported so the same conversion is never uploaded twice.
 *
 * Intelligence: if a WooCommerce order arrives carrying a gclid that matches
 * a pending WhatsApp click, the click row is auto-converted with the order
 * value and linked to the order. The owner only ever confirms sales that
 * happened entirely inside WhatsApp.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --------------------------------------------------------------------------
 * Table
 * ------------------------------------------------------------------------ */

function wghs_attr_table() {
	global $wpdb;
	return $wpdb->prefix . 'wghs_attribution';
}

function wghs_attr_install() {
	global $wpdb;
	$table   = wghs_attr_table();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NULL DEFAULT NULL,
		click_id VARCHAR(191) NOT NULL DEFAULT '',
		click_type VARCHAR(10) NOT NULL DEFAULT '',
		product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		product_name VARCHAR(191) NOT NULL DEFAULT '',
		price DECIMAL(10,2) NOT NULL DEFAULT 0,
		placement VARCHAR(60) NOT NULL DEFAULT '',
		utm_source VARCHAR(60) NOT NULL DEFAULT '',
		utm_medium VARCHAR(60) NOT NULL DEFAULT '',
		utm_campaign VARCHAR(120) NOT NULL DEFAULT '',
		utm_term VARCHAR(191) NOT NULL DEFAULT '',
		utm_content VARCHAR(191) NOT NULL DEFAULT '',
		utm_id VARCHAR(64) NOT NULL DEFAULT '',
		match_type VARCHAR(4) NOT NULL DEFAULT '',
		campaign_id VARCHAR(32) NOT NULL DEFAULT '',
		adgroup_id VARCHAR(32) NOT NULL DEFAULT '',
		creative_id VARCHAR(32) NOT NULL DEFAULT '',
		target_id VARCHAR(64) NOT NULL DEFAULT '',
		network VARCHAR(8) NOT NULL DEFAULT '',
		device VARCHAR(4) NOT NULL DEFAULT '',
		ad_placement VARCHAR(191) NOT NULL DEFAULT '',
		cart_items TEXT NULL,
		status VARCHAR(12) NOT NULL DEFAULT 'pending',
		converted_at DATETIME NULL DEFAULT NULL,
		conv_value DECIMAL(10,2) NOT NULL DEFAULT 0,
		order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		exported TINYINT(1) NOT NULL DEFAULT 0,
		visitor VARCHAR(8) NOT NULL DEFAULT 'human',
		ref VARCHAR(12) NOT NULL DEFAULT '',
		cust_name VARCHAR(120) NOT NULL DEFAULT '',
		cust_phone VARCHAR(24) NOT NULL DEFAULT '',
		cust_area VARCHAR(120) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY status (status),
		KEY click_id (click_id(32)),
		KEY created_at (created_at),
		KEY updated_at (updated_at),
		KEY utm_term (utm_term(64)),
		KEY campaign_id (campaign_id),
		KEY ref (ref),
		KEY visitor (visitor),
		KEY cust_phone (cust_phone)
	) {$charset};" );

	// Backfill, so the dashboard's delta cursor has a value on every historic
	// row from the first sync. Without this, every pre-existing row would look
	// like it had never been touched and COALESCE would carry the work forever.
	$wpdb->query( "UPDATE {$table} SET updated_at = created_at WHERE updated_at IS NULL" );

	update_option( 'wghs_attr_db_version', '1.5' );
}
add_action( 'after_switch_theme', 'wghs_attr_install' );
add_action( 'admin_init', function () {
	if ( '1.5' !== get_option( 'wghs_attr_db_version' ) ) { wghs_attr_install(); }
} );

/* --------------------------------------------------------------------------
 * Write helpers.
 *
 * Every write goes through these two so updated_at can never be forgotten at
 * one call site. It is forgotten exactly once and then the dashboard holds a
 * stale copy of a converted row and reports a sale that happened as no sale.
 * The stamp is explicit UTC, matching created_at, never MySQL CURRENT_TIMESTAMP,
 * which would follow the server session timezone and mix local time into a
 * UTC table.
 * ------------------------------------------------------------------------ */

/**
 * Insert an attribution row, stamping updated_at.
 *
 * @param array $data Column data.
 * @return int|false Insert result from wpdb.
 */
/**
 * Who is this, really: a customer, a crawler, or the shop owner testing?
 *
 * WHY THIS COLUMN HAD TO EXIST
 * The funnel read 87 add-to-cart against 8 WhatsApp messages and nobody could
 * say whether that was a real closing problem or a bot problem, because the
 * table recorded no user agent, no session and no IP. That is not a number you
 * can act on: one reading says fix the cart page, the other says ignore it, and
 * they lead to opposite work. Anything that cannot tell those apart is not
 * measurement.
 *
 * WooCommerce accepts add-to-cart as a plain GET (`?add-to-cart=123`), and those
 * links sit on every shop and category page, so a crawler walking the site adds
 * to the basket dozens of times without any human intent whatsoever.
 *
 * NOTHING IS DROPPED, ONLY LABELLED.
 * Same rule as cancelled orders, which are kept rather than hidden. A row
 * deleted at the shop can never be re-examined, and a classifier that turns out
 * to be wrong would have silently destroyed the evidence of its own mistake.
 * The dashboard decides what to count; the shop only records what it saw.
 *
 * @return string 'human', 'bot' or 'staff'.
 */
function wghs_attr_visitor_kind() {
	// The owner testing his own shop is not a customer, and he is on it daily.
	if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
		return 'staff';
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) $_SERVER['HTTP_USER_AGENT'] ) : '';

	// No user agent at all is never a browser.
	if ( '' === $ua ) {
		return 'bot';
	}

	$signatures = array(
		'bot', 'crawl', 'spider', 'slurp', 'archiver', 'scraper', 'monitor',
		'headless', 'phantom', 'selenium', 'puppeteer', 'playwright',
		'python-requests', 'python-urllib', 'curl/', 'wget', 'go-http-client',
		'java/', 'okhttp', 'axios', 'node-fetch', 'httpclient', 'libwww',
		'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot', 'bytespider',
		'facebookexternalhit', 'preview', 'lighthouse', 'pagespeed', 'gtmetrix',
		'uptime', 'pingdom', 'statuscake', 'newrelic',
	);

	foreach ( $signatures as $needle ) {
		if ( false !== strpos( $ua, $needle ) ) {
			return 'bot';
		}
	}

	/*
	 * A real browser sends an Accept header asking for HTML. Scripted clients
	 * that bothered to fake a browser user agent usually do not bother with
	 * this one, and a genuine browser always sends it.
	 */
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';

	if ( '' === $accept ) {
		return 'bot';
	}

	return 'human';
}

function wghs_attr_insert( array $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql', true );

	if ( ! isset( $data['visitor'] ) ) {
		$data['visitor'] = wghs_attr_visitor_kind();
	}

	return $wpdb->insert( wghs_attr_table(), $data );
}

/**
 * Update attribution rows, stamping updated_at.
 *
 * @param array $data  Column data.
 * @param array $where Where clause.
 * @return int|false Update result from wpdb.
 */
function wghs_attr_update( array $data, array $where ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql', true );
	return $wpdb->update( wghs_attr_table(), $data, $where );
}

/* --------------------------------------------------------------------------
 * Campaign parameters.
 *
 * A click id alone says "a Google click happened". It never says which keyword
 * or ad group produced it, and there is no way to ask Google later: the click
 * id only resolves inside Google's own reports. Whatever ValueTrack puts on
 * the landing URL at click time is the only chance to capture it. These two
 * helpers make sure the beacon, the add-to-cart hook and the checkout hook all
 * record exactly the same set, because a field captured on one path and missed
 * on another is worse than not capturing it: it looks like real data.
 * ------------------------------------------------------------------------ */

/**
 * The campaign columns, mapped from a request payload.
 *
 * @param array $p Decoded JSON payload from the beacon.
 * @return array
 */
function wghs_attr_campaign_from_payload( array $p ) {
	$take = function ( $key, $len ) use ( $p ) {
		return substr( sanitize_text_field( (string) ( $p[ $key ] ?? '' ) ), 0, $len );
	};
	return array(
		'utm_source'   => $take( 'utm_source', 60 ),
		'utm_medium'   => $take( 'utm_medium', 60 ),
		'utm_campaign' => $take( 'utm_campaign', 120 ),
		'utm_term'     => $take( 'utm_term', 191 ),
		'utm_content'  => $take( 'utm_content', 191 ),
		'utm_id'       => $take( 'utm_id', 64 ),
		'match_type'   => $take( 'wg_mt', 4 ),
		'campaign_id'  => $take( 'wg_cid', 32 ),
		'adgroup_id'   => $take( 'wg_ag', 32 ),
		'creative_id'  => $take( 'wg_cr', 32 ),
		'network'      => $take( 'wg_net', 8 ),
		'device'       => $take( 'wg_dev', 4 ),
		'target_id'    => $take( 'wg_tgt', 64 ),
		'ad_placement' => $take( 'wg_pl', 191 ),
	);
}

/**
 * The campaign columns, mapped from the first-party cookies.
 *
 * Used by the server-side paths (add to cart, checkout) where there is no
 * beacon payload to read.
 *
 * @return array
 */
function wghs_attr_campaign_from_cookies() {
	$map = array(
		'utm_source' => array( 'utm_source', 60 ),
		'utm_medium' => array( 'utm_medium', 60 ),
		'utm_campaign' => array( 'utm_campaign', 120 ),
		'utm_term' => array( 'utm_term', 191 ),
		'utm_content' => array( 'utm_content', 191 ),
		'utm_id' => array( 'utm_id', 64 ),
		'match_type' => array( 'wg_mt', 4 ),
		'campaign_id' => array( 'wg_cid', 32 ),
		'adgroup_id' => array( 'wg_ag', 32 ),
		'creative_id' => array( 'wg_cr', 32 ),
		'network' => array( 'wg_net', 8 ),
		'device' => array( 'wg_dev', 4 ),
		'target_id' => array( 'wg_tgt', 64 ),
		'ad_placement' => array( 'wg_pl', 191 ),
	);
	$out = array();
	foreach ( $map as $column => $spec ) {
		list( $cookie, $len ) = $spec;
		$out[ $column ] = ! empty( $_COOKIE[ 'wghs_' . $cookie ] )
			? substr( sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $cookie ] ) ), 0, $len )
			: '';
	}
	return $out;
}

/**
 * The click id and its type, from the first-party cookies.
 *
 * Google first: it is the only click id that feeds offline conversions back
 * into Smart Bidding, which is the growth loop the whole system protects.
 *
 * @return array{0:string,1:string}
 */
function wghs_attr_click_from_cookies() {
	foreach ( array( 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'msclkid' ) as $k ) {
		if ( ! empty( $_COOKIE[ 'wghs_' . $k ] ) ) {
			return array( substr( sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $k ] ) ), 0, 191 ), $k );
		}
	}
	return array( '', '' );
}

/**
 * Resolve the current cart into a product id, a label and a value.
 *
 * WHY THIS EXISTS
 * The shop is cart first, by design: "Get it now" adds to the cart and the
 * order is sent from the cart in one message. That makes cart_whatsapp the
 * MAIN order path, and it was logging product 0 at value 0, because the cart
 * button carries no single product. Every real order therefore arrived in the
 * dashboard with no product and no money attached, and no amount of ad data
 * downstream could have said which product earned what. Read from WooCommerce
 * on the server, so it cannot be faked and does not depend on the markup being
 * fresh.
 *
 * @return array{product_id:int,product_name:string,price:float,cart_items:string}|null
 */
function wghs_attr_cart_snapshot() {
	if ( ! function_exists( 'WC' ) ) { return null; }
	if ( ( ! WC()->cart || WC()->cart->is_empty() ) && function_exists( 'wc_load_cart' ) ) {
		// REST runs without a cart loaded. The session cookie is on the request,
		// so this rehydrates the real basket rather than guessing from markup.
		wc_load_cart();
	}
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return null; }

	$items = array();
	$lead  = 0;
	$best  = -1;
	$count = 0;
	foreach ( WC()->cart->get_cart() as $line ) {
		$pid = (int) ( $line['product_id'] ?? 0 );
		$qty = max( 1, (int) ( $line['qty'] ?? 1 ) );
		$sub = (float) ( $line['line_subtotal'] ?? 0 );
		if ( ! $pid ) { continue; }
		$items[] = $pid . ':' . $qty . ':' . number_format( $sub, 2, '.', '' );
		$count  += $qty;
		// The dearest line represents the basket in single-product views, so a
		// basket is never filed under a GHS 20 add-on.
		if ( $sub > $best ) { $best = $sub; $lead = $pid; }
	}
	if ( ! $items ) { return null; }

	$name = $lead && function_exists( 'wc_get_product' ) && wc_get_product( $lead )
		? wc_get_product( $lead )->get_name()
		: __( 'Cart', 'wghshop' );
	if ( count( $items ) > 1 ) {
		/* translators: 1: lead product name, 2: number of other items */
		$name = sprintf( __( '%1$s + %2$d more', 'wghshop' ), $name, count( $items ) - 1 );
	}

	return array(
		'product_id'   => $lead,
		'product_name' => substr( $name, 0, 191 ),
		'price'        => (float) WC()->cart->get_cart_contents_total(),
		'cart_items'   => substr( implode( ',', $items ), 0, 65000 ),
	);
}

/* ------------------------------------------------------------------------ */

/* --------------------------------------------------------------------------
 * Front end: capture the click IDs and UTMs, log WhatsApp taps.
 * ------------------------------------------------------------------------ */

add_action( 'wp_footer', function () {
	$rest = esc_url_raw( rest_url( 'wghs/v1/wa-click' ) );
	?>
	<script>
	(function () {
		'use strict';
		/* 1. Persist ad click IDs and campaign parameters for 90 days, first
		   party.

		   A gclid alone tells you a Google click happened. It does not tell you
		   WHICH keyword, ad group or ad produced it, and Google will not tell
		   you later either: the click id is only resolvable inside Google's own
		   reports. If the keyword is not on the landing URL at click time, it is
		   gone. So everything Google offers through ValueTrack is captured here,
		   and the campaign CSV import in sprint 2 resolves the numeric ids to
		   names.

		   wg_* are our own short names, filled from ValueTrack on Google, from
		   the {{...}} macros on Meta, and from __MACROS__ on TikTok. See
		   dashboard/docs/TRACKING-TEMPLATES.md for the exact strings to paste
		   into each platform. */
		var qs = new URLSearchParams(location.search);
		var keep = [
			'gclid','gbraid','wbraid','fbclid','ttclid','msclkid',
			'utm_source','utm_medium','utm_campaign','utm_term','utm_content','utm_id',
			'wg_mt','wg_cid','wg_ag','wg_cr','wg_net','wg_dev','wg_tgt','wg_pl'
		];
		keep.forEach(function (k) {
			var v = qs.get(k);
			if (v) { document.cookie = 'wghs_' + k + '=' + encodeURIComponent(v) + ';path=/;max-age=7776000;SameSite=Lax'; }
		});
		function ck(k) {
			var m = document.cookie.match(new RegExp('(?:^|; )wghs_' + k + '=([^;]*)'));
			return m ? decodeURIComponent(m[1]) : '';
		}
		/* 2. Log every WhatsApp tap. sendBeacon so the navigation is never blocked. */
		function mkref() {
			var abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789', out = '';
			for (var i = 0; i < 4; i++) { out += abc[Math.floor(Math.random() * abc.length)]; }
			return 'WG-' + out;
		}
		/* Log one WhatsApp tap. Exposed globally so the lead-capture popup can
		   call it AFTER it has captured the customer's name and phone, which is
		   the only way those details reach the attribution row on a first
		   order. Returns the generated ref. */
		window.wghsLogTap = function (a) {
			if (!a) { return ''; }
			/* Stamp a human order reference into the prefilled text. Reads as
			   customer service, works as attribution: the code the customer
			   sends is the code on the row in WooCommerce > Attribution. */
			var ref = mkref();
			try {
				var u = new URL(a.href);
				var t = u.searchParams.get('text') || '';
				if (t.indexOf('Order ref:') === -1) {
					u.searchParams.set('text', t + (t ? '\n' : '') + 'Order ref: ' + ref);
					a.href = u.toString();
				}
			} catch (err) { /* leave the link untouched */ }
			var lead = {};
			try { var lm = document.cookie.match(/(?:^|; )wghs_lead=([^;]*)/); if (lm) { lead = JSON.parse(decodeURIComponent(lm[1])); } } catch (e2) { lead = {}; }
			/* Click id, in priority order. Google first because it is the only
			   one that feeds offline conversions back for Smart Bidding. */
			var cid = '', ctype = '';
			['gclid','gbraid','wbraid','fbclid','ttclid','msclkid'].forEach(function (k) {
				if (!cid && ck(k)) { cid = ck(k); ctype = k; }
			});

			var payload = {
				ref: ref,
				click_id: cid,
				click_type: ctype,
				product_id: parseInt(a.getAttribute('data-product-id') || (document.body.className.match(/postid-(\d+)/) || [0,0])[1], 10) || 0,
				placement: a.getAttribute('data-wghs-event') || 'generic',
				/* The cart button carries the whole basket. Without these the
				   main order path logs product 0 at value 0, and no amount of
				   ad data downstream can say which product earned the money. */
				cart_value: a.getAttribute('data-cart-value') || '',
				cart_items: a.getAttribute('data-cart-items') || '',
				utm_source: ck('utm_source'), utm_medium: ck('utm_medium'), utm_campaign: ck('utm_campaign'),
				utm_term: ck('utm_term'), utm_content: ck('utm_content'), utm_id: ck('utm_id'),
				wg_mt: ck('wg_mt'), wg_cid: ck('wg_cid'), wg_ag: ck('wg_ag'), wg_cr: ck('wg_cr'),
				wg_net: ck('wg_net'), wg_dev: ck('wg_dev'), wg_tgt: ck('wg_tgt'), wg_pl: ck('wg_pl'),
				cust_name: lead.name || '', cust_phone: lead.phone || '', cust_area: lead.area || ''
			};
			try {
				navigator.sendBeacon('<?php echo $rest; // phpcs:ignore ?>', new Blob([JSON.stringify(payload)], { type: 'application/json' }));
			} catch (err) { /* never block the tap */ }
			return ref;
		};

		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || typeof t.closest !== 'function') { return; } // text nodes, SVG in old browsers
			var a = t.closest('a[href*="wa.me"]');
			if (!a) { return; }
			/* Only real order buttons are taps. Blog share buttons also build
			   wa.me links; logging those would pollute the funnel. */
			if (!a.hasAttribute('data-wghs-event')) { return; }
			/* If the lead popup is going to intercept this tap to collect the
			   name and phone, let IT log the tap afterwards. Otherwise we would
			   log twice, and the first row would have no customer details. */
			if (window.wghsLeadWillIntercept && window.wghsLeadWillIntercept(a)) { return; }
			window.wghsLogTap(a);
		}, true);
	}());
	</script>
	<?php
}, 40 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'wghs/v1', '/wa-click', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			global $wpdb;
			$p  = $req->get_json_params();
			if ( ! is_array( $p ) ) { return new WP_REST_Response( array( 'ok' => false ), 400 ); }

			$placement    = substr( sanitize_text_field( $p['placement'] ?? '' ), 0, 60 );
			$product_id   = absint( $p['product_id'] ?? 0 );
			$product_name = '';
			$price        = 0;
			$cart_items   = null;

			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_name = $product->get_name();
					$price        = (float) $product->get_price();
				}
			}

			// The cart button is the main order path and carries no single
			// product. Read the real basket from WooCommerce rather than
			// filing the order under product 0 at value 0.
			if ( 'cart_whatsapp' === $placement || ! $product_id ) {
				$cart = wghs_attr_cart_snapshot();
				if ( $cart ) {
					$product_id   = $cart['product_id'];
					$product_name = $cart['product_name'];
					$price        = $cart['price'];
					$cart_items   = $cart['cart_items'];
				} elseif ( '' !== (string) ( $p['cart_value'] ?? '' ) ) {
					// Fallback: the button carries a server-rendered snapshot,
					// used when the REST request arrives without a cart session.
					$price      = (float) $p['cart_value'];
					$cart_items = substr( preg_replace( '/[^0-9:.,]/', '', (string) ( $p['cart_items'] ?? '' ) ), 0, 65000 );
				}
			}

			// Basic flood guard: same click id + product within 60s is a double tap.
			$click_id = substr( sanitize_text_field( $p['click_id'] ?? '' ), 0, 191 );
			$dupe = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . wghs_attr_table() . " WHERE click_id = %s AND product_id = %d AND placement = %s AND created_at > %s LIMIT 1",
				$click_id, $product_id, $placement, gmdate( 'Y-m-d H:i:s', time() - 60 )
			) );
			if ( $dupe ) { return new WP_REST_Response( array( 'ok' => true, 'dupe' => true ), 200 ); }

			$types = array( 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'msclkid' );

			wghs_attr_insert( array_merge( wghs_attr_campaign_from_payload( $p ), array(
				'created_at'   => current_time( 'mysql', true ),
				'click_id'     => $click_id,
				'click_type'   => in_array( $p['click_type'] ?? '', $types, true ) ? $p['click_type'] : '',
				'product_id'   => $product_id,
				'product_name' => $product_name,
				'price'        => $price,
				'cart_items'   => $cart_items,
				'placement'    => $placement,
				'ref'          => substr( preg_replace( '/[^A-Z0-9-]/', '', strtoupper( (string) ( $p['ref'] ?? '' ) ) ), 0, 12 ),
				'cust_name'    => substr( sanitize_text_field( $p['cust_name'] ?? '' ), 0, 120 ),
				'cust_phone'   => substr( preg_replace( '/[^0-9+]/', '', (string) ( $p['cust_phone'] ?? '' ) ), 0, 24 ),
				'cust_area'    => substr( sanitize_text_field( $p['cust_area'] ?? '' ), 0, 120 ),
			) ) );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );
} );

/* --------------------------------------------------------------------------
 * On-site orders: ride the gclid through checkout, auto-convert.
 * ------------------------------------------------------------------------ */

/**
 * Log every add-to-cart as a funnel stage, so the dashboard can measure
 * view -> cart -> WhatsApp -> sale. Reuses the same click-id/UTM cookie
 * context as the WhatsApp tap. Placement 'add_to_cart' distinguishes it.
 */
add_action( 'woocommerce_add_to_cart', function ( $cart_item_key, $product_id, $qty ) {
	global $wpdb;
	if ( ! function_exists( 'wc_get_product' ) ) { return; }
	$product = wc_get_product( $product_id );
	if ( ! $product ) { return; }

	list( $click_id, $type ) = wghs_attr_click_from_cookies();

	wghs_attr_insert( array_merge( wghs_attr_campaign_from_cookies(), array(
		'created_at'   => current_time( 'mysql', true ),
		'click_id'     => $click_id,
		'click_type'   => $type,
		'product_id'   => (int) $product_id,
		'product_name' => $product->get_name(),
		'price'        => (float) $product->get_price() * max( 1, (int) $qty ),
		'placement'    => 'add_to_cart',
		'status'       => 'cart',
	) ) );
}, 20, 3 );

add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	global $wpdb;
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	list( $click_id, $type ) = wghs_attr_click_from_cookies();
	$campaign = wghs_attr_campaign_from_cookies();

	// Stamp the whole campaign context onto the order, not just the three
	// original UTMs. The dashboard reads these back through the export, and a
	// field that was captured on the tap but dropped on the order would make
	// on-site sales look like they came from nowhere.
	foreach ( $campaign as $column => $value ) {
		if ( '' !== $value ) { $order->update_meta_data( '_wghs_' . $column, $value ); }
	}
	if ( $click_id ) {
		$order->update_meta_data( '_wghs_click_id', $click_id );
		$order->update_meta_data( '_wghs_click_type', $type );
	}
	$order->save();

	// Intelligence 1: a pending WhatsApp click with this click id becomes this
	// order's conversion automatically. Otherwise log a fresh converted row so
	// on-site ad-driven sales are export ready with zero owner effort.
	if ( $click_id ) {
		$pending = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . wghs_attr_table() . " WHERE click_id = %s AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
			$click_id
		) );
		$data = array(
			'status'       => 'converted',
			'converted_at' => current_time( 'mysql', true ),
			'conv_value'   => (float) $order->get_total(),
			'order_id'     => $order_id,
		);
		if ( $pending ) {
			wghs_attr_update( $data, array( 'id' => $pending ) );
		} else {
			$items = $order->get_items();
			$first = $items ? reset( $items ) : null;
			$lines = array();
			foreach ( $items as $line ) {
				$lines[] = (int) $line->get_product_id() . ':' . max( 1, (int) $line->get_quantity() )
					. ':' . number_format( (float) $line->get_total(), 2, '.', '' );
			}
			wghs_attr_insert( array_merge( $campaign, $data, array(
				'created_at'   => current_time( 'mysql', true ),
				'click_id'     => $click_id,
				'click_type'   => $type,
				'product_id'   => $first ? (int) $first->get_product_id() : 0,
				'product_name' => $first ? $first->get_name() : __( 'On-site order', 'wghshop' ),
				'price'        => (float) $order->get_total(),
				'cart_items'   => $lines ? substr( implode( ',', $lines ), 0, 65000 ) : null,
				'placement'    => 'checkout',
			) ) );
		}
	}
} );

/* --------------------------------------------------------------------------
 * Admin backend: WooCommerce > Attribution.
 * ------------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Attribution', 'wghshop' ),
		__( 'Attribution', 'wghshop' ),
		'manage_woocommerce',
		'wghs-attribution',
		'wghs_attr_admin_page'
	);
}, 60 );

function wghs_attr_admin_page() {
	global $wpdb;
	$table  = wghs_attr_table();
	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'pending';
	if ( ! in_array( $status, array( 'pending', 'converted', 'dismissed', 'all' ), true ) ) { $status = 'pending'; }

	$ref_q = isset( $_GET['ref'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', wp_unslash( $_GET['ref'] ) ) ) : '';
	if ( $ref_q ) {
		$where = $wpdb->prepare( 'ref = %s', $ref_q );
	} else {
		$where = 'all' === $status ? '1=1' : $wpdb->prepare( 'status = %s', $status );
	}
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 300" );

	// Product intelligence: which products convert from chat, which only chat.
	$intel = $wpdb->get_results(
		"SELECT product_name,
			COUNT(*) taps,
			SUM(status='converted') sold,
			ROUND(100 * SUM(status='converted') / COUNT(*)) rate
		FROM {$table}
		WHERE product_name <> '' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
		GROUP BY product_name HAVING taps >= 2
		ORDER BY sold DESC, taps DESC LIMIT 8"
	);
	// Follow up list: warm chats going cold. Older than a day, younger than a week.
	$followups = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table} WHERE status='pending'
		AND created_at BETWEEN DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) AND DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
	);

	$counts = $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$table} GROUP BY status", OBJECT_K );
	$n      = function ( $k ) use ( $counts ) { return isset( $counts[ $k ] ) ? (int) $counts[ $k ]->n : 0; };
	// Google click ids ONLY. Since the tap now also captures fbclid, ttclid and
	// msclkid, a bare "click_id is not empty" test would count a Meta click as
	// exportable and then upload it to Google Ads as a Google Click ID, where
	// it matches nothing and quietly poisons the conversion feed that Smart
	// Bidding learns from.
	$unexported = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table}
		WHERE status='converted' AND exported=0 AND click_id <> ''
		AND click_type IN ('gclid','gbraid','wbraid')"
	);

	$nonce = wp_create_nonce( 'wghs_attr' );
	$ajax  = admin_url( 'admin-ajax.php' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Attribution', 'wghshop' ); ?></h1>
		<p><?php esc_html_e( 'Every ad-tracked WhatsApp tap and on-site order. On-site orders auto-convert. For WhatsApp sales: set the value if it differs, then click Sold. Export sends only new converted rows with a Google click ID.', 'wghshop' ); ?></p>
		<p class="description" style="max-width:760px">
			<?php esc_html_e( 'Paste the customer number from the chat before clicking Sold. It costs the buyer nothing, it is the only way repeat customers can be counted, and it links this sale back to their earlier taps. It also works for Meta Click-to-WhatsApp sales, which never touch the website at all.', 'wghshop' ); ?>
		</p>

		<?php if ( $intel ) : ?>
		<table class="widefat" style="max-width:720px;margin:10px 0">
			<thead><tr>
				<th><?php esc_html_e( 'Product (last 30 days)', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'WhatsApp taps', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Sold', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Close rate', 'wghshop' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $intel as $i ) : ?>
				<tr>
					<td><?php echo esc_html( $i->product_name ); ?></td>
					<td><?php echo (int) $i->taps; ?></td>
					<td><strong><?php echo (int) $i->sold; ?></strong></td>
					<td><?php echo (int) $i->rate; ?>%</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'High taps with a low close rate means the page or the price is losing the chat. High close rate means put ad budget and stock there.', 'wghshop' ); ?></p>
		<?php endif; ?>
		<?php if ( $followups > 0 && 'pending' === $status && ! $ref_q ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php printf( esc_html__( '%d warm chats are going cold (tapped WhatsApp 1 to 7 days ago, no sale marked). Open WhatsApp, search the ref code from the row, and follow up in that thread.', 'wghshop' ), (int) $followups ); ?>
			</p></div>
		<?php endif; ?>

		<ul class="subsubsub">
			<?php foreach ( array( 'pending' => __( 'Pending', 'wghshop' ), 'converted' => __( 'Converted', 'wghshop' ), 'dismissed' => __( 'Dismissed', 'wghshop' ), 'all' => __( 'All', 'wghshop' ) ) as $k => $label ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wghs-attribution', 'status' => $k ), admin_url( 'admin.php' ) ) ); ?>" <?php echo $k === $status ? 'class="current"' : ''; ?>>
					<?php echo esc_html( $label ); ?> <span class="count">(<?php echo 'all' === $k ? esc_html( $n( 'pending' ) + $n( 'converted' ) + $n( 'dismissed' ) ) : esc_html( $n( $k ) ); ?>)</span>
				</a></li>
			<?php endforeach; ?>
		</ul>

		<form method="get" style="float:left;margin:6px 12px 6px 0">
			<input type="hidden" name="page" value="wghs-attribution">
			<input type="search" name="ref" value="<?php echo esc_attr( $ref_q ); ?>" placeholder="<?php esc_attr_e( 'Find ref e.g. WG-4F7K', 'wghshop' ); ?>">
			<button class="button"><?php esc_html_e( 'Find', 'wghshop' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="float:right;margin:6px 0">
			<input type="hidden" name="action" value="wghs_attr_export">
			<?php wp_nonce_field( 'wghs_attr_export' ); ?>
			<button class="button button-primary" <?php disabled( 0 === $unexported ); ?>>
				<?php printf( esc_html__( 'Export %d new conversions (Google Ads CSV)', 'wghshop' ), (int) $unexported ); ?>
			</button>
		</form>

		<div class="wghs-bulkbar" style="clear:both;margin:10px 0;padding:8px 10px;background:#fff;border:1px solid #c3c4c7;display:none">
			<strong><span class="wghs-bulkn">0</span> <?php esc_html_e( 'selected', 'wghshop' ); ?></strong>
			<label style="margin-left:12px">
				<?php esc_html_e( 'Value each (GHS)', 'wghshop' ); ?>
				<input type="number" step="0.01" min="0" class="wghs-bulkval small-text" placeholder="<?php esc_attr_e( 'keep', 'wghshop' ); ?>">
			</label>
			<button class="button button-primary wghs-bulk" data-act="convert" style="margin-left:8px"><?php esc_html_e( 'Mark Sold', 'wghshop' ); ?></button>
			<button class="button wghs-bulk" data-act="dismiss"><?php esc_html_e( 'Dismiss', 'wghshop' ); ?></button>
			<button class="button wghs-bulk" data-act="pend"><?php esc_html_e( 'Reopen', 'wghshop' ); ?></button>
			<span class="wghs-bulkmsg" style="margin-left:10px;color:#646970"></span>
			<p class="description" style="margin:6px 0 0">
				<?php esc_html_e( 'Leave the value blank to keep each row\'s own amount. Sold rows that have already been exported are skipped.', 'wghshop' ); ?>
			</p>
		</div>

		<table class="widefat striped" style="clear:both;margin-top:8px">
			<thead><tr>
				<td class="check-column" style="width:2.2em;padding:8px 0 8px 3px">
					<input type="checkbox" class="wghs-checkall" title="<?php esc_attr_e( 'Select all', 'wghshop' ); ?>">
				</td>
				<th><?php esc_html_e( 'When (GMT)', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Product', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Placement', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Source', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Keyword', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Ref', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Click ID', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Value (GHS)', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wghshop' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="12"><?php esc_html_e( 'Nothing here yet. Rows appear when visitors tap WhatsApp or order with an ad click ID present.', 'wghshop' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr data-id="<?php echo (int) $r->id; ?>" data-exported="<?php echo (int) $r->exported; ?>" data-status="<?php echo esc_attr( $r->status ); ?>">
					<th scope="row" class="check-column" style="padding:8px 0 8px 3px">
						<input type="checkbox" class="wghs-cb">
					</th>
					<td><?php echo esc_html( $r->created_at ); ?></td>
					<td><?php
						if ( ! empty( $r->cust_name ) || ! empty( $r->cust_phone ) ) {
							echo '<strong>' . esc_html( $r->cust_name ?: '-' ) . '</strong>';
							if ( ! empty( $r->cust_phone ) ) { echo '<br><a href="tel:' . esc_attr( $r->cust_phone ) . '">' . esc_html( $r->cust_phone ) . '</a>'; }
							if ( ! empty( $r->cust_area ) ) { echo '<br><small>' . esc_html( $r->cust_area ) . '</small>'; }
						} else {
							echo '<small style="color:#a00">' . esc_html__( 'skipped', 'wghshop' ) . '</small>';
						}
					?></td>
					<td>
						<?php echo esc_html( $r->product_name ?: ( $r->product_id ? '#' . $r->product_id : '-' ) ); ?>
						<?php if ( ! empty( $r->cart_items ) ) : ?>
							<br><small style="color:#646970" title="<?php echo esc_attr( $r->cart_items ); ?>">
								<?php printf( esc_html( _n( '%d line in basket', '%d lines in basket', substr_count( $r->cart_items, ',' ) + 1, 'wghshop' ) ), (int) substr_count( $r->cart_items, ',' ) + 1 ); ?>
							</small>
						<?php endif; ?>
						<?php echo $r->order_id ? ' <a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ) . '">#' . (int) $r->order_id . '</a>' : ''; ?>
					</td>
					<td><?php echo esc_html( $r->placement ); ?></td>
					<td><?php echo esc_html( trim( $r->utm_source . ' / ' . $r->utm_campaign, ' /' ) ?: ( $r->click_id ? 'google' : 'direct' ) ); ?></td>
					<td>
						<?php if ( ! empty( $r->utm_term ) ) : ?>
							<code><?php echo esc_html( $r->utm_term ); ?></code>
							<?php if ( ! empty( $r->match_type ) ) : ?>
								<br><small style="color:#646970"><?php echo esc_html( wghs_attr_match_label( $r->match_type ) ); ?></small>
							<?php endif; ?>
						<?php else : ?>
							<small style="color:#a7aaad"><?php esc_html_e( 'none', 'wghshop' ); ?></small>
						<?php endif; ?>
					</td>
					<td><strong><?php echo esc_html( $r->ref ); ?></strong></td>
					<td><?php echo $r->click_id ? '<code title="' . esc_attr( $r->click_id ) . '">' . esc_html( substr( $r->click_id, 0, 10 ) ) . '&hellip;</code> <small>' . esc_html( $r->click_type ) . '</small>' : '<small>' . esc_html__( 'none', 'wghshop' ) . '</small>'; ?></td>
					<td>
						<input type="number" step="0.01" class="wghs-attr-value small-text" value="<?php echo esc_attr( $r->conv_value > 0 ? $r->conv_value : $r->price ); ?>" <?php disabled( 'converted' === $r->status && $r->exported ); ?>>
						<?php if ( 'pending' === $r->status && empty( $r->cust_phone ) ) : ?>
							<br><input type="tel" class="wghs-attr-phone small-text" style="margin-top:4px"
								placeholder="<?php esc_attr_e( 'phone from the chat', 'wghshop' ); ?>"
								title="<?php esc_attr_e( 'Copy the number from the WhatsApp chat. It links this sale to the customer, so repeat buyers can be counted. Nothing is asked of the buyer.', 'wghshop' ); ?>">
						<?php endif; ?>
					</td>
					<td><strong><?php echo esc_html( $r->status ); ?></strong><?php echo $r->exported ? ' &middot; ' . esc_html__( 'exported', 'wghshop' ) : ''; ?></td>
					<td style="white-space:nowrap">
						<button type="button" class="button button-small wghs-tags-btn" aria-expanded="false">
							<?php esc_html_e( 'Tags', 'wghshop' ); ?>
						</button>
						<?php if ( 'pending' === $r->status ) : ?>
							<button class="button button-primary wghs-attr-act" data-act="convert"><?php esc_html_e( 'Sold', 'wghshop' ); ?></button>
							<button class="button wghs-attr-act" data-act="dismiss"><?php esc_html_e( 'Dismiss', 'wghshop' ); ?></button>
						<?php elseif ( 'converted' === $r->status && ! $r->exported ) : ?>
							<button class="button wghs-attr-act" data-act="pend"><?php esc_html_e( 'Undo', 'wghshop' ); ?></button>
						<?php elseif ( 'dismissed' === $r->status ) : ?>
							<button class="button wghs-attr-act" data-act="pend"><?php esc_html_e( 'Restore', 'wghshop' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
				<tr class="wghs-tags-row" style="display:none;background:#fbfbfc">
					<td></td>
					<td colspan="11" style="padding:12px 14px">
						<?php $tags = wghs_attr_tag_list( $r ); ?>
						<?php $present = array_filter( $tags, function ( $t ) { return '' !== $t['raw']; } ); ?>

						<?php if ( ! $present ) : ?>
							<p style="margin:0;color:#a00">
								<strong><?php esc_html_e( 'No campaign tags on this visit.', 'wghshop' ); ?></strong><br>
								<span class="description">
									<?php esc_html_e( 'The visitor arrived without ad parameters, so this is organic, direct or a link with no tracking on it. Nothing is broken.', 'wghshop' ); ?>
								</span>
							</p>
						<?php else : ?>
							<table style="border-collapse:collapse">
								<?php foreach ( $tags as $t ) : ?>
									<tr>
										<td style="padding:2px 18px 2px 0;color:#646970;white-space:nowrap"><?php echo esc_html( $t['label'] ); ?></td>
										<td style="padding:2px 12px 2px 0">
											<?php if ( '' === $t['raw'] ) : ?>
												<span style="color:#c3c4c7">&mdash;</span>
											<?php else : ?>
												<code style="user-select:all"><?php echo esc_html( $t['raw'] ); ?></code>
												<?php if ( '' !== $t['note'] ) : ?>
													<small style="color:#646970"> <?php echo esc_html( $t['note'] ); ?></small>
												<?php endif; ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						<?php endif; ?>

						<?php if ( ! empty( $r->cart_items ) ) : ?>
							<p style="margin:10px 0 0"><strong><?php esc_html_e( 'Basket at the moment of the tap', 'wghshop' ); ?></strong></p>
							<table class="widefat striped" style="max-width:520px;margin-top:4px">
								<thead><tr>
									<th><?php esc_html_e( 'Product', 'wghshop' ); ?></th>
									<th style="width:60px"><?php esc_html_e( 'Qty', 'wghshop' ); ?></th>
									<th style="width:110px"><?php esc_html_e( 'Line (GHS)', 'wghshop' ); ?></th>
								</tr></thead>
								<tbody>
								<?php foreach ( explode( ',', (string) $r->cart_items ) as $line ) : ?>
									<?php
									$parts = explode( ':', $line );
									if ( count( $parts ) < 3 ) { continue; }
									$pid  = (int) $parts[0];
									$pobj = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
									?>
									<tr>
										<td><?php echo $pobj ? esc_html( $pobj->get_name() ) : '#' . (int) $pid; ?></td>
										<td><?php echo (int) $parts[1]; ?></td>
										<td><?php echo esc_html( number_format( (float) $parts[2], 2 ) ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>

						<p class="description" style="margin:10px 0 0">
							<?php esc_html_e( 'None of this goes into the Google Ads export. That file carries only the click ID, conversion name, time, value and currency, which is all Google accepts. These tags are for the dashboard.', 'wghshop' ); ?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<script>
	(function () {
		var AJAX  = '<?php echo esc_js( $ajax ); ?>';
		var NONCE = '<?php echo esc_js( $nonce ); ?>';
		var bar   = document.querySelector('.wghs-bulkbar');

		/* Only real data rows carry a .wghs-cb, so the expanded tag rows can
		   never be swept into a bulk selection. */
		function boxes() { return Array.prototype.slice.call(document.querySelectorAll('tbody .wghs-cb')); }
		function picked() { return boxes().filter(function (c) { return c.checked; }); }

		function refresh() {
			var n = picked().length;
			if (bar) {
				bar.style.display = n ? 'block' : 'none';
				bar.querySelector('.wghs-bulkn').textContent = n;
				bar.querySelector('.wghs-bulkmsg').textContent = '';
			}
			var all = document.querySelector('.wghs-checkall');
			if (all) { all.checked = n > 0 && n === boxes().length; }
		}

		/* Shift-click selects a range, the way every list table on the web
		   behaves. Marking thirty sales one checkbox at a time is the reason
		   bulk actions were asked for in the first place. */
		var last = null;
		document.addEventListener('click', function (e) {
			var cb = e.target.closest('.wghs-cb');
			if (cb) {
				var list = boxes();
				if (e.shiftKey && last !== null) {
					var a = list.indexOf(cb), b = last;
					var lo = Math.min(a, b), hi = Math.max(a, b);
					for (var i = lo; i <= hi; i++) { list[i].checked = cb.checked; }
				}
				last = boxes().indexOf(cb);
				refresh();
				return;
			}

			var all = e.target.closest('.wghs-checkall');
			if (all) {
				boxes().forEach(function (c) { c.checked = all.checked; });
				refresh();
				return;
			}

			/* Single row buttons, unchanged. */
			var one = e.target.closest('.wghs-attr-act');
			if (one) {
				var tr = one.closest('tr');
				one.disabled = true;
				post({ id: tr.dataset.id, act: one.dataset.act, value: rowValue(tr), phone: rowPhone(tr) })
					.then(function () { location.reload(); });
				return;
			}

			var tags = e.target.closest('.wghs-tags-btn');
			if (tags) {
				var row = tags.closest('tr').nextElementSibling;
				if (row && row.classList.contains('wghs-tags-row')) {
					var open = row.style.display !== 'none';
					row.style.display = open ? 'none' : 'table-row';
					tags.setAttribute('aria-expanded', open ? 'false' : 'true');
				}
				return;
			}

			var bulk = e.target.closest('.wghs-bulk');
			if (bulk) { runBulk(bulk); }
		});

		function rowValue(tr) {
			var f = tr.querySelector('.wghs-attr-value');
			return f ? f.value : 0;
		}

		function rowPhone(tr) {
			var f = tr.querySelector('.wghs-attr-phone');
			return f ? f.value.trim() : '';
		}

		function post(extra) {
			var body = new URLSearchParams({ action: 'wghs_attr_update', _wpnonce: NONCE });
			Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
			return fetch(AJAX, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); });
		}

		function runBulk(btn) {
			var rows = picked().map(function (c) { return c.closest('tr'); });
			if (!rows.length) { return; }

			var act = btn.dataset.act;
			var override = bar.querySelector('.wghs-bulkval').value;

			/* An exported conversion has already been uploaded to Google.
			   Re-marking it would either double count or desync the export
			   flag, so those rows are skipped rather than silently rewritten. */
			var usable = rows.filter(function (tr) {
				return !(tr.dataset.exported === '1' && act !== 'convert');
			});

			var label = act === 'convert' ? 'Marking sold' : (act === 'dismiss' ? 'Dismissing' : 'Reopening');
			if (!confirm(label + ' ' + usable.length + ' row(s). Continue?')) { return; }

			var ids = usable.map(function (tr) { return tr.dataset.id; });
			var values = usable.map(function (tr) { return override !== '' ? override : rowValue(tr); });

			bar.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
			bar.querySelector('.wghs-bulkmsg').textContent = 'Working...';

			/* One request for the whole selection. Thirty separate calls would
			   be thirty chances for a half-applied batch. */
			post({ act: act, ids: ids.join(','), values: values.join(','), bulk: 1 })
				.then(function (r) {
					var n = (r && r.data && r.data.updated) || 0;
					bar.querySelector('.wghs-bulkmsg').textContent = n + ' updated';
					location.reload();
				})
				.catch(function () {
					bar.querySelector('.wghs-bulkmsg').textContent = 'Failed. Nothing was changed.';
					bar.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
				});
		}

		refresh();
	}());
	</script>
	<?php
}

/**
 * Apply one status change to one row.
 *
 * Shared by the single-row buttons and the bulk bar so the two can never drift
 * apart. A bulk path that reimplements the single path is how "Sold" starts
 * meaning two different things.
 *
 * @param int    $id  Row id.
 * @param string $act convert|dismiss|pend.
 * @param float  $val Conversion value, used by convert only.
 * @return bool Whether a row was changed.
 */
function wghs_attr_apply( $id, $act, $val = 0, $phone = '' ) {
	global $wpdb;
	$id = absint( $id );
	if ( ! $id ) { return false; }

	if ( 'convert' === $act ) {
		$data = array(
			'status'       => 'converted',
			'converted_at' => current_time( 'mysql', true ),
			'conv_value'   => (float) $val,
		);

		/*
		 * The phone number, captured HERE rather than at the tap.
		 *
		 * The obvious place to demand a phone number is before the WhatsApp
		 * button, and it is the wrong place. The tap is the single worst
		 * moment in the funnel to add friction, WhatsApp hands over the number
		 * thirty seconds later anyway, and Meta Click-to-WhatsApp ads open
		 * WhatsApp directly without ever touching this site, so a site-side
		 * gate cannot cover the primary paid channel at all.
		 *
		 * Marking a sale is the opposite: the owner is already looking at the
		 * chat, the number is on screen, the customer is committed, and it
		 * works for CTWA sales too. Identity is only needed on BUYERS, and
		 * this catches every one of them at zero cost to the buyer.
		 */
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );

		if ( $phone ) {
			$data['cust_phone'] = substr( $phone, 0, 24 );
			$ok = false !== wghs_attr_update( $data, array( 'id' => $id ) );

			if ( $ok ) { wghs_attr_backlink_phone( $id, $data['cust_phone'] ); }

			return $ok;
		}

		return false !== wghs_attr_update( $data, array( 'id' => $id ) );
	}
	if ( 'dismiss' === $act ) {
		return false !== wghs_attr_update( array( 'status' => 'dismissed' ), array( 'id' => $id ) );
	}
	if ( 'pend' === $act ) {
		return false !== wghs_attr_update(
			array( 'status' => 'pending', 'converted_at' => null, 'exported' => 0 ),
			array( 'id' => $id )
		);
	}
	return false;
}

/**
 * Attach a known phone to this customer's earlier anonymous taps.
 *
 * Somebody who skipped the popup three times and then bought is one customer,
 * not four anonymous events. Once the number is known from the chat, the
 * earlier taps in the same 90 day window can be claimed, which is what turns a
 * pile of events into a journey and makes repeat-customer tracking possible at
 * all.
 *
 * Only rows with NO phone are touched, and only ones that share this row's
 * click id or ref, so it can never merge two different people.
 *
 * @param int    $id    The row just marked sold.
 * @param string $phone E.164-ish phone.
 * @return int Rows linked.
 */
function wghs_attr_backlink_phone( $id, $phone ) {
	global $wpdb;
	$table = wghs_attr_table();

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT click_id, ref, created_at FROM {$table} WHERE id = %d", $id ) );
	if ( ! $row ) { return 0; }

	$since = gmdate( 'Y-m-d H:i:s', strtotime( $row->created_at ) - ( 90 * DAY_IN_SECONDS ) );

	// A shared click id means the same browser and the same ad click. A shared
	// ref means the same order conversation. Either is strong enough; anything
	// looser would start merging strangers.
	if ( ! $row->click_id && ! $row->ref ) { return 0; }

	$where  = array();
	$params = array( current_time( 'mysql', true ), substr( $phone, 0, 24 ) );

	if ( $row->click_id ) { $where[] = 'click_id = %s'; $params[] = $row->click_id; }
	if ( $row->ref )      { $where[] = 'ref = %s';      $params[] = $row->ref; }

	$params[] = $since;
	$params[] = (int) $id;

	$sql = "UPDATE {$table} SET updated_at = %s, cust_phone = %s
		WHERE ( cust_phone = '' OR cust_phone IS NULL )
		AND ( " . implode( ' OR ', $where ) . " )
		AND created_at >= %s AND id <> %d";

	return (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
}

/**
 * Human label for a Google match type letter.
 *
 * @param string $code e, p, b or a.
 * @return string
 */
function wghs_attr_match_label( $code ) {
	$map = array(
		'e' => __( 'exact', 'wghshop' ),
		'p' => __( 'phrase', 'wghshop' ),
		'b' => __( 'broad', 'wghshop' ),
		'a' => __( 'AI Max, no keyword', 'wghshop' ),
	);
	return $map[ strtolower( (string) $code ) ] ?? (string) $code;
}

/**
 * Human label for a Google network letter.
 *
 * @param string $code ValueTrack {network} value.
 * @return string
 */
function wghs_attr_network_label( $code ) {
	$map = array(
		'g'   => __( 'Google search', 'wghshop' ),
		's'   => __( 'search partner', 'wghshop' ),
		'd'   => __( 'Display', 'wghshop' ),
		'ytv' => __( 'YouTube', 'wghshop' ),
		'vp'  => __( 'video partner', 'wghshop' ),
		'gtv' => __( 'Google TV', 'wghshop' ),
		'x'   => __( 'Performance Max', 'wghshop' ),
		'e'   => __( 'app engagement', 'wghshop' ),
	);
	return $map[ strtolower( (string) $code ) ] ?? (string) $code;
}

/**
 * Human label for a Google device letter.
 *
 * @param string $code ValueTrack {device} value.
 * @return string
 */
function wghs_attr_device_label( $code ) {
	$map = array(
		'm' => __( 'mobile', 'wghshop' ),
		't' => __( 'tablet', 'wghshop' ),
		'c' => __( 'computer', 'wghshop' ),
	);
	return $map[ strtolower( (string) $code ) ] ?? (string) $code;
}

/**
 * Every campaign field on a row, labelled, for the Tags panel.
 *
 * Returns the raw value too, because the raw value is what the dashboard joins
 * on and what you paste into Google when something does not reconcile.
 *
 * @param object $r Attribution row.
 * @return array<int, array{label:string, raw:string, note:string}>
 */
function wghs_attr_tag_list( $r ) {
	$get = function ( $k ) use ( $r ) { return isset( $r->$k ) ? (string) $r->$k : ''; };

	$fields = array(
		array( __( 'utm_source', 'wghshop' ),   $get( 'utm_source' ),   '' ),
		array( __( 'utm_medium', 'wghshop' ),   $get( 'utm_medium' ),   '' ),
		array( __( 'utm_campaign', 'wghshop' ), $get( 'utm_campaign' ), '' ),
		array( __( 'utm_term (keyword bid on)', 'wghshop' ), $get( 'utm_term' ), '' ),
		array( __( 'utm_content', 'wghshop' ),  $get( 'utm_content' ),  '' ),
		array( __( 'utm_id', 'wghshop' ),       $get( 'utm_id' ),       '' ),
		array( __( 'match type', 'wghshop' ),   $get( 'match_type' ),   $get( 'match_type' ) ? wghs_attr_match_label( $get( 'match_type' ) ) : '' ),
		array( __( 'campaign id', 'wghshop' ),  $get( 'campaign_id' ),  '' ),
		array( __( 'ad group id', 'wghshop' ),  $get( 'adgroup_id' ),   '' ),
		array( __( 'creative id', 'wghshop' ),  $get( 'creative_id' ),  '' ),
		array( __( 'target id', 'wghshop' ),    $get( 'target_id' ),    '' ),
		array( __( 'network', 'wghshop' ),      $get( 'network' ),      $get( 'network' ) ? wghs_attr_network_label( $get( 'network' ) ) : '' ),
		array( __( 'device', 'wghshop' ),       $get( 'device' ),       $get( 'device' ) ? wghs_attr_device_label( $get( 'device' ) ) : '' ),
		array( __( 'ad placement', 'wghshop' ), $get( 'ad_placement' ), '' ),
		array( __( 'click id', 'wghshop' ),     $get( 'click_id' ),     $get( 'click_type' ) ),
	);

	$out = array();
	foreach ( $fields as $f ) {
		$out[] = array( 'label' => $f[0], 'raw' => $f[1], 'note' => $f[2] );
	}
	return $out;
}

add_action( 'wp_ajax_wghs_attr_update', function () {
	check_ajax_referer( 'wghs_attr' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }

	$act = sanitize_key( $_POST['act'] ?? '' );
	if ( ! in_array( $act, array( 'convert', 'dismiss', 'pend' ), true ) ) { wp_send_json_error(); }

	// Bulk: ids and values arrive as parallel comma separated lists.
	if ( ! empty( $_POST['bulk'] ) ) {
		$ids  = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['ids'] ?? '' ) ) ) );
		$vals = array_map( 'floatval', explode( ',', (string) wp_unslash( $_POST['values'] ?? '' ) ) );

		if ( ! $ids ) { wp_send_json_error(); }

		// A runaway selection should not be able to rewrite the whole table in
		// one request. The screen never lists more than 300 rows anyway.
		$ids = array_slice( $ids, 0, 300 );

		$updated = 0;
		foreach ( $ids as $i => $id ) {
			if ( wghs_attr_apply( $id, $act, $vals[ $i ] ?? 0 ) ) { $updated++; }
		}
		wp_send_json_success( array( 'updated' => $updated ) );
	}

	$ok = wghs_attr_apply(
		$_POST['id'] ?? 0,
		$act,
		(float) ( $_POST['value'] ?? 0 ),
		sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) )
	);
	if ( ! $ok ) { wp_send_json_error(); }
	wp_send_json_success( array( 'updated' => 1 ) );
} );

/* --------------------------------------------------------------------------
 * Export: the exact offline click conversion CSV Google Ads ingests.
 * Ghana is GMT year round, so the timezone parameter is +0000.
 * ------------------------------------------------------------------------ */

add_action( 'admin_post_wghs_attr_export', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Insufficient permissions.' ); }
	check_admin_referer( 'wghs_attr_export' );
	global $wpdb;
	$table = wghs_attr_table();
	$rows  = $wpdb->get_results(
		"SELECT * FROM {$table}
		WHERE status = 'converted' AND exported = 0 AND click_id <> ''
		AND click_type IN ('gclid','gbraid','wbraid')
		ORDER BY converted_at ASC"
	);
	$conv_name = get_theme_mod( 'wghs_offline_conv_name', 'WhatsApp Sale' );
	$currency  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GHS';

	/*
	 * SEND PROFIT, NOT TURNOVER.
	 *
	 * Google's Smart Bidding optimises toward whatever number it is given as
	 * the conversion value. Sending the order total tells it to chase big
	 * baskets, so it learns to buy customers for a GHS 950 microwave at 8%
	 * margin in preference to a GHS 320 blender at 30%, and the account gets
	 * busier and poorer at the same time.
	 *
	 * Sending the MARGIN tells it to chase money you keep. Google's own gross
	 * profit optimisation reports roughly 15% more campaign profit than
	 * revenue optimisation for the same spend, on machinery already paid for.
	 *
	 * The margin percentage lives in the Customizer because it is a business
	 * fact the owner knows and the theme does not. At 0 the behaviour is
	 * unchanged and the full value is sent, so this is opt-in and reversible
	 * in one field.
	 */
	$margin_pct = (float) get_theme_mod( 'wghs_gross_margin_pct', 0 );
	$use_profit = $margin_pct > 0 && $margin_pct < 100;

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=google-ads-conversions-' . gmdate( 'Ymd-His' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Parameters:TimeZone=+0000' ) );
	fputcsv( $out, array( 'Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency' ) );

	$ids = array();
	foreach ( $rows as $r ) {
		// Conversion time must be after the click. If confirmation happened the
		// same second as the click (auto-convert), nudge by one minute.
		$time = $r->converted_at && $r->converted_at > $r->created_at
			? $r->converted_at
			: gmdate( 'Y-m-d H:i:s', strtotime( $r->created_at ) + 60 );
		$value = (float) $r->conv_value;

		if ( $use_profit ) {
			$value = round( $value * ( $margin_pct / 100 ), 2 );
		}

		fputcsv( $out, array(
			$r->click_id,
			$conv_name,
			$time,
			number_format( $value, 2, '.', '' ),
			$currency,
		) );
		$ids[] = (int) $r->id;
	}
	fclose( $out );

	if ( $ids ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET exported = 1, updated_at = %s WHERE id IN (" . implode( ',', $ids ) . ')', current_time( 'mysql', true ) ) );
	}
	exit;
} );

/** Customizer: conversion name must match the offline conversion action in Google Ads. */
add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_setting( 'wghs_gross_margin_pct', array(
		'default'           => 0,
		'sanitize_callback' => function ( $v ) { return max( 0, min( 100, (float) $v ) ); },
	) );
	$wp_customize->add_control( 'wghs_gross_margin_pct', array(
		'label'       => __( 'Average gross margin, %', 'wghshop' ),
		'description' => __( 'Leave at 0 to upload the full order value, which is the old behaviour. Set your real average margin (for example 28) and the export uploads PROFIT instead of turnover, so Google bids for the customers who leave you money rather than the ones with the biggest basket. Google reports roughly 15% more campaign profit from this change. Set it once real dealer costs are known; a wrong number here misprices every bid.', 'wghshop' ),
		'section'     => 'wghs_tracking',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
	) );
	$wp_customize->add_setting( 'wghs_offline_conv_name', array( 'default' => 'WhatsApp Sale', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_offline_conv_name', array(
		'label'       => __( 'Offline conversion name', 'wghshop' ),
		'description' => __( 'Must exactly match the import-type conversion action name created in Google Ads.', 'wghshop' ),
		'section'     => 'wghs_tracking',
		'type'        => 'text',
	) );
}, 40 );
