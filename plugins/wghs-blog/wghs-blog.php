<?php
/**
 * Plugin Name: WebsitesGH Shop Blog and Archive
 * Description: Adds a professional, native-looking blog and article archive to the WebsitesGH Shop storefront. Activate and it creates a Guides and Reviews hub, adds it to the primary menu, and styles everything to match the Aurora theme. No theme edits.
 * Version: 1.0.0
 * Author: WebsitesGH Shop
 * License: GPL-2.0-or-later
 * Text Domain: wghs-blog
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'TPGB_VERSION', '1.0.0' );
define( 'TPGB_FILE', __FILE__ );
define( 'TPGB_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPGB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Defaults (filterable so nothing needs an admin screen: just activate).
 */
function tpgb_page_slug()  { return apply_filters( 'tpgb_page_slug', 'blog' ); }
function tpgb_page_title() { return apply_filters( 'tpgb_page_title', 'Guides and Reviews' ); }
function tpgb_menu_label() { return apply_filters( 'tpgb_menu_label', 'Blog' ); }
function tpgb_per_page()   { return (int) apply_filters( 'tpgb_per_page', 12 ); }
function tpgb_eyebrow()    { return apply_filters( 'tpgb_eyebrow', 'Journal' ); }
function tpgb_intro()      { return apply_filters( 'tpgb_intro', 'Reviews, comparisons and honest buyer guides for UK-used business products in Ghana. Real specs, real prices, updated as new stock lands.' ); }

/* ---------------------------------------------------------------------------
 * Activation: create (or adopt) the hub page, then flush rewrites.
 * ------------------------------------------------------------------------- */
register_activation_hook( __FILE__, 'tpgb_activate' );
function tpgb_activate() {
	tpgb_get_or_create_page();
	tpgb_ensure_primary_menu();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Return the hub page ID, creating the page if it does not exist.
 * Non-destructive: if a page already uses the slug, adopt it.
 */
function tpgb_get_or_create_page() {
	$stored = (int) get_option( 'tpgb_page_id' );
	if ( $stored && get_post( $stored ) && 'trash' !== get_post_status( $stored ) ) {
		return $stored;
	}
	$existing = get_page_by_path( tpgb_page_slug() );
	if ( $existing ) {
		update_option( 'tpgb_page_id', (int) $existing->ID );
		return (int) $existing->ID;
	}
	$id = wp_insert_post( array(
		'post_title'   => tpgb_page_title(),
		'post_name'    => tpgb_page_slug(),
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
		'comment_status' => 'closed',
	) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_tpgb_hub', 1 );
		update_option( 'tpgb_page_id', (int) $id );
		return (int) $id;
	}
	return 0;
}

/** Safety net: recreate the page if it was deleted while active. */
add_action( 'admin_init', function () {
	$id = (int) get_option( 'tpgb_page_id' );
	if ( ! $id || ! get_post( $id ) || 'trash' === get_post_status( $id ) ) {
		tpgb_get_or_create_page();
	}
} );

function tpgb_page_id()  { return (int) get_option( 'tpgb_page_id' ); }
function tpgb_page_url() {
	$id = tpgb_page_id();
	return $id ? get_permalink( $id ) : home_url( '/' . tpgb_page_slug() . '/' );
}
function tpgb_is_hub()   { $id = tpgb_page_id(); return $id && is_page( $id ); }

/* ---------------------------------------------------------------------------
 * Route the hub page through the plugin template.
 * ------------------------------------------------------------------------- */
add_filter( 'template_include', function ( $template ) {
	if ( tpgb_is_hub() ) {
		$custom = TPGB_DIR . 'templates/archive-blog.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
} );

/* Keep the query treating the hub as a normal singular page (pagination is query-string based). */

/* ---------------------------------------------------------------------------
 * Assets: only where needed, scoped so nothing leaks into the storefront.
 * ------------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( tpgb_is_hub() || is_singular( 'post' ) ) {
		wp_enqueue_style( 'wghs-blog', TPGB_URL . 'assets/wghs-blog.css', array(), TPGB_VERSION );
	}
} );

/* ---------------------------------------------------------------------------
 * Inject the menu link into the primary location (desktop + mobile share it).
 * Non-destructive: does not touch the saved menu.
 * ------------------------------------------------------------------------- */
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	$loc = isset( $args->theme_location ) ? $args->theme_location : '';
	if ( 'primary' !== $loc ) {
		return $items;
	}
	// Do not double up if a real Blog item already exists in the assigned menu.
	if ( false !== strpos( $items, esc_url( tpgb_page_url() ) ) ) {
		return $items;
	}
	$active = tpgb_is_hub() ? ' text-wgh-green' : '';
	$li  = '<li class="menu-item tpgb-nav-item">';
	$li .= '<a class="menu-link block py-2 lg:py-0' . $active . '" href="' . esc_url( tpgb_page_url() ) . '">' . esc_html( tpgb_menu_label() ) . '</a>';
	$li .= '</li>';
	return $items . $li;
}, 20, 2 );

/**
 * If the primary location has no assigned menu, the theme renders a hardcoded
 * fallback and the wp_nav_menu_items filter never fires, so the Blog link would
 * not appear. In that case only, create a menu mirroring the theme defaults plus
 * a Blog item and assign it to primary. Runs on activation. Non-destructive: it
 * never overwrites an already-assigned menu.
 */
function tpgb_ensure_primary_menu() {
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) && is_nav_menu( $locations['primary'] ) ) {
		return; // A menu is already assigned; the filter handles the link.
	}
	$menu_name = 'Primary Menu';
	$existing  = wp_get_nav_menu_object( $menu_name );
	$menu_id   = $existing ? (int) $existing->term_id : wp_create_nav_menu( $menu_name );
	if ( is_wp_error( $menu_id ) || ! $menu_id ) {
		return;
	}
	if ( ! wp_get_nav_menu_items( $menu_id ) ) {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
		$defaults = array(
			'Home'            => home_url( '/' ),
			'Shop'            => $shop,
			'Deals'           => home_url( '/deals' ),
			'How to Order'    => home_url( '/how-to-order' ),
			'About'           => home_url( '/about' ),
			'Contact'         => home_url( '/contact' ),
			tpgb_menu_label() => tpgb_page_url(),
		);
		foreach ( $defaults as $title => $url ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'  => $title,
				'menu-item-url'    => $url,
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			) );
		}
	}
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

/* ---------------------------------------------------------------------------
 * Helpers used by the template.
 * ------------------------------------------------------------------------- */

/** Estimated reading time in minutes for a post. */
function tpgb_reading_time( $post = null ) {
	$content = get_post_field( 'post_content', $post ? $post : get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	return max( 1, (int) ceil( $words / 200 ) );
}

/** The most meaningful term to badge a card with (a real category, else a tag). */
function tpgb_primary_term( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	foreach ( (array) get_the_category( $post_id ) as $c ) {
		if ( 'uncategorized' !== strtolower( $c->slug ) ) {
			return $c;
		}
	}
	$tags = get_the_tags( $post_id );
	return $tags ? $tags[0] : null;
}

/** A themed placeholder when a post has no featured image. */
function tpgb_thumb( $size = 'large', $class = 'tpgb-thumb-img' ) {
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( $size, array( 'class' => $class, 'loading' => 'lazy' ) );
		return;
	}
	if ( function_exists( 'wghs_placeholder_svg' ) ) {
		echo wghs_placeholder_svg( $class ); // phpcs:ignore
		return;
	}
	echo '<div class="' . esc_attr( $class ) . ' tpgb-thumb-fallback" aria-hidden="true"></div>';
}

/** Top tags for the filter pills. */
function tpgb_filter_terms( $limit = 8 ) {
	$tags = get_tags( array(
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $limit,
		'hide_empty' => true,
	) );
	return is_array( $tags ) ? $tags : array();
}
