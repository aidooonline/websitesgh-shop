<?php
/**
 * Order via WhatsApp payment gateway.
 *
 * Records the order in WooCommerce (so all order data is tracked and
 * exportable), then sends the customer to WhatsApp with a prefilled
 * order summary for the store owner to confirm payment and delivery.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

class WGHS_Gateway_WhatsApp extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'wghs_whatsapp';
		$this->has_fields         = false;
		$this->method_title       = __( 'Order via WhatsApp', 'wghshop' );
		$this->method_description = __( 'Saves the order in WooCommerce, then redirects the customer to WhatsApp with the order summary (order number, products, prices, total).', 'wghshop' );

		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_whatsapp' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'wghshop' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Order via WhatsApp', 'wghshop' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'   => __( 'Title shown at checkout', 'wghshop' ),
				'type'    => 'text',
				'default' => __( 'Order via WhatsApp', 'wghshop' ),
			),
			'description' => array(
				'title'   => __( 'Description shown at checkout', 'wghshop' ),
				'type'    => 'textarea',
				'default' => __( 'Place your order and finish on WhatsApp. Pay by MoMo, bank transfer, or on delivery within Accra.', 'wghshop' ),
			),
			'whatsapp' => array(
				'title'       => __( 'WhatsApp number or profile link', 'wghshop' ),
				'type'        => 'text',
				'description' => __( 'International format e.g. 233XXXXXXXXX, or a wa.me link. Leave empty to use the number from Appearance > Customize > WebsitesGH Shop Settings > Contact & Social.', 'wghshop' ),
				'default'     => '233542148020',
			),
			'status' => array(
				'title'   => __( 'New order status', 'wghshop' ),
				'type'    => 'select',
				'default' => 'on-hold',
				'options' => array(
					'on-hold'    => __( 'On hold (recommended: awaiting your confirmation)', 'wghshop' ),
					'pending'    => __( 'Pending payment', 'wghshop' ),
					'processing' => __( 'Processing', 'wghshop' ),
				),
			),
		);
	}

	/** Digits-only WhatsApp number from the gateway setting or Customizer fallback. */
	private function wa_number() {
		$raw = trim( (string) $this->get_option( 'whatsapp' ) );
		if ( '' === $raw && function_exists( 'wghs_wa_number' ) ) {
			$raw = wghs_wa_number();
		}
		if ( preg_match( '~(?:wa\.me/|api\.whatsapp\.com/send[^ ]*phone=)\+?(\d+)~i', $raw, $m ) ) {
			return $m[1];
		}
		return preg_replace( '/\D+/', '', $raw );
	}

	/** Hide the gateway until a WhatsApp number is configured somewhere. */
	public function is_available() {
		return parent::is_available() && '' !== $this->wa_number();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_status(
			$this->get_option( 'status', 'on-hold' ),
			__( 'Order placed via WhatsApp checkout. Awaiting confirmation on WhatsApp.', 'wghshop' )
		);
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/** Plain-text order summary for the WhatsApp message. */
	public function order_message( $order ) {
		$money = function ( $amount ) use ( $order ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		};
		$lines   = array();
		$lines[] = 'Hello WebsitesGH Shop, I just placed order #' . $order->get_order_number() . ' on the website.';
		$lines[] = '';
		foreach ( $order->get_items() as $item ) {
			$lines[] = '- ' . $item->get_name() . ' x' . $item->get_quantity() . ' = ' . $money( $item->get_total() );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$lines[] = '- Delivery = ' . $money( $order->get_shipping_total() );
		}
		$lines[] = '';
		$lines[] = 'Total: ' . $money( $order->get_total() );
		$lines[] = 'Name: ' . $order->get_formatted_billing_full_name();
		if ( $order->get_billing_phone() ) {
			$lines[] = 'Phone: ' . $order->get_billing_phone();
		}
		return implode( "\n", $lines );
	}

	public function wa_url( $order ) {
		return 'https://wa.me/' . $this->wa_number() . '?text=' . rawurlencode( $this->order_message( $order ) );
	}

	/** Thank-you page: confirm the order, then hand off to WhatsApp. */
	public function thankyou_whatsapp( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || '' === $this->wa_number() ) { return; }
		$url = $this->wa_url( $order );
		?>
		<div class="card" style="padding:2rem;margin:1.5rem 0;text-align:center">
			<h2 class="font-display" style="font-size:1.5rem;font-weight:700;margin-bottom:.5rem"><?php esc_html_e( 'One last step: send your order on WhatsApp', 'wghshop' ); ?></h2>
			<p style="color:#94A3C4;max-width:34rem;margin:0 auto 1.25rem"><?php esc_html_e( 'Your order is saved. Tap the button to send it to us on WhatsApp so we can confirm payment and arrange delivery or pickup.', 'wghshop' ); ?></p>
			<a class="btn-wa" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Send order on WhatsApp', 'wghshop' ); ?></a>
			<p style="color:#94A3C4;font-size:.8rem;margin-top:1rem"><?php esc_html_e( 'Opening WhatsApp automatically...', 'wghshop' ); ?></p>
		</div>
		<script>setTimeout(function(){ window.location.href = <?php echo wp_json_encode( $url ); ?>; }, 2500);</script>
		<?php
	}
}

add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
	$methods[] = 'WGHS_Gateway_WhatsApp';
	return $methods;
} );

/* =========================================================
   Buy on WhatsApp: direct purchase flow.
   Creates a pending WooCommerce order (for tracking, reporting
   and printing), then redirects the buyer straight to WhatsApp
   with the device details and the order reference.
   ========================================================= */

/** Endpoint URL for a product's Buy on WhatsApp button. */
function wghs_wa_buy_url( $product_id ) {
	return add_query_arg( 'wghs_wa_buy', (int) $product_id, home_url( '/' ) );
}

add_action( 'template_redirect', 'wghs_wa_buy_redirect' );
function wghs_wa_buy_redirect() {
	if ( empty( $_GET['wghs_wa_buy'] ) ) { return; }
	$pid     = absint( $_GET['wghs_wa_buy'] );
	$product = wc_get_product( $pid );
	$number  = function_exists( 'wghs_wa_number' ) ? wghs_wa_number() : '';
	if ( ! $product || '' === $number ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/* One tracked order per product per visitor per hour, light IP rate limit. */
	$order  = null;
	$cookie = 'wghs_wa_order_' . $pid;
	if ( ! empty( $_COOKIE[ $cookie ] ) ) {
		$existing = wc_get_order( absint( $_COOKIE[ $cookie ] ) );
		if ( $existing && 'pending' === $existing->get_status() ) { $order = $existing; }
	}
	if ( ! $order ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'wghs_wa_rl_' . md5( $ip );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			$maybe = wc_create_order( array( 'status' => 'pending', 'created_via' => 'whatsapp_button' ) );
			if ( ! is_wp_error( $maybe ) ) {
				$maybe->add_product( $product, 1 );
				$maybe->calculate_totals();
				$maybe->add_order_note( __( 'Created from a Buy on WhatsApp click. Confirm details with the customer in chat, then update the order status.', 'wghshop' ) );
				$maybe->save();
				$order = $maybe;
				setcookie( $cookie, (string) $order->get_id(), time() + HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
			}
		}
	}

	$price = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
	$spec  = trim( wp_strip_all_tags( $product->get_short_description() ) );

	$lines   = array();
	$lines[] = 'Hello WebsitesGH Shop, I want to buy this products:';
	$lines[] = '';
	$lines[] = $product->get_name();
	if ( $spec ) { $lines[] = $spec; }
	$lines[] = 'Price: ' . $price;
	if ( $product->get_sku() ) { $lines[] = 'SKU: ' . $product->get_sku(); }
	$lines[] = get_permalink( $pid );
	if ( $order ) {
		$lines[] = '';
		$lines[] = 'Order ref: #' . $order->get_order_number();
	}

	wp_redirect( 'https://wa.me/' . $number . '?text=' . rawurlencode( implode( "\n", $lines ) ) );
	exit;
}

/* =========================================================
   Checkout Blocks support for the Order via WhatsApp gateway
   (the block checkout hides classic gateways otherwise).
   ========================================================= */
add_action( 'woocommerce_blocks_loaded', function () {
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) { return; }

	class WGHS_Gateway_WhatsApp_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
		protected $name = 'wghs_whatsapp';
		public function initialize() {
			$this->settings = get_option( 'woocommerce_wghs_whatsapp_settings', array() );
		}
		public function is_active() {
			$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
			return isset( $gateways['wghs_whatsapp'] ) && $gateways['wghs_whatsapp']->is_available();
		}
		public function get_payment_method_script_handles() {
			wp_register_script(
				'wghs-whatsapp-blocks',
				WGHS_URI . '/assets/js/whatsapp-checkout-block.js',
				array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
				WGHS_VERSION,
				true
			);
			return array( 'wghs-whatsapp-blocks' );
		}
		public function get_payment_method_data() {
			$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
			$g        = isset( $gateways['wghs_whatsapp'] ) ? $gateways['wghs_whatsapp'] : null;
			return array(
				'title'       => $g ? $g->get_option( 'title' ) : __( 'Order via WhatsApp', 'wghshop' ),
				'description' => $g ? $g->get_option( 'description' ) : '',
			);
		}
	}

	add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
		$registry->register( new WGHS_Gateway_WhatsApp_Blocks() );
	} );
} );

/* =========================================================
   WhatsApp CART checkout: creates a tracked pending order from
   the entire cart, then redirects to WhatsApp with the full
   itemised order and the order reference. Used by the cart
   page checkout button and the mini-cart drawer.
   ========================================================= */

/** Nonce-protected endpoint URL for cart checkout. */
function wghs_wa_cart_url() {
	return wp_nonce_url( add_query_arg( 'wghs_wa_cart', '1', home_url( '/' ) ), 'wghs_wa_cart' );
}

add_action( 'template_redirect', 'wghs_wa_cart_checkout' );
function wghs_wa_cart_checkout() {
	if ( empty( $_GET['wghs_wa_cart'] ) ) { return; }
	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wghs_wa_cart' ) ) {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
	$number = function_exists( 'wghs_wa_number' ) ? wghs_wa_number() : '';
	if ( '' === $number || ! WC()->cart || WC()->cart->is_empty() ) {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	$money = function ( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	};

	/* Create the tracked order from the cart. */
	$order = wc_create_order( array( 'status' => 'pending', 'created_via' => 'whatsapp_cart' ) );
	$lines = array();
	if ( ! is_wp_error( $order ) ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			$p = $item['data'];
			if ( ! $p || ! $p->exists() ) { continue; }
			$order->add_product( $p, $item['quantity'] );
			$lines[] = '- ' . $p->get_name() . ' x' . $item['quantity'] . ' = ' . $money( (float) $p->get_price() * (int) $item['quantity'] );
		}
		$order->calculate_totals();
		$order->add_order_note( __( 'Created from cart checkout on WhatsApp. Confirm details with the customer in chat, then update the order status.', 'wghshop' ) );
		$order->save();
	} else {
		foreach ( WC()->cart->get_cart() as $item ) {
			$p = $item['data'];
			if ( $p && $p->exists() ) {
				$lines[] = '- ' . $p->get_name() . ' x' . $item['quantity'] . ' = ' . $money( (float) $p->get_price() * (int) $item['quantity'] );
			}
		}
	}

	$msg = function_exists( 'wghs_wa_cart_message' ) ? wghs_wa_cart_message() : '';
	if ( '' === $msg ) {
		// Fallback if the shared builder is unavailable for any reason.
		$msg   = array();
		$msg[] = '*New order from WebsitesGH Shop*';
		$msg[] = '';
		foreach ( WC()->cart->get_cart() as $item ) {
			$p = $item['data'];
			if ( $p && $p->exists() ) {
				$msg[] = '- ' . $p->get_name() . ' x' . $item['quantity'] . ' = ' . $money( (float) $p->get_price() * (int) $item['quantity'] );
			}
		}
		$msg[] = '';
		$msg[] = '*Total: ' . $money( WC()->cart->get_total( 'edit' ) ) . '*';
		$msg   = implode( "\n", $msg );
	}
	if ( ! is_wp_error( $order ) ) {
		$msg .= "\nOrder ref: #" . $order->get_order_number();
	}

	WC()->cart->empty_cart();

	wp_redirect( 'https://wa.me/' . $number . '?text=' . rawurlencode( $msg ) );
	exit;
}
