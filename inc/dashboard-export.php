<?php
/**
 * WGH Intelligence: the signed export endpoint.
 *
 * Sprint 1 of dashboard/docs/ENGINEERING-SPEC.md. The dashboard is a separate
 * Laravel application and it needs the shop's truth: orders, order items and
 * the attribution rows that tie a WhatsApp chat back to an ad click.
 *
 * WHY A SIGNED REST ENDPOINT AND NOT A DATABASE CONNECTION
 * The spec weighed both. A read-only MySQL user is fragile on shared cPanel:
 * remote MySQL is host-locked by IP, the IP moves, and a broken grant is
 * invisible until a sync silently returns nothing. A REST endpoint travels
 * over the same HTTPS that already works, fails loudly with a status code, and
 * carries no credential that can read anything but this one payload.
 *
 * THE CURSOR IS INCLUSIVE, ON PURPOSE
 * The classic delta-sync bug is an exclusive cursor: pull everything with
 * updated_at > cursor, set cursor to the newest row, and silently lose every
 * row written in the same second as that newest row. This endpoint returns
 * rows with updated_at >= cursor and the dashboard upserts on a unique key, so
 * the overlap is free and nothing can fall through the gap. Idempotency is
 * what makes an inclusive cursor safe, and idempotency is the acceptance test.
 *
 * WHY attribution NEEDED AN updated_at COLUMN
 * Attribution rows are not write-once. A pending WhatsApp tap becomes
 * converted later, either automatically when a matching gclid arrives on an
 * order or by hand in WooCommerce > Attribution. A cursor over created_at
 * would pull that row once, while it was still pending, and never again, so
 * the dashboard would hold a permanently stale copy that says no sale
 * happened. The column is stamped explicitly in UTC at every write site, never
 * by MySQL's CURRENT_TIMESTAMP, because that follows the server session
 * timezone and would mix local time into a table that is otherwise UTC.
 *
 * AUTHENTICATION
 * HMAC-SHA256 over method, path, query, timestamp and nonce. The secret never
 * travels. A five minute clock skew window plus a single-use nonce closes the
 * replay window. Comparison is hash_equals, so a wrong signature costs the
 * same time as a right one.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const WGHS_EXPORT_MAX_SKEW  = 300; // Seconds of tolerated clock drift.
const WGHS_EXPORT_MAX_LIMIT = 500; // Rows per page, per stream.

/* --------------------------------------------------------------------------
 * The shared secret
 * ------------------------------------------------------------------------ */

/**
 * The shared secret the dashboard signs with.
 *
 * Prefers a WGHS_DASHBOARD_SECRET constant in wp-config.php, which keeps the
 * secret out of the database and out of any database backup. Falls back to an
 * option so the owner can get running without editing wp-config.
 *
 * @return string Empty string when no secret has been set yet.
 */
function wghs_export_secret() {
	if ( defined( 'WGHS_DASHBOARD_SECRET' ) && WGHS_DASHBOARD_SECRET ) {
		return (string) WGHS_DASHBOARD_SECRET;
	}
	return (string) get_option( 'wghs_dashboard_secret', '' );
}

/**
 * Generate and store a fresh 64 character secret.
 *
 * @return string
 */
function wghs_export_rotate_secret() {
	$secret = bin2hex( random_bytes( 32 ) );
	update_option( 'wghs_dashboard_secret', $secret, false );
	return $secret;
}

/* --------------------------------------------------------------------------
 * Signature verification
 * ------------------------------------------------------------------------ */

/**
 * Build the canonical string that both sides sign.
 *
 * Query arguments are sorted so that a different parameter order is not a
 * different signature. The signature parameters themselves are excluded, since
 * they cannot sign themselves.
 *
 * @param string $method    HTTP method, upper case.
 * @param string $route     REST route, e.g. /wghs/v1/export.
 * @param array  $query     Query arguments.
 * @param string $timestamp Unix timestamp as a string.
 * @param string $nonce     Single use random string.
 * @return string
 */
function wghs_export_canonical( $method, $route, $query, $timestamp, $nonce ) {
	unset( $query['signature'], $query['timestamp'], $query['nonce'], $query['_locale'] );
	ksort( $query );
	$pairs = array();
	foreach ( $query as $k => $v ) {
		$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
	}
	return strtoupper( $method ) . "\n" . $route . "\n" . implode( '&', $pairs ) . "\n" . $timestamp . "\n" . $nonce;
}

/**
 * Permission callback for the export route.
 *
 * @param WP_REST_Request $req Request.
 * @return true|WP_Error
 */
function wghs_export_authorize( WP_REST_Request $req ) {
	$secret = wghs_export_secret();
	if ( '' === $secret ) {
		return new WP_Error( 'wghs_no_secret', 'Dashboard export is not configured on this site.', array( 'status' => 503 ) );
	}

	$timestamp = (string) $req->get_header( 'x_wghs_timestamp' );
	$nonce     = (string) $req->get_header( 'x_wghs_nonce' );
	$signature = (string) $req->get_header( 'x_wghs_signature' );

	if ( '' === $timestamp || '' === $nonce || '' === $signature ) {
		return new WP_Error( 'wghs_unsigned', 'Missing signature headers.', array( 'status' => 401 ) );
	}
	if ( abs( time() - (int) $timestamp ) > WGHS_EXPORT_MAX_SKEW ) {
		return new WP_Error( 'wghs_stale', 'Signature timestamp is outside the accepted window.', array( 'status' => 401 ) );
	}
	if ( strlen( $nonce ) < 16 || strlen( $nonce ) > 64 ) {
		return new WP_Error( 'wghs_bad_nonce', 'Nonce must be 16 to 64 characters.', array( 'status' => 401 ) );
	}

	// Replay guard. A nonce is good once, for slightly longer than the skew
	// window, which is the whole period in which a replay could still verify.
	$key = 'wghs_xn_' . md5( $nonce );
	if ( get_transient( $key ) ) {
		return new WP_Error( 'wghs_replay', 'This nonce has already been used.', array( 'status' => 401 ) );
	}
	set_transient( $key, 1, WGHS_EXPORT_MAX_SKEW * 2 );

	$expected = hash_hmac(
		'sha256',
		wghs_export_canonical( $req->get_method(), '/wghs/v1/export', $req->get_query_params(), $timestamp, $nonce ),
		$secret
	);
	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'wghs_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}
	return true;
}

/* --------------------------------------------------------------------------
 * Route
 * ------------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route( 'wghs/v1', '/export', array(
		'methods'             => 'GET',
		'permission_callback' => 'wghs_export_authorize',
		'callback'            => 'wghs_export_handler',
		'args'                => array(
			'orders_since' => array( 'type' => 'string', 'default' => '' ),
			'attr_since'   => array( 'type' => 'string', 'default' => '' ),
			'orders_offset' => array( 'type' => 'integer', 'default' => 0 ),
			'attr_offset'   => array( 'type' => 'integer', 'default' => 0 ),
			'limit'        => array( 'type' => 'integer', 'default' => 200 ),
			'streams'      => array( 'type' => 'string', 'default' => 'orders,attribution' ),
		),
	) );
} );

/**
 * Normalise a cursor argument to a MySQL UTC datetime string.
 *
 * @param string $raw Incoming value, ISO 8601 or MySQL format.
 * @return string Empty string means "from the beginning".
 */
function wghs_export_cursor( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) { return ''; }
	$ts = strtotime( $raw );
	if ( false === $ts ) { return ''; }
	return gmdate( 'Y-m-d H:i:s', $ts );
}

/**
 * The export handler.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function wghs_export_handler( WP_REST_Request $req ) {
	$limit        = max( 1, min( WGHS_EXPORT_MAX_LIMIT, (int) $req->get_param( 'limit' ) ) );
	$orders_since = wghs_export_cursor( $req->get_param( 'orders_since' ) );
	$attr_since   = wghs_export_cursor( $req->get_param( 'attr_since' ) );

	// One stream can finish paging before the other. The dashboard then drops
	// it from the request rather than asking for rows it will throw away.
	$streams = array_filter( array_map( 'trim', explode( ',', (string) $req->get_param( 'streams' ) ) ) );
	$empty   = array( 'rows' => array(), 'has_more' => false, 'next_cursor' => '', 'next_offset' => 0, 'total' => 0, 'skipped' => true );

	$orders_offset = max( 0, (int) $req->get_param( 'orders_offset' ) );
	$attr_offset   = max( 0, (int) $req->get_param( 'attr_offset' ) );

	$orders = in_array( 'orders', $streams, true ) ? wghs_export_orders( $orders_since, $limit, $orders_offset ) : $empty;
	$attr   = in_array( 'attribution', $streams, true ) ? wghs_export_attribution( $attr_since, $limit, $attr_offset ) : $empty;

	return new WP_REST_Response( array(
		'ok'            => true,
		'schema'        => 2,
		'generated_at'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
		'site'          => home_url( '/' ),
		'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GHS',
		'orders'        => $orders,
		'attribution'   => $attr,
	), 200 );
}

/* --------------------------------------------------------------------------
 * Orders
 * ------------------------------------------------------------------------ */

/**
 * Order statuses that count as a real order for the dashboard.
 *
 * Everything except trashed. Cancelled and failed are deliberately included:
 * a cancelled order still consumed the ad click that produced it, and hiding
 * it would make cost per delivered order look better than it is.
 *
 * @return array
 */
function wghs_export_order_statuses() {
	if ( ! function_exists( 'wc_get_order_statuses' ) ) { return array(); }
	$statuses = array_keys( wc_get_order_statuses() );
	return array_values( array_diff( $statuses, array( 'wc-checkout-draft' ) ) );
}

/**
 * Pull orders modified at or after the cursor.
 *
 * Uses wc_get_orders() rather than a direct query so this works on both the
 * legacy post-table storage and High Performance Order Storage.
 *
 * @param string $since MySQL UTC datetime, or empty for everything.
 * @param int    $limit Page size.
 * @return array
 */
function wghs_export_orders( $since, $limit, $offset = 0 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array( 'rows' => array(), 'has_more' => false, 'next_cursor' => $since, 'next_offset' => 0, 'total' => 0 );
	}

	$query = array(
		'limit'    => $limit,
		'offset'   => $offset,
		'orderby'  => array( 'modified' => 'ASC', 'ID' => 'ASC' ),
		'status'   => wghs_export_order_statuses(),
		'type'     => 'shop_order',
		'paginate' => false,
	);
	if ( '' !== $since ) {
		// WooCommerce reads this as a UTC timestamp when given an integer. A
		// string would be interpreted in site time, which is exactly the
		// timezone bug the spec warns about. Pass the integer.
		$query['date_modified'] = '>=' . strtotime( $since . ' UTC' );
	}

	$found = wc_get_orders( $query );
	$rows  = array();
	foreach ( $found as $order ) {
		$rows[] = wghs_export_order_row( $order );
	}

	// Total for the window. The dashboard asserts against this, and it is what
	// makes "the dashboard holds as many orders as WooCommerce does" checkable
	// without database access.
	$total_query           = $query;
	$total_query['limit']  = -1;
	$total_query['offset'] = 0;
	$total_query['return'] = 'ids';
	$total                 = count( (array) wc_get_orders( $total_query ) );

	$next = $since;
	if ( $rows ) {
		$next = end( $rows )['modified_at'];
	}

	// Paging is by offset within a run, not by moving the timestamp cursor.
	// A timestamp cursor stalls forever when a whole page shares one second,
	// which a bulk backfill or a busy minute can produce.
	return array(
		'rows'        => $rows,
		'has_more'    => ( $offset + count( $rows ) ) < $total,
		'next_offset' => $offset + count( $rows ),
		'next_cursor' => $next,
		'total'       => $total,
	);
}

/**
 * Flatten one WooCommerce order into the dashboard's orders shape.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function wghs_export_order_row( $order ) {
	global $wpdb;

	$created  = $order->get_date_created();
	$modified = $order->get_date_modified();

	$items = array();
	foreach ( $order->get_items() as $item ) {
		$qty     = max( 1, (int) $item->get_quantity() );
		$items[] = array(
			// The line item id, not the product id. Two lines of the same
			// product in one basket are two rows, and keying on the product
			// would collapse them and halve the basket.
			'woo_item_id'    => (int) $item->get_id(),
			'woo_product_id' => (int) $item->get_product_id(),
			'product_name'   => (string) $item->get_name(),
			'qty'            => $qty,
			'unit_price_ghs' => round( (float) $item->get_total() / $qty, 2 ),
		);
	}

	// The ref code and the click id can arrive from two places. Order meta is
	// authoritative when the sale went through the site. For a WhatsApp sale
	// the row in the attribution table is the record, linked by order_id.
	$ref      = (string) $order->get_meta( '_wghs_ref' );
	$click_id = (string) $order->get_meta( '_wghs_click_id' );
	$link     = $wpdb->get_row( $wpdb->prepare(
		'SELECT ref, click_id, click_type, placement, utm_source, utm_medium, utm_campaign,'
		. ' utm_term, utm_content, utm_id, match_type, campaign_id, adgroup_id, creative_id,'
		. ' target_id, network, device, ad_placement, cust_phone, cust_name, cust_area'
		. ' FROM ' . wghs_attr_table() . ' WHERE order_id = %d ORDER BY id DESC LIMIT 1',
		$order->get_id()
	) );

	$pick = function ( $meta_value, $attr_field ) use ( $link ) {
		if ( '' !== (string) $meta_value ) { return (string) $meta_value; }
		return $link && isset( $link->$attr_field ) ? (string) $link->$attr_field : '';
	};

	$phone = (string) $order->get_billing_phone();
	if ( '' === $phone && $link ) { $phone = (string) $link->cust_phone; }

	return array(
		'woo_order_id'   => (int) $order->get_id(),
		'created_at'     => $created ? gmdate( 'Y-m-d H:i:s', $created->getTimestamp() ) : '',
		'modified_at'    => $modified ? gmdate( 'Y-m-d H:i:s', $modified->getTimestamp() ) : '',
		'status'         => (string) $order->get_status(),
		'revenue_ghs'    => round( (float) $order->get_total(), 2 ),
		'currency'       => (string) $order->get_currency(),
		'customer_ref'   => $pick( $ref, 'ref' ),
		'click_id'       => $pick( $click_id, 'click_id' ),
		'click_type'     => $pick( (string) $order->get_meta( '_wghs_click_type' ), 'click_type' ),
		'utm_source'     => $pick( (string) $order->get_meta( '_wghs_utm_source' ), 'utm_source' ),
		'utm_medium'     => $pick( (string) $order->get_meta( '_wghs_utm_medium' ), 'utm_medium' ),
		'utm_campaign'   => $pick( (string) $order->get_meta( '_wghs_utm_campaign' ), 'utm_campaign' ),
		'utm_term'       => $pick( (string) $order->get_meta( '_wghs_utm_term' ), 'utm_term' ),
		'utm_content'    => $pick( (string) $order->get_meta( '_wghs_utm_content' ), 'utm_content' ),
		'utm_id'         => $pick( (string) $order->get_meta( '_wghs_utm_id' ), 'utm_id' ),
		'match_type'     => $pick( (string) $order->get_meta( '_wghs_match_type' ), 'match_type' ),
		'campaign_id'    => $pick( (string) $order->get_meta( '_wghs_campaign_id' ), 'campaign_id' ),
		'adgroup_id'     => $pick( (string) $order->get_meta( '_wghs_adgroup_id' ), 'adgroup_id' ),
		'creative_id'    => $pick( (string) $order->get_meta( '_wghs_creative_id' ), 'creative_id' ),
		'target_id'      => $pick( (string) $order->get_meta( '_wghs_target_id' ), 'target_id' ),
		'network'        => $pick( (string) $order->get_meta( '_wghs_network' ), 'network' ),
		'device'         => $pick( (string) $order->get_meta( '_wghs_device' ), 'device' ),
		'ad_placement'   => $pick( (string) $order->get_meta( '_wghs_ad_placement' ), 'ad_placement' ),
		'placement'      => $link ? (string) $link->placement : '',
		'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
		'customer_phone' => $phone,
		'customer_area'  => $link ? (string) $link->cust_area : (string) $order->get_billing_city(),
		'items'          => $items,
	);
}

/* --------------------------------------------------------------------------
 * Attribution
 * ------------------------------------------------------------------------ */

/**
 * Pull attribution rows updated at or after the cursor.
 *
 * @param string $since MySQL UTC datetime, or empty for everything.
 * @param int    $limit Page size.
 * @return array
 */
function wghs_export_attribution( $since, $limit, $offset = 0 ) {
	global $wpdb;
	$table = wghs_attr_table();

	$cols = 'id, created_at, updated_at, click_id, click_type, product_id, product_name, price,'
		. ' placement, utm_source, utm_medium, utm_campaign, utm_term, utm_content, utm_id,'
		. ' match_type, campaign_id, adgroup_id, creative_id, target_id, network, device,'
		. ' ad_placement, cart_items, status, converted_at, conv_value,'
		. ' order_id, exported, ref, cust_name, cust_phone, cust_area';

	// COALESCE covers any row written before the updated_at column existed, in
	// the window between deploying the theme and the admin_init backfill.
	$stamp = 'COALESCE(updated_at, created_at)';

	if ( '' !== $since ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$cols} FROM {$table} WHERE {$stamp} >= %s ORDER BY {$stamp} ASC, id ASC LIMIT %d OFFSET %d",
			$since,
			$limit,
			$offset
		), ARRAY_A );
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$stamp} >= %s",
			$since
		) );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$cols} FROM {$table} ORDER BY {$stamp} ASC, id ASC LIMIT %d OFFSET %d",
			$limit,
			$offset
		), ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	$out  = array();
	$next = $since;
	foreach ( (array) $rows as $r ) {
		$when = ( null !== $r['updated_at'] && '' !== (string) $r['updated_at'] ) ? $r['updated_at'] : $r['created_at'];
		$next = $when;
		$out[] = array(
			'woo_attr_id'    => (int) $r['id'],
			'created_at'     => (string) $r['created_at'],
			'updated_at'     => (string) $when,
			'click_id'       => (string) $r['click_id'],
			'click_type'     => (string) $r['click_type'],
			'product_id'     => (int) $r['product_id'],
			'product_name'   => (string) $r['product_name'],
			'price_ghs'      => round( (float) $r['price'], 2 ),
			'placement'      => (string) $r['placement'],
			'utm_source'     => (string) $r['utm_source'],
			'utm_medium'     => (string) $r['utm_medium'],
			'utm_campaign'   => (string) $r['utm_campaign'],
			'utm_term'       => (string) $r['utm_term'],
			'utm_content'    => (string) $r['utm_content'],
			'utm_id'         => (string) $r['utm_id'],
			'match_type'     => (string) $r['match_type'],
			'campaign_id'    => (string) $r['campaign_id'],
			'adgroup_id'     => (string) $r['adgroup_id'],
			'creative_id'    => (string) $r['creative_id'],
			'target_id'      => (string) $r['target_id'],
			'network'        => (string) $r['network'],
			'device'         => (string) $r['device'],
			'ad_placement'   => (string) $r['ad_placement'],
			'cart_items'     => (string) $r['cart_items'],
			'status'         => (string) $r['status'],
			'converted_at'   => (string) $r['converted_at'],
			'conv_value_ghs' => round( (float) $r['conv_value'], 2 ),
			'order_id'       => (int) $r['order_id'],
			'exported'       => (int) $r['exported'] ? true : false,
			'ref'            => (string) $r['ref'],
			'cust_name'      => (string) $r['cust_name'],
			'cust_phone'     => (string) $r['cust_phone'],
			'cust_area'      => (string) $r['cust_area'],
		);
	}

	return array(
		'rows'        => $out,
		'has_more'    => ( $offset + count( $out ) ) < $total,
		'next_offset' => $offset + count( $out ),
		'next_cursor' => $next,
		'total'       => $total,
	);
}

/* --------------------------------------------------------------------------
 * Tools > WGH Dashboard Access
 *
 * Registered with add_management_page, the same as the setup screen, because
 * a link to themes.php for a Tools page returns "not allowed". See section 7
 * of AGENT-HANDOVER.md.
 * ------------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_management_page(
		__( 'WGH Dashboard Access', 'wghshop' ),
		__( 'WGH Dashboard Access', 'wghshop' ),
		'manage_options',
		'wghs-dashboard-access',
		'wghs_export_admin_page'
	);
} );

add_action( 'admin_post_wghs_rotate_secret', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed' ); }
	check_admin_referer( 'wghs_rotate_secret' );
	wghs_export_rotate_secret();
	wp_safe_redirect( add_query_arg( array( 'page' => 'wghs-dashboard-access', 'rotated' => 1 ), admin_url( 'tools.php' ) ) );
	exit;
} );

/**
 * Render the access screen.
 */
function wghs_export_admin_page() {
	global $wpdb;
	$secret     = wghs_export_secret();
	$from_const = defined( 'WGHS_DASHBOARD_SECRET' ) && WGHS_DASHBOARD_SECRET;
	$attr_rows  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . wghs_attr_table() );
	$backfilled = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . wghs_attr_table() . ' WHERE updated_at IS NULL' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WGH Dashboard Access', 'wghshop' ); ?></h1>
		<p><?php esc_html_e( 'The WGH Intelligence dashboard reads this shop through one signed endpoint. Nothing else is exposed, and the secret below is the only credential.', 'wghshop' ); ?></p>

		<?php if ( isset( $_GET['rotated'] ) ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'New secret generated. Paste it into the dashboard .env and run the sync again.', 'wghshop' ); ?></p></div>
		<?php endif; ?>

		<table class="widefat" style="max-width:820px">
			<tbody>
				<tr>
					<th style="width:220px"><?php esc_html_e( 'Endpoint', 'wghshop' ); ?></th>
					<td><code><?php echo esc_html( rest_url( 'wghs/v1/export' ) ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Shared secret', 'wghshop' ); ?></th>
					<td>
						<?php if ( '' === $secret ) : ?>
							<em><?php esc_html_e( 'Not set. Generate one below.', 'wghshop' ); ?></em>
						<?php else : ?>
							<code style="user-select:all"><?php echo esc_html( $secret ); ?></code><br>
							<span class="description">
								<?php echo $from_const
									? esc_html__( 'Defined in wp-config.php as WGHS_DASHBOARD_SECRET. This is the safer place for it.', 'wghshop' )
									: esc_html__( 'Stored in the options table. Moving it to wp-config.php as WGHS_DASHBOARD_SECRET keeps it out of database backups.', 'wghshop' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Attribution rows', 'wghshop' ); ?></th>
					<td><?php echo (int) $attr_rows; ?><?php if ( $backfilled ) : ?>
						<span class="description"> <?php printf( esc_html__( '(%d still awaiting an updated_at backfill; reload this page to run it)', 'wghshop' ), (int) $backfilled ); ?></span>
					<?php endif; ?></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! $from_const ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
			<input type="hidden" name="action" value="wghs_rotate_secret">
			<?php wp_nonce_field( 'wghs_rotate_secret' ); ?>
			<button class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Any dashboard still using the old secret will stop syncing until it is updated. Continue?', 'wghshop' ); ?>')">
				<?php echo '' === $secret ? esc_html__( 'Generate secret', 'wghshop' ) : esc_html__( 'Rotate secret', 'wghshop' ); ?>
			</button>
		</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Checking it by hand', 'wghshop' ); ?></h2>
		<p class="description"><?php esc_html_e( 'From the dashboard server, this prints the live order and attribution totals. It is the fastest way to tell a broken sync from an empty shop.', 'wghshop' ); ?></p>
		<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;overflow:auto">php artisan wgh:sync --dry-run</pre>
	</div>
	<?php
}
