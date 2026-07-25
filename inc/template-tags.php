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
	// Final fallback to the shop's known number, so the WhatsApp order button
	// never disappears just because the Customizer field was left blank.
	if ( '' === $raw ) {
		$raw = '233542148020';
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
	// Build and escape the base URL WITHOUT the text, then append the encoded
	// message. esc_url() strips %0A (encoded newlines), so we must not run it
	// over the encoded message or the WhatsApp text collapses onto one line.
	$url = esc_url( 'https://wa.me/' . $num );
	if ( '' !== $message ) {
		$url .= '?text=' . rawurlencode( $message );
	}
	return $url;
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
		'WebsitesGH<span class="text-wgh-green">Shop</span>',
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

/* wghs_placeholder_svg now lives in inc/illustrations.php, which draws a
   category aware illustration instead of one generic dark theme icon. */

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
	$slugs = array( 'hp-products', 'dell-products', 'lenovo-products', 'macbooks', 'products-accessories' );
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'slug' => $slugs, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) { return array(); }
	usort( $terms, function ( $a, $b ) { return $b->count <=> $a->count; } );
	return array_slice( $terms, 0, $limit );
}

/**
 * Byline meta for blog listings: category, date, reading time.
 */
function wghs_post_meta() {
	$cats = get_the_category();
	echo '<p class="wghs-meta">';
	if ( $cats ) {
		echo '<span class="wghs-meta__cat">' . esc_html( $cats[0]->name ) . '</span>';
		echo '<span aria-hidden="true">&middot;</span>';
	}
	echo '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
	echo '<span aria-hidden="true">&middot;</span>';
	echo '<span>' . esc_html( wghs_reading_time() ) . '</span>';
	echo '</p>';
}

/**
 * Reading time from the post body, at 200 words per minute.
 */
function wghs_reading_time( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	$mins    = max( 1, (int) ceil( $words / 200 ) );
	/* translators: %d: estimated minutes to read the article. */
	return sprintf( _n( '%d min read', '%d min read', $mins, 'wghshop' ), $mins );
}

/**
 * WhatsApp deep link. Wraps the existing helper under the name the
 * blog templates call, so either name works.
 */
function wghs_whatsapp_link( $message = '' ) {
	return function_exists( 'wghs_wa_link' ) ? wghs_wa_link( $message ) : '';
}
