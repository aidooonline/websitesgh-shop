<?php
/**
 * Enquiry form handler. Emails the admin and stores every submission as a
 * private wghs_enquiry post so nothing is ever lost to a mail failure.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	register_post_type( 'wghs_enquiry', array(
		'label'    => __( 'Enquiries', 'wghshop' ),
		'public'   => false,
		'show_ui'  => true,
		'show_in_menu' => 'woocommerce',
		'supports' => array( 'title', 'editor' ),
		'map_meta_cap' => true,
		'capability_type' => 'post',
	) );
} );

add_action( 'admin_post_nopriv_wghs_enquiry', 'wghs_handle_enquiry' );
add_action( 'admin_post_wghs_enquiry', 'wghs_handle_enquiry' );

function wghs_handle_enquiry() {
	check_admin_referer( 'wghs_enquiry' );
	// Honeypot: bots fill it, humans never see it.
	if ( ! empty( $_POST['wghs_hp'] ) ) { wp_safe_redirect( home_url( '/contact/?sent=1' ) ); exit; }

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$area    = sanitize_text_field( wp_unslash( $_POST['area'] ?? '' ) );
	$product = sanitize_text_field( wp_unslash( $_POST['product'] ?? '' ) );
	$msg     = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	// Name, phone and area are the required lead fields; message and product are optional.
	if ( ! $name || ! $phone || ! $area ) { wp_safe_redirect( home_url( '/contact/' ) ); exit; }

	$body = "Name: {$name}\nPhone: {$phone}\nArea: {$area}";
	if ( $product ) { $body .= "\nProduct: {$product}"; }
	if ( $msg ) { $body .= "\n\n{$msg}"; }

	wp_insert_post( array(
		'post_type'    => 'wghs_enquiry',
		'post_status'  => 'private',
		'post_title'   => $name . ' (' . $phone . ')' . ( $product ? ' - ' . $product : '' ),
		'post_content' => $body,
	) );

	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] Lead from %s', get_bloginfo( 'name' ), $name ),
		$body
	);

	wp_safe_redirect( home_url( '/contact/?sent=1' ) );
	exit;
}
