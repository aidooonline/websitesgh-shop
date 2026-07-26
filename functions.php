<?php
/**
 * WebsitesGH Shop theme functions.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WGHS_VERSION', '2.1.0' );
define( 'WGHS_DIR', get_template_directory() );
define( 'WGHS_URI', get_template_directory_uri() );

/**
 * Theme setup.
 */
function wghs_setup() {
	load_theme_textdomain( 'wghshop', WGHS_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// WooCommerce.
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 700,
		'single_image_width'    => 1200,
		'product_grid'          => array( 'default_columns' => 4 ),
	) );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary'        => __( 'Primary Menu', 'wghshop' ),
		'footer'         => __( 'Footer Menu', 'wghshop' ),
		'footer_shop'    => __( 'Footer: Shop', 'wghshop' ),
		'footer_help'    => __( 'Footer: Help', 'wghshop' ),
		'footer_company' => __( 'Footer: Company', 'wghshop' ),
	) );
}
add_action( 'after_setup_theme', 'wghs_setup' );

/**
 * Cache-busting version for a theme asset.
 *
 * Every asset was previously enqueued with the hardcoded WGHS_VERSION, which
 * has not changed in dozens of releases. Browsers, LiteSpeed and any CDN
 * therefore kept serving the first main.css they ever saw, so CSS and JS fixes
 * were invisible to anyone who had already visited the site. Using the file's
 * modification time means the version changes automatically whenever the file
 * actually changes, and never otherwise.
 *
 * @param string $rel Path relative to the theme root, e.g. /assets/css/main.css
 * @return string Version string.
 */
function wghs_asset_ver( $rel ) {
	$path = WGHS_DIR . $rel;
	$mtime = file_exists( $path ) ? filemtime( $path ) : 0;
	return $mtime ? (string) $mtime : WGHS_VERSION;
}

/**
 * Assets.
 */
function wghs_assets() {
	wp_enqueue_style( 'wghshop-main', WGHS_URI . '/assets/css/main.css', array(), wghs_asset_ver( '/assets/css/main.css' ) );
	// Keep theme header style.css present for WP validation.
	wp_enqueue_style( 'wghshop-style', get_stylesheet_uri(), array( 'wghshop-main' ), wghs_asset_ver( '/style.css' ) );

	wp_enqueue_script( 'wghshop-main', WGHS_URI . '/assets/js/main.js', array(), wghs_asset_ver( '/assets/js/main.js' ), true );
	$wghs_l10n = array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) );
	if ( function_exists( 'wghs_wa_cart_url' ) && function_exists( 'wghs_wa_number' ) && wghs_wa_number() ) {
		$wghs_l10n['waCartUrl']   = wghs_wa_cart_url();
		$wghs_l10n['waCartLabel'] = __( 'Checkout', 'wghshop' );
	}
	wp_localize_script( 'wghshop-main', 'TPG', $wghs_l10n );

	// Ad slot loader. Only on the blog, where the rail lives.
	if ( is_single() ) {
		wp_enqueue_script( 'wghshop-article', WGHS_URI . '/assets/js/article.js', array(), wghs_asset_ver( '/assets/js/article.js' ), true );
	}
	if ( is_home() || is_single() || is_archive() || is_search() ) {
		wp_enqueue_script( 'wghshop-adslots', WGHS_URI . '/assets/js/adslots.js', array(), wghs_asset_ver( '/assets/js/adslots.js' ), true );
		wp_localize_script( 'wghshop-adslots', 'WGHS_ADS', array( 'root' => esc_url_raw( rest_url() ) ) );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'wghs_assets' );

/**
 * Widget areas (footer).
 */
function wghs_widgets() {
	register_sidebar( array(
		'name'          => __( 'Footer About', 'wghshop' ),
		'id'            => 'footer-about',
		'before_widget' => '<div class="mb-6">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="text-sm font-display font-semibold text-wgh-ink mb-3">',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'wghs_widgets' );

/** Includes. */
require WGHS_DIR . '/inc/customizer.php';
if ( is_admin() ) {
	require WGHS_DIR . '/inc/setup.php';
}
require WGHS_DIR . '/inc/illustrations.php';
require WGHS_DIR . '/inc/template-tags.php';
if ( class_exists( 'WooCommerce' ) ) {
	require WGHS_DIR . '/inc/woocommerce.php';
	require WGHS_DIR . '/inc/whatsapp-gateway.php';
	require WGHS_DIR . '/inc/momo-gateway.php';
	require WGHS_DIR . '/inc/whatsapp-product.php';
	require WGHS_DIR . '/inc/conversion.php';
	require WGHS_DIR . '/inc/schema.php';
	require WGHS_DIR . '/inc/tracking.php';
	require WGHS_DIR . '/inc/attribution.php';
	// After attribution.php: it uses wghs_attr_table() and the updated_at column.
	require WGHS_DIR . '/inc/dashboard-export.php';
	require WGHS_DIR . '/inc/lead-capture.php';
	require WGHS_DIR . '/inc/express-order.php';
	require WGHS_DIR . '/inc/enquiries.php';
	require WGHS_DIR . '/inc/diagnostics.php';
}

/** Excerpt tweaks. */
add_filter( 'excerpt_more', function () { return '&hellip;'; } );
add_filter( 'excerpt_length', function () { return 24; } );

/** Body classes. */
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'wghs-theme';
	return $classes;
} );

/**
 * Deploy guard. cPanel git pulls can create files without world-read
 * permissions, which makes the webserver 404 theme assets while PHP
 * still reads them fine. Quietly repair the theme tree to 755/644,
 * at most once per hour, on admin requests only.
 */
add_action( 'admin_init', function () {
	$guard_key = 'wghs_perms_guard_' . get_stylesheet();
	if ( get_transient( $guard_key ) ) { return; }
	set_transient( $guard_key, 1, HOUR_IN_SECONDS );
	$dir = get_template_directory();
	@chmod( $dir, 0755 );
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $it as $item ) {
		$p = $item->getPathname();
		if ( false !== strpos( $p, DIRECTORY_SEPARATOR . '.git' ) ) { continue; }
		$want = $item->isDir() ? 0755 : 0644;
		if ( ( fileperms( $p ) & 0777 ) !== $want ) { @chmod( $p, $want ); }
	}
} );
