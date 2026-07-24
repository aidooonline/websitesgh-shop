<?php
/**
 * Customizer settings - every image/contact value is editable here.
 * Nothing media-related is hardcoded in templates.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function wghs_customize_register( $wp_customize ) {

	/* ---------------------------------------------------------------------
	 * PANEL: WebsitesGH Shop
	 * ------------------------------------------------------------------- */
	$wp_customize->add_panel( 'wghs_panel', array(
		'title'    => __( 'WebsitesGH Shop Settings', 'wghshop' ),
		'priority' => 20,
	) );

	/* ===== Section: Contact / Social ===== */
	$wp_customize->add_section( 'wghs_contact', array(
		'title' => __( 'Contact & Social', 'wghshop' ),
		'panel' => 'wghs_panel',
	) );

	$contact_fields = array(
		'wghs_phone'      => array( 'label' => 'Phone number', 'default' => '0542148020' ),
		'wghs_whatsapp'   => array( 'label' => 'WhatsApp number (intl, e.g. 233XXXXXXXXX)', 'default' => '233542148020' ),
		'wghs_email'      => array( 'label' => 'Email', 'default' => '' ),
		'wghs_address'    => array( 'label' => 'Location text', 'default' => 'Accra, Ghana' ),
		'wghs_pickup'     => array( 'label' => 'Pickup point note', 'default' => 'Pickup available - meeting point in Accra (confirmed on order).' ),
		'wghs_fb'         => array( 'label' => 'Facebook URL', 'default' => '' ),
		'wghs_ig'         => array( 'label' => 'Instagram URL', 'default' => '' ),
		'wghs_tiktok'     => array( 'label' => 'TikTok URL', 'default' => '' ),
		'wghs_x'          => array( 'label' => 'X (Twitter) URL', 'default' => '' ),
	);
	foreach ( $contact_fields as $id => $f ) {
		$wp_customize->add_setting( $id, array( 'default' => $f['default'], 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( $id, array( 'label' => $f['label'], 'section' => 'wghs_contact', 'type' => 'text' ) );
	}

	/* ===== Section: Hero ===== */
	$wp_customize->add_section( 'wghs_hero', array(
		'title' => __( 'Homepage Hero', 'wghshop' ),
		'panel' => 'wghs_panel',
	) );

	$wp_customize->add_setting( 'wghs_hero_eyebrow', array( 'default' => 'We check the numbers', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_hero_eyebrow', array( 'label' => 'Eyebrow', 'section' => 'wghs_hero', 'type' => 'text' ) );

	$wp_customize->add_setting( 'wghs_hero_title', array( 'default' => 'Appliances and electronics', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_hero_title', array( 'label' => 'Headline (line 1)', 'section' => 'wghs_hero', 'type' => 'text' ) );

	$wp_customize->add_setting( 'wghs_hero_title2', array( 'default' => 'Delivered across Ghana.', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_hero_title2', array( 'label' => 'Headline (line 2, gradient)', 'section' => 'wghs_hero', 'type' => 'text' ) );

	$wp_customize->add_setting( 'wghs_hero_sub', array( 'default' => 'Blenders, kettles, irons, power banks and more. We check the specifications before we sell them. Pay the rider when it reaches you.', 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control( 'wghs_hero_sub', array( 'label' => 'Subtext', 'section' => 'wghs_hero', 'type' => 'textarea' ) );

	$wp_customize->add_setting( 'wghs_hero_image', array( 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'wghs_hero_image', array(
		'label'     => __( 'Hero image (editable - leave empty for gradient only)', 'wghshop' ),
		'section'   => 'wghs_hero',
		'mime_type' => 'image',
	) ) );

	/* ===== Section: Promo / Deals banner ===== */
	$wp_customize->add_section( 'wghs_promo', array(
		'title' => __( 'Promo Banner', 'wghshop' ),
		'panel' => 'wghs_panel',
	) );

	$wp_customize->add_setting( 'wghs_promo_on', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
	$wp_customize->add_control( 'wghs_promo_on', array( 'label' => 'Show top promo bar', 'section' => 'wghs_promo', 'type' => 'checkbox' ) );

	$wp_customize->add_setting( 'wghs_promo_text', array( 'default' => 'Free delivery within Accra on orders above GHS 3,000 · Pay on delivery available', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'wghs_promo_text', array( 'label' => 'Promo bar text', 'section' => 'wghs_promo', 'type' => 'text' ) );

	$wp_customize->add_setting( 'wghs_promo_image', array( 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'wghs_promo_image', array(
		'label'     => __( 'Deals section image (editable)', 'wghshop' ),
		'section'   => 'wghs_promo',
		'mime_type' => 'image',
	) ) );
}
add_action( 'customize_register', 'wghs_customize_register' );
