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
		click_id VARCHAR(191) NOT NULL DEFAULT '',
		click_type VARCHAR(10) NOT NULL DEFAULT '',
		product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		product_name VARCHAR(191) NOT NULL DEFAULT '',
		price DECIMAL(10,2) NOT NULL DEFAULT 0,
		placement VARCHAR(60) NOT NULL DEFAULT '',
		utm_source VARCHAR(60) NOT NULL DEFAULT '',
		utm_medium VARCHAR(60) NOT NULL DEFAULT '',
		utm_campaign VARCHAR(120) NOT NULL DEFAULT '',
		status VARCHAR(12) NOT NULL DEFAULT 'pending',
		converted_at DATETIME NULL DEFAULT NULL,
		conv_value DECIMAL(10,2) NOT NULL DEFAULT 0,
		order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		exported TINYINT(1) NOT NULL DEFAULT 0,
		ref VARCHAR(12) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY status (status),
		KEY click_id (click_id(32)),
		KEY created_at (created_at),
		KEY ref (ref)
	) {$charset};" );
	update_option( 'wghs_attr_db_version', '1.1' );
}
add_action( 'after_switch_theme', 'wghs_attr_install' );
add_action( 'admin_init', function () {
	if ( '1.1' !== get_option( 'wghs_attr_db_version' ) ) { wghs_attr_install(); }
} );

/* --------------------------------------------------------------------------
 * Front end: capture the click IDs and UTMs, log WhatsApp taps.
 * ------------------------------------------------------------------------ */

add_action( 'wp_footer', function () {
	$rest = esc_url_raw( rest_url( 'wghs/v1/wa-click' ) );
	?>
	<script>
	(function () {
		'use strict';
		/* 1. Persist ad click IDs and UTMs for 90 days, first party. */
		var qs = new URLSearchParams(location.search);
		var keep = ['gclid','gbraid','wbraid','utm_source','utm_medium','utm_campaign'];
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
		document.addEventListener('click', function (e) {
			var a = e.target.closest('a[href*="wa.me"]');
			if (!a) { return; }
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
			var payload = {
				ref: ref,
				click_id: ck('gclid') || ck('gbraid') || ck('wbraid'),
				click_type: ck('gclid') ? 'gclid' : (ck('gbraid') ? 'gbraid' : (ck('wbraid') ? 'wbraid' : '')),
				product_id: parseInt(a.getAttribute('data-product-id') || (document.body.className.match(/postid-(\d+)/) || [0,0])[1], 10) || 0,
				placement: a.getAttribute('data-wghs-event') || 'generic',
				utm_source: ck('utm_source'), utm_medium: ck('utm_medium'), utm_campaign: ck('utm_campaign')
			};
			try {
				navigator.sendBeacon('<?php echo $rest; // phpcs:ignore ?>', new Blob([JSON.stringify(payload)], { type: 'application/json' }));
			} catch (err) { /* never block the tap */ }
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

			$product_id   = absint( $p['product_id'] ?? 0 );
			$product_name = '';
			$price        = 0;
			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_name = $product->get_name();
					$price        = (float) $product->get_price();
				}
			}

			// Basic flood guard: same click id + product within 60s is a double tap.
			$click_id = substr( sanitize_text_field( $p['click_id'] ?? '' ), 0, 191 );
			$dupe = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . wghs_attr_table() . " WHERE click_id = %s AND product_id = %d AND created_at > %s LIMIT 1",
				$click_id, $product_id, gmdate( 'Y-m-d H:i:s', time() - 60 )
			) );
			if ( $dupe ) { return new WP_REST_Response( array( 'ok' => true, 'dupe' => true ), 200 ); }

			$wpdb->insert( wghs_attr_table(), array(
				'created_at'   => current_time( 'mysql', true ),
				'click_id'     => $click_id,
				'click_type'   => in_array( $p['click_type'] ?? '', array( 'gclid', 'gbraid', 'wbraid' ), true ) ? $p['click_type'] : '',
				'product_id'   => $product_id,
				'product_name' => $product_name,
				'price'        => $price,
				'placement'    => substr( sanitize_text_field( $p['placement'] ?? '' ), 0, 60 ),
				'utm_source'   => substr( sanitize_text_field( $p['utm_source'] ?? '' ), 0, 60 ),
				'utm_medium'   => substr( sanitize_text_field( $p['utm_medium'] ?? '' ), 0, 60 ),
				'utm_campaign' => substr( sanitize_text_field( $p['utm_campaign'] ?? '' ), 0, 120 ),
				'ref'          => substr( preg_replace( '/[^A-Z0-9-]/', '', strtoupper( (string) ( $p['ref'] ?? '' ) ) ), 0, 12 ),
			) );
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

	$click_id = '';
	$type     = '';
	foreach ( array( 'gclid', 'gbraid', 'wbraid' ) as $k ) {
		if ( ! empty( $_COOKIE[ 'wghs_' . $k ] ) ) {
			$click_id = substr( sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $k ] ) ), 0, 191 );
			$type     = $k;
			break;
		}
	}
	$utm = array();
	foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
		$utm[ $k ] = ! empty( $_COOKIE[ 'wghs_' . $k ] ) ? substr( sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $k ] ) ), 0, 120 ) : '';
	}

	$wpdb->insert( wghs_attr_table(), array(
		'created_at'   => current_time( 'mysql', true ),
		'click_id'     => $click_id,
		'click_type'   => $type,
		'product_id'   => (int) $product_id,
		'product_name' => $product->get_name(),
		'price'    => (float) $product->get_price(),
		'placement'    => 'add_to_cart',
		'utm_source'   => $utm['utm_source'],
		'utm_medium'   => $utm['utm_medium'],
		'utm_campaign' => $utm['utm_campaign'],
		'status'       => 'cart',
	) );
}, 20, 3 );

add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	global $wpdb;
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	$click_id = '';
	$type     = '';
	foreach ( array( 'gclid', 'gbraid', 'wbraid' ) as $k ) {
		if ( ! empty( $_COOKIE[ 'wghs_' . $k ] ) ) {
			$click_id = substr( sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $k ] ) ), 0, 191 );
			$type     = $k;
			break;
		}
	}
	foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
		if ( ! empty( $_COOKIE[ 'wghs_' . $k ] ) ) {
			$order->update_meta_data( '_wghs_' . $k, sanitize_text_field( wp_unslash( $_COOKIE[ 'wghs_' . $k ] ) ) );
		}
	}
	if ( $click_id ) { $order->update_meta_data( '_wghs_click_id', $click_id ); }
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
			$wpdb->update( wghs_attr_table(), $data, array( 'id' => $pending ) );
		} else {
			$items = $order->get_items();
			$first = $items ? reset( $items ) : null;
			$wpdb->insert( wghs_attr_table(), array_merge( $data, array(
				'created_at'   => current_time( 'mysql', true ),
				'click_id'     => $click_id,
				'click_type'   => $type,
				'product_id'   => $first ? (int) $first->get_product_id() : 0,
				'product_name' => $first ? $first->get_name() : __( 'On-site order', 'wghshop' ),
				'price'        => (float) $order->get_total(),
				'placement'    => 'checkout',
				'utm_source'   => (string) $order->get_meta( '_wghs_utm_source' ),
				'utm_medium'   => (string) $order->get_meta( '_wghs_utm_medium' ),
				'utm_campaign' => (string) $order->get_meta( '_wghs_utm_campaign' ),
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
	$unexported = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='converted' AND exported=0 AND click_id <> ''" );

	$nonce = wp_create_nonce( 'wghs_attr' );
	$ajax  = admin_url( 'admin-ajax.php' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Attribution', 'wghshop' ); ?></h1>
		<p><?php esc_html_e( 'Every ad-tracked WhatsApp tap and on-site order. On-site orders auto-convert. For WhatsApp sales: set the value if it differs, then click Sold. Export sends only new converted rows with a Google click ID.', 'wghshop' ); ?></p>

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

		<table class="widefat striped" style="clear:both;margin-top:8px">
			<thead><tr>
				<th><?php esc_html_e( 'When (GMT)', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Product', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Placement', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Source', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Ref', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Click ID', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Value (GHS)', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wghshop' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'Nothing here yet. Rows appear when visitors tap WhatsApp or order with an ad click ID present.', 'wghshop' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr data-id="<?php echo (int) $r->id; ?>">
					<td><?php echo esc_html( $r->created_at ); ?></td>
					<td><?php echo esc_html( $r->product_name ?: '#' . $r->product_id ); ?><?php echo $r->order_id ? ' <a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ) . '">#' . (int) $r->order_id . '</a>' : ''; ?></td>
					<td><?php echo esc_html( $r->placement ); ?></td>
					<td><?php echo esc_html( trim( $r->utm_source . ' / ' . $r->utm_campaign, ' /' ) ?: ( $r->click_id ? 'google' : 'direct' ) ); ?></td>
					<td><strong><?php echo esc_html( $r->ref ); ?></strong></td>
					<td><?php echo $r->click_id ? '<code title="' . esc_attr( $r->click_id ) . '">' . esc_html( substr( $r->click_id, 0, 10 ) ) . '&hellip;</code> <small>' . esc_html( $r->click_type ) . '</small>' : '<small>' . esc_html__( 'none', 'wghshop' ) . '</small>'; ?></td>
					<td><input type="number" step="0.01" class="wghs-attr-value small-text" value="<?php echo esc_attr( $r->conv_value > 0 ? $r->conv_value : $r->price ); ?>" <?php disabled( 'converted' === $r->status && $r->exported ); ?>></td>
					<td><strong><?php echo esc_html( $r->status ); ?></strong><?php echo $r->exported ? ' &middot; ' . esc_html__( 'exported', 'wghshop' ) : ''; ?></td>
					<td style="white-space:nowrap">
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
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.wghs-attr-act');
		if (!b) { return; }
		var tr = b.closest('tr');
		var body = new URLSearchParams({
			action: 'wghs_attr_update', _wpnonce: '<?php echo esc_js( $nonce ); ?>',
			id: tr.dataset.id, act: b.dataset.act,
			value: (tr.querySelector('.wghs-attr-value') || { value: 0 }).value
		});
		b.disabled = true;
		fetch('<?php echo esc_js( $ajax ); ?>', { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function () { location.reload(); });
	});
	</script>
	<?php
}

add_action( 'wp_ajax_wghs_attr_update', function () {
	check_ajax_referer( 'wghs_attr' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
	global $wpdb;
	$id  = absint( $_POST['id'] ?? 0 );
	$act = sanitize_key( $_POST['act'] ?? '' );
	$val = (float) ( $_POST['value'] ?? 0 );
	if ( ! $id ) { wp_send_json_error(); }

	if ( 'convert' === $act ) {
		$wpdb->update( wghs_attr_table(), array(
			'status'       => 'converted',
			'converted_at' => current_time( 'mysql', true ),
			'conv_value'   => $val,
		), array( 'id' => $id ) );
	} elseif ( 'dismiss' === $act ) {
		$wpdb->update( wghs_attr_table(), array( 'status' => 'dismissed' ), array( 'id' => $id ) );
	} elseif ( 'pend' === $act ) {
		$wpdb->update( wghs_attr_table(), array( 'status' => 'pending', 'converted_at' => null, 'exported' => 0 ), array( 'id' => $id ) );
	}
	wp_send_json_success();
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
		"SELECT * FROM {$table} WHERE status = 'converted' AND exported = 0 AND click_id <> '' ORDER BY converted_at ASC"
	);
	$conv_name = get_theme_mod( 'wghs_offline_conv_name', 'WhatsApp Sale' );
	$currency  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GHS';

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
		fputcsv( $out, array(
			$r->click_id,
			$conv_name,
			$time,
			number_format( (float) $r->conv_value, 2, '.', '' ),
			$currency,
		) );
		$ids[] = (int) $r->id;
	}
	fclose( $out );

	if ( $ids ) {
		$wpdb->query( "UPDATE {$table} SET exported = 1 WHERE id IN (" . implode( ',', $ids ) . ')' );
	}
	exit;
} );

/** Customizer: conversion name must match the offline conversion action in Google Ads. */
add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_setting( 'wghs_offline_conv_name', array( 'default' => 'WhatsApp Sale', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_offline_conv_name', array(
		'label'       => __( 'Offline conversion name', 'wghshop' ),
		'description' => __( 'Must exactly match the import-type conversion action name created in Google Ads.', 'wghshop' ),
		'section'     => 'wghs_tracking',
		'type'        => 'text',
	) );
}, 40 );
