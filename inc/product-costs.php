<?php
/**
 * WooCommerce > Product Costs. One screen, every product, two boxes each.
 *
 * WHY THIS EXISTS, AND WHY IT REPLACES THREE EARLIER ATTEMPTS
 * Dealer cost is the single number that unblocks the whole intelligence system:
 * profit per order is what every KEEP and every KILL verdict is measured
 * against, and until costs are known it is a constant in a config file.
 *
 * Three ways to enter it were built and all three failed on contact:
 *
 *   1. A CSV round trip. Correct code, and the sheet was exported and imported
 *      three times without a single cost being entered, because the middle of
 *      that loop is "download through cPanel File Manager, open a spreadsheet,
 *      upload back over the original", which happens outside the terminal and
 *      does not get done.
 *   2. An interactive prompt. cPanel's browser terminal gives PHP no
 *      interactive STDIN, so every question answered itself with the default
 *      and the command reported success having written nothing.
 *   3. A one-line batch command. It worked, and the numbers that got entered
 *      were the ones from the example in the instructions, because typing real
 *      figures into a shell means holding them in your head while you get the
 *      punctuation right.
 *
 * The common thread is that the shop owner lives in WordPress admin, not in a
 * terminal. He is already on this screen looking at Attribution. So the entry
 * belongs here: every product listed, best sellers first, the selling price
 * shown beside the box, and the margin computed in front of him as he types.
 *
 * THE SHOP OWNS THE COST, THE DASHBOARD READS IT
 * Costs are stored as product meta, next to the price they are measured
 * against, and travel to the dashboard through the export endpoint that already
 * carries the catalogue. That means a cost survives the dashboard database
 * being rebuilt, and there is one place to change it rather than two that can
 * disagree.
 *
 * @package WebsitesGH_Shop
 */

defined( 'ABSPATH' ) || exit;

const WGHS_COST_META     = '_wghs_dealer_cost';
const WGHS_DELIVERY_META = '_wghs_delivery_cost';
const WGHS_SUPPLIER_META = '_wghs_supplier';
const WGHS_QUOTED_META   = '_wghs_cost_quoted';

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Product Costs', 'wghshop' ),
		__( 'Product Costs', 'wghshop' ),
		'manage_woocommerce',
		'wghs-costs',
		'wghs_costs_admin_page'
	);
}, 61 );

/**
 * Read a stored cost. Blank stays blank.
 *
 * NEVER RETURNS ZERO FOR MISSING. A zero dealer cost makes a product look like
 * pure profit, and every verdict that touches it comes out wrong in the
 * flattering direction: it inflates profit per order, which relaxes every
 * judgement, which keeps bad keywords alive.
 *
 * @param int    $product_id Product or variation id.
 * @param string $key        Meta key.
 * @return float|null
 */
function wghs_cost_get( $product_id, $key ) {
	$raw = get_post_meta( (int) $product_id, $key, true );

	if ( '' === $raw || null === $raw ) {
		return null;
	}

	return round( (float) $raw, 2 );
}

/**
 * Products to list, best sellers first.
 *
 * total_sales is WooCommerce's own counter, so the products carrying the
 * business float to the top instead of being buried alphabetically halfway
 * down a list of fifty.
 *
 * @return array
 */
function wghs_costs_products() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$products = wc_get_products( array(
		'status'  => 'publish',
		'limit'   => -1,
		'orderby' => 'meta_value_num',
		'meta_key' => 'total_sales',
		'order'   => 'DESC',
		'return'  => 'objects',
	) );

	$out = array();

	foreach ( $products as $product ) {
		if ( ! $product ) {
			continue;
		}

		// A variable parent never appears in an order line and carries no price
		// of its own, so a cost held against it could never be matched to a
		// sale. Its variations are listed instead.
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );

				if ( $child ) {
					$out[] = $child;
				}
			}

			continue;
		}

		$out[] = $product;
	}

	return $out;
}

/**
 * Save the submitted grid.
 */
add_action( 'admin_post_wghs_save_costs', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'wghshop' ) );
	}

	check_admin_referer( 'wghs_save_costs' );

	$dealers    = isset( $_POST['dealer'] ) ? (array) wp_unslash( $_POST['dealer'] ) : array();
	$deliveries = isset( $_POST['delivery'] ) ? (array) wp_unslash( $_POST['delivery'] ) : array();
	$suppliers  = isset( $_POST['supplier'] ) ? (array) wp_unslash( $_POST['supplier'] ) : array();
	$quoted     = isset( $_POST['quoted'] ) ? (array) wp_unslash( $_POST['quoted'] ) : array();

	$saved   = 0;
	$cleared = 0;
	$odd     = array();

	foreach ( $dealers as $product_id => $raw ) {
		$product_id = (int) $product_id;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			continue;
		}

		$dealer = wghs_costs_money( $raw );

		/*
		 * An emptied box means "I do not know this", not "it is free". Clearing
		 * it back to unknown has to be possible: a wrong cost that cannot be
		 * removed is worse than no cost, because it looks like knowledge.
		 */
		if ( null === $dealer ) {
			if ( '' !== get_post_meta( $product_id, WGHS_COST_META, true ) ) {
				delete_post_meta( $product_id, WGHS_COST_META );
				$cleared++;
			}

			continue;
		}

		$price = (float) $product->get_price();

		// Almost always a typo, and silently accepting it turns a healthy
		// product into a KILL verdict on one keystroke. Saved, because it can
		// genuinely be true on a clearance line, but named on the way past.
		if ( $price > 0 && $dealer >= $price ) {
			$odd[] = $product->get_name();
		}

		update_post_meta( $product_id, WGHS_COST_META, $dealer );

		$delivery = wghs_costs_money( isset( $deliveries[ $product_id ] ) ? $deliveries[ $product_id ] : '' );

		if ( null === $delivery ) {
			delete_post_meta( $product_id, WGHS_DELIVERY_META );
		} else {
			update_post_meta( $product_id, WGHS_DELIVERY_META, $delivery );
		}

		$supplier = isset( $suppliers[ $product_id ] ) ? sanitize_text_field( $suppliers[ $product_id ] ) : '';
		if ( '' === $supplier ) {
			delete_post_meta( $product_id, WGHS_SUPPLIER_META );
		} else {
			update_post_meta( $product_id, WGHS_SUPPLIER_META, mb_substr( $supplier, 0, 120 ) );
		}

		update_post_meta( $product_id, WGHS_QUOTED_META, empty( $quoted[ $product_id ] ) ? '' : '1' );

		$saved++;
	}

	wp_safe_redirect( add_query_arg( array(
		'page'    => 'wghs-costs',
		'saved'   => $saved,
		'cleared' => $cleared,
		'odd'     => rawurlencode( implode( ' | ', array_slice( $odd, 0, 5 ) ) ),
	), admin_url( 'admin.php' ) ) );
	exit;
} );

/**
 * Parse a money box. Blank is unknown, never zero.
 *
 * @param mixed $raw Submitted value.
 * @return float|null
 */
function wghs_costs_money( $raw ) {
	$raw = trim( (string) $raw );

	if ( '' === $raw || '-' === $raw ) {
		return null;
	}

	$clean = preg_replace( '/[^0-9.]/', '', str_replace( ',', '', $raw ) );

	return '' === $clean ? null : round( (float) $clean, 2 );
}

/**
 * The screen.
 */
function wghs_costs_admin_page() {
	$products = wghs_costs_products();

	$costed = 0;
	foreach ( $products as $p ) {
		if ( null !== wghs_cost_get( $p->get_id(), WGHS_COST_META ) ) {
			$costed++;
		}
	}

	$saved   = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : -1;
	$cleared = isset( $_GET['cleared'] ) ? (int) $_GET['cleared'] : 0;
	$odd     = isset( $_GET['odd'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['odd'] ) ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Product Costs', 'wghshop' ); ?></h1>

		<p style="max-width:760px">
			<?php esc_html_e( 'What you pay the supplier, and what the rider costs. This is the number every KEEP and KILL decision in the dashboard is measured against: until it is here, profit per order is a guess and so is every verdict.', 'wghshop' ); ?>
		</p>
		<p style="max-width:760px">
			<strong><?php esc_html_e( 'Leave anything you do not know blank.', 'wghshop' ); ?></strong>
			<?php esc_html_e( 'A blank is treated as unknown and the product is left out of every margin. A zero would make it look like pure profit and quietly bend every verdict in your favour.', 'wghshop' ); ?>
		</p>

		<?php if ( $saved >= 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				printf(
					/* translators: 1: saved count, 2: cleared count */
					esc_html__( 'Saved %1$d product cost(s). %2$d cleared back to unknown.', 'wghshop' ),
					(int) $saved,
					(int) $cleared
				);
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( '' !== $odd ) : ?>
			<div class="notice notice-warning"><p>
				<strong><?php esc_html_e( 'Check these: the cost is at or above the selling price.', 'wghshop' ); ?></strong><br>
				<?php echo esc_html( $odd ); ?><br>
				<?php esc_html_e( 'Saved anyway, in case it is a clearance line, but it is usually a typo and it will read as selling at a loss.', 'wghshop' ); ?>
			</p></div>
		<?php endif; ?>

		<p>
			<strong><?php echo (int) $costed; ?></strong>
			<?php
			printf(
				/* translators: %d: total products */
				esc_html__( 'of %d products costed.', 'wghshop' ),
				count( $products )
			);
			?>
			<?php esc_html_e( 'Best sellers first. You do not need them all: the ones that actually sell change the numbers, the rest can wait.', 'wghshop' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wghs_save_costs">
			<?php wp_nonce_field( 'wghs_save_costs' ); ?>

			<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save all costs', 'wghshop' ); ?></button></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'wghshop' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Sold', 'wghshop' ); ?></th>
						<th style="width:110px"><?php esc_html_e( 'You sell at', 'wghshop' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'You pay supplier', 'wghshop' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Rider costs', 'wghshop' ); ?></th>
						<th style="width:150px"><?php esc_html_e( 'Supplier', 'wghshop' ); ?></th>
						<th style="width:70px"><?php esc_html_e( 'Quoted', 'wghshop' ); ?></th>
						<th style="width:150px"><?php esc_html_e( 'You keep', 'wghshop' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $products as $product ) : ?>
					<?php
					$id       = (int) $product->get_id();
					$price    = (float) $product->get_price();
					$dealer   = wghs_cost_get( $id, WGHS_COST_META );
					$delivery = wghs_cost_get( $id, WGHS_DELIVERY_META );
					$supplier = (string) get_post_meta( $id, WGHS_SUPPLIER_META, true );
					$quoted   = '1' === get_post_meta( $id, WGHS_QUOTED_META, true );
					$sold     = (int) get_post_meta( $id, 'total_sales', true );
					?>
					<tr data-price="<?php echo esc_attr( $price ); ?>">
						<td>
							<strong><?php echo esc_html( $product->get_name() ); ?></strong>
							<div style="color:#787c82;font-size:11px">#<?php echo (int) $id; ?></div>
						</td>
						<td><?php echo $sold > 0 ? esc_html( $sold ) : '<span style="color:#a7aaad">none yet</span>'; ?></td>
						<td>GHS <?php echo esc_html( number_format( $price, 2 ) ); ?></td>
						<td>
							<input type="number" step="0.01" min="0" class="wghs-dealer small-text"
								name="dealer[<?php echo (int) $id; ?>]"
								value="<?php echo null === $dealer ? '' : esc_attr( $dealer ); ?>"
								placeholder="<?php esc_attr_e( 'blank', 'wghshop' ); ?>">
						</td>
						<td>
							<input type="number" step="0.01" min="0" class="wghs-delivery small-text"
								name="delivery[<?php echo (int) $id; ?>]"
								value="<?php echo null === $delivery ? '' : esc_attr( $delivery ); ?>"
								placeholder="<?php esc_attr_e( 'blank', 'wghshop' ); ?>">
						</td>
						<td>
							<input type="text" class="regular-text" style="width:100%"
								name="supplier[<?php echo (int) $id; ?>]"
								value="<?php echo esc_attr( $supplier ); ?>">
						</td>
						<td style="text-align:center">
							<input type="checkbox" name="quoted[<?php echo (int) $id; ?>]" value="1" <?php checked( $quoted ); ?>>
						</td>
						<td class="wghs-margin" style="font-family:monospace"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save all costs', 'wghshop' ); ?></button></p>
		</form>
	</div>

	<script>
	/*
	 * Margin in front of you as you type.
	 *
	 * Entering a cost blind and finding out days later that a product loses
	 * money is how a wrong number survives. Seeing "loses GHS 40" appear as you
	 * type catches the typo and the bad supplier deal in the same instant.
	 */
	( function () {
		function money( n ) { return n.toFixed( 2 ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' ); }

		function paint( row ) {
			var price    = parseFloat( row.getAttribute( 'data-price' ) ) || 0,
				dealer   = parseFloat( row.querySelector( '.wghs-dealer' ).value ),
				delivery = parseFloat( row.querySelector( '.wghs-delivery' ).value ) || 0,
				cell     = row.querySelector( '.wghs-margin' );

			if ( isNaN( dealer ) || price <= 0 ) {
				cell.innerHTML = '<span style="color:#a7aaad">not known</span>';
				return;
			}

			var profit = price - dealer - delivery,
				pct    = Math.round( profit / price * 1000 ) / 10;

			if ( profit < 0 ) {
				cell.innerHTML = '<span style="color:#d63638;font-weight:600">loses ' + money( Math.abs( profit ) ) + '</span>';
			} else if ( pct < 10 ) {
				cell.innerHTML = '<span style="color:#996800">' + money( profit ) + ' &middot; ' + pct + '%</span>';
			} else {
				cell.innerHTML = '<span style="color:#007017">' + money( profit ) + ' &middot; ' + pct + '%</span>';
			}
		}

		var rows = document.querySelectorAll( 'tr[data-price]' );

		Array.prototype.forEach.call( rows, function ( row ) {
			paint( row );
			Array.prototype.forEach.call( row.querySelectorAll( 'input[type=number]' ), function ( input ) {
				input.addEventListener( 'input', function () { paint( row ); } );
			} );
		} );
	} )();
	</script>
	<?php
}
