<?php
/**
 * Mobile Money payment gateway.
 *
 * Manual MoMo. The customer selects it, sees the number, sends the money and
 * gives us the transaction ID. The order is placed on hold until we confirm the
 * transfer, so nothing ships against an unconfirmed payment.
 *
 * This is deliberately not an automated MoMo integration. Those need a merchant
 * agreement and API credentials. Manual works from day one and costs nothing.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

class WGHS_Gateway_MoMo extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'wghs_momo';
		$this->has_fields         = true;
		$this->method_title       = __( 'Mobile Money', 'wghshop' );
		$this->method_description = __( 'Manual mobile money. Shows the customer your MoMo number at checkout and holds the order until you confirm the transfer. No merchant API needed.', 'wghshop' );

		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_instructions' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'wghshop' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Mobile Money', 'wghshop' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'wghshop' ),
				'type'        => 'text',
				'description' => __( 'What the customer sees at checkout.', 'wghshop' ),
				'default'     => __( 'Mobile Money', 'wghshop' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'   => __( 'Description', 'wghshop' ),
				'type'    => 'textarea',
				'default' => __( 'Send the total to our mobile money number, then give us the transaction ID. We dispatch once it clears. Prefer to pay at the door instead? Choose pay on delivery.', 'wghshop' ),
			),
			'momo_number' => array(
				'title'       => __( 'Mobile money number', 'wghshop' ),
				'type'        => 'text',
				'description' => __( 'The number customers send to. Change it here at any time, nothing is hardcoded.', 'wghshop' ),
				'default'     => '0542148020',
				'desc_tip'    => true,
			),
			'momo_name' => array(
				'title'       => __( 'Registered name', 'wghshop' ),
				'type'        => 'text',
				'description' => __( 'The name that appears on the customer MoMo prompt. Showing it up front prevents fraud and reduces abandoned payments.', 'wghshop' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'hold_order' => array(
				'title'       => __( 'Hold until confirmed', 'wghshop' ),
				'type'        => 'checkbox',
				'label'       => __( 'Put the order on hold until the transfer is confirmed', 'wghshop' ),
				'description' => __( 'Strongly recommended. Leave this on so stock is never released against an unconfirmed payment.', 'wghshop' ),
				'default'     => 'yes',
			),
		);
	}

	/**
	 * Transaction ID field at checkout. Optional, since many customers pay after
	 * placing the order. Blocking on it here loses sales.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
		$number = $this->get_option( 'momo_number' );
		$name   = $this->get_option( 'momo_name' );
		if ( $number ) {
			echo '<p class="wghs-momo-number"><strong>' . esc_html__( 'Send to:', 'wghshop' ) . '</strong> ' . esc_html( $number );
			if ( $name ) {
				echo ' (' . esc_html( $name ) . ')';
			}
			echo '</p>';
		}
		echo '<p class="form-row form-row-wide">';
		echo '<label for="wghs_momo_txn">' . esc_html__( 'Transaction ID (optional)', 'wghshop' ) . '</label>';
		echo '<input type="text" class="input-text" name="wghs_momo_txn" id="wghs_momo_txn" autocomplete="off" placeholder="' . esc_attr__( 'Paste it here if you have already paid', 'wghshop' ) . '">';
		echo '</p>';
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$txn = isset( $_POST['wghs_momo_txn'] ) ? sanitize_text_field( wp_unslash( $_POST['wghs_momo_txn'] ) ) : '';
		if ( $txn ) {
			$order->update_meta_data( '_wghs_momo_txn', $txn );
			$order->add_order_note( sprintf(
				/* translators: %s: mobile money transaction id supplied by the customer. */
				__( 'Customer supplied mobile money transaction ID: %s. Verify before dispatch.', 'wghshop' ),
				$txn
			) );
		}

		if ( 'yes' === $this->get_option( 'hold_order' ) ) {
			$order->update_status( 'on-hold', __( 'Awaiting mobile money confirmation.', 'wghshop' ) );
		} else {
			$order->payment_complete();
		}

		$order->save();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Repeat the number on the thank you page. Most people pay after checkout,
	 * not during, so this is where the number actually gets used.
	 */
	public function thankyou_instructions( $order_id ) {
		$number = $this->get_option( 'momo_number' );
		if ( ! $number ) { return; }
		$order = wc_get_order( $order_id );
		$name  = $this->get_option( 'momo_name' );

		echo '<section class="wghs-momo-thanks">';
		echo '<h2>' . esc_html__( 'Complete your mobile money payment', 'wghshop' ) . '</h2>';
		echo '<ol>';
		echo '<li>' . sprintf(
			/* translators: 1: order total, 2: mobile money number. */
			esc_html__( 'Send %1$s to %2$s.', 'wghshop' ),
			wp_kses_post( $order ? $order->get_formatted_order_total() : '' ),
			'<strong>' . esc_html( $number ) . '</strong>' . ( $name ? ' (' . esc_html( $name ) . ')' : '' )
		) . '</li>';
		echo '<li>' . esc_html__( 'Send us the transaction ID on WhatsApp with your order number.', 'wghshop' ) . '</li>';
		echo '<li>' . esc_html__( 'We confirm and dispatch. You can still inspect the item on delivery.', 'wghshop' ) . '</li>';
		echo '</ol>';

		if ( function_exists( 'wghs_whatsapp_link' ) && $order ) {
			$msg = sprintf(
				/* translators: %s: order number. */
				__( 'Hello, I have paid by mobile money for order #%s. Transaction ID:', 'wghshop' ),
				$order->get_order_number()
			);
			$link = wghs_whatsapp_link( $msg );
			if ( $link ) {
				echo '<a class="wghs-btn wghs-btn--primary" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">'
					. esc_html__( 'Send transaction ID on WhatsApp', 'wghshop' ) . '</a>';
			}
		}
		echo '</section>';
	}
}

add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
	$methods[] = 'WGHS_Gateway_MoMo';
	return $methods;
} );

/**
 * Show the transaction ID on the admin order screen, so it can be checked
 * against the MoMo statement without opening the notes.
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$txn = $order->get_meta( '_wghs_momo_txn' );
	if ( $txn ) {
		echo '<p><strong>' . esc_html__( 'MoMo transaction ID:', 'wghshop' ) . '</strong> ' . esc_html( $txn ) . '</p>';
	}
} );
