<?php
/**
 * Reusable template helpers.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Get a Customizer value with default. */
function wghs_opt( $key, $default = '' ) {
	return get_theme_mod( $key, $default );
}

/**
 * Sanitised, digits-only WhatsApp number.
 * Sources: Customizer field first, then the WhatsApp gateway setting.
 * Accepts a raw number or a wa.me / WhatsApp profile link.
 */
function wghs_wa_number() {
	$raw = trim( (string) wghs_opt( 'wghs_whatsapp' ) );
	if ( '' === $raw ) {
		$gw  = get_option( 'woocommerce_wghs_whatsapp_settings', array() );
		$raw = isset( $gw['whatsapp'] ) ? trim( (string) $gw['whatsapp'] ) : '';
	}
	if ( preg_match( '~(?:wa\.me/|api\.whatsapp\.com/send[^ ]*phone=)\+?(\d+)~i', $raw, $m ) ) {
		return $m[1];
	}
	return preg_replace( '/\D+/', '', $raw );
}

/** Build a wa.me link with an optional prefilled message. */
function wghs_wa_link( $message = '' ) {
	$num = wghs_wa_number();
	if ( ! $num ) {
		return '#';
	}
	$url = 'https://wa.me/' . $num;
	if ( $message ) {
		$url .= '?text=' . rawurlencode( $message );
	}
	return esc_url( $url );
}

/**
 * Print the site logo if set, otherwise a clean text wordmark fallback.
 * Keeps the brand visible even before a logo is uploaded (no hardcoded image).
 */
function wghs_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf(
		'<a href="%1$s" class="flex items-baseline gap-1 font-display font-extrabold text-xl tracking-tight text-wgh-ink">%2$s<span class="text-wgh-green">.</span><span class="sr-only">%3$s</span></a>',
		esc_url( home_url( '/' ) ),
		'TechPlug<span class="gradient-text">GH</span>',
		esc_html__( 'Home', 'wghshop' )
	);
}

/**
 * Image for a slot, falling back to a branded SVG placeholder.
 * @param int    $attachment_id Customizer media id (0 if none).
 * @param string $size          Image size.
 * @param string $class         CSS classes.
 */
function wghs_image_or_placeholder( $attachment_id, $size = 'large', $class = '' ) {
	if ( $attachment_id && wp_get_attachment_image_url( (int) $attachment_id, $size ) ) {
		echo wp_get_attachment_image( (int) $attachment_id, $size, false, array( 'class' => $class, 'loading' => 'lazy' ) );
		return;
	}
	echo wghs_placeholder_svg( $class );
}

/** Inline branded placeholder (used only when no media is set; not a stored file). */
function wghs_placeholder_svg( $class = '' ) {
	return '<div class="' . esc_attr( $class ) . ' flex items-center justify-center bg-wgh-bg">'
		. '<svg viewBox="0 0 64 64" width="64" height="64" fill="none" aria-hidden="true">'
		. '<rect x="8" y="14" width="48" height="30" rx="3" stroke="#28324F" stroke-width="2"/>'
		. '<rect x="4" y="46" width="56" height="4" rx="2" fill="#28324F"/>'
		. '<path d="M26 29l5 5 8-9" stroke="#3D7BFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg></div>';
}

/** Product count for a WC category term. */
function wghs_cat_count( $term ) {
	return isset( $term->count ) ? (int) $term->count : 0;
}

/** Fallback primary menu when none is assigned. */
function wghs_default_menu() {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
	$items = array(
		__( 'Home', 'wghshop' )        => home_url( '/' ),
		__( 'Shop', 'wghshop' )        => $shop,
		__( 'Deals', 'wghshop' )       => home_url( '/deals' ),
		__( 'How to Order', 'wghshop' )=> home_url( '/how-to-order' ),
		__( 'About', 'wghshop' )       => home_url( '/about' ),
		__( 'Contact', 'wghshop' )     => home_url( '/contact' ),
	);
	echo '<ul class="flex flex-col lg:flex-row gap-1 lg:gap-7">';
	foreach ( $items as $label => $url ) {
		printf( '<li><a class="menu-link block py-2 lg:py-0" href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/** Minimal nav walker that applies brand classes to top-level links. */
class WGHS_Nav_Walker extends Walker_Nav_Menu {
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$url     = ! empty( $item->url ) ? $item->url : '#';
		$output .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$output .= '<a class="menu-link" href="' . esc_url( $url ) . '">' . esc_html( $item->title ) . '</a>';
	}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
	public function start_lvl( &$output, $depth = 0, $args = null ) { $output .= '<ul class="wghs-submenu">'; }
	public function end_lvl( &$output, $depth = 0, $args = null ) { $output .= '</ul>'; }
}

/** Brand category terms only (whitelist), for nav chips and the homepage brand row. */
function wghs_brand_terms( $limit = 8 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) { return array(); }
	$slugs = array( 'hp-laptops', 'dell-laptops', 'lenovo-laptops', 'macbooks', 'laptop-accessories' );
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'slug' => $slugs, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) { return array(); }
	usort( $terms, function ( $a, $b ) { return $b->count <=> $a->count; } );
	return array_slice( $terms, 0, $limit );
}
