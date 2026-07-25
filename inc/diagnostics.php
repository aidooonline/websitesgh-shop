<?php
/**
 * Setup diagnostics: a one-look admin page that reports whether the live site
 * has the pages, menus, blog posts, permalinks and OG image that the theme
 * expects, so issues like 404s, missing menus and missing WhatsApp images can
 * be pinpointed instead of guessed.
 *
 * @package WGHShop
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'themes.php',
		__( 'Shop Diagnostics', 'wghshop' ),
		__( 'Shop Diagnostics', 'wghshop' ),
		'manage_options',
		'wghs-diagnostics',
		'wghs_render_diagnostics'
	);
} );

function wghs_render_diagnostics() {
	echo '<div class="wrap"><h1>WebsitesGH Shop Diagnostics</h1>';
	echo '<p>Green is good. Red needs the fix shown next to it.</p><table class="widefat striped" style="max-width:900px"><tbody>';

	$row = function ( $label, $ok, $fix = '' ) {
		printf(
			'<tr><td style="width:40px;font-size:20px">%s</td><td><strong>%s</strong>%s</td></tr>',
			$ok ? '&#9989;' : '&#10060;',
			esc_html( $label ),
			$ok ? '' : ' <span style="color:#a00">&mdash; ' . esc_html( $fix ) . '</span>'
		);
	};

	// 1. Permalinks not plain
	$perma = get_option( 'permalink_structure' );
	$row( 'Permalinks are pretty (not plain)', ! empty( $perma ), 'Settings > Permalinks > choose Post name > Save. Plain permalinks cause 404s.' );

	// 2. Key pages exist
	$pages = array( 'guides', 'price-index', 'about', 'contact', 'how-to-order', 'faq', 'shop' );
	foreach ( $pages as $slug ) {
		$p = get_page_by_path( $slug );
		if ( 'shop' === $slug && function_exists( 'wc_get_page_id' ) ) {
			$p = get_post( wc_get_page_id( 'shop' ) );
		}
		$row( "Page exists: /{$slug}/", $p && 'publish' === get_post_status( $p ), 'Run Tools > WebsitesGH Shop Setup to create all pages.' );
	}

	// 3. Blog posts page set + has posts
	$pfp = (int) get_option( 'page_for_posts' );
	$row( 'Blog (Guides) page is set', $pfp > 0, 'Run Tools > WebsitesGH Shop Setup, or Settings > Reading > Posts page = Guides.' );
	// WordPress ignores page_for_posts unless show_on_front is 'page' with a
	// page_on_front. Without this the Guides page renders empty and no blog
	// listing ever appears, which looks like "the blog page does not exist".
	$sof = get_option( 'show_on_front' );
	$pof = (int) get_option( 'page_on_front' );
	$row(
		'Front page mode is "page" (required for the blog listing to render)',
		'page' === $sof && $pof > 0,
		'Run Tools > WebsitesGH Shop Setup, or Settings > Reading > set "A static page", Homepage = Home, Posts page = Guides. Without this the Guides page shows nothing.'
	);
	$posts = wp_count_posts()->publish;
	$row( "Published articles: {$posts}", $posts > 0, 'Run Tools > WebsitesGH Shop Setup to publish the seed articles.' );

	// 4. Menus assigned
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( array( 'primary', 'footer_shop', 'footer_help', 'footer_company' ) as $loc ) {
		$row( "Menu assigned: {$loc}", ! empty( $locs[ $loc ] ), 'Run Tools > WebsitesGH Shop Setup, or Appearance > Menus and assign locations.' );
	}

	// 5. WhatsApp number
	$num = function_exists( 'wghs_wa_number' ) ? wghs_wa_number() : '';
	$row( 'WhatsApp number resolves: ' . esc_html( $num ), ! empty( $num ), 'Customize > Shop settings > WhatsApp number.' );

	// 6. Cart/checkout classic
	$cart_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
	$cart_post = $cart_id ? get_post( $cart_id ) : null;
	$has_shortcode = $cart_post && ( has_shortcode( $cart_post->post_content, 'woocommerce_cart' ) || false !== strpos( $cart_post->post_content, 'woocommerce_cart' ) );
	$row( 'Cart page uses classic shortcode', (bool) $has_shortcode, 'Run Tools > WebsitesGH Shop Setup to convert the block cart to classic.' );

	// 7. SEO plugin + OG image note
	$seo = function_exists( 'wghs_seo_plugin_active' ) && wghs_seo_plugin_active();
	if ( $seo ) {
		echo '<tr><td style="font-size:20px">&#8505;&#65039;</td><td><strong>An SEO plugin is active (The SEO Framework or similar).</strong> <span style="color:#a60">The theme lets it own the og:image, and a filter feeds it the product photo. If WhatsApp shows no image: make sure each product has a Featured image, and in the SEO plugin enable Open Graph / social meta. Then re-scrape the URL in Facebook Sharing Debugger to clear WhatsApp cache.</span></td></tr>';
	} else {
		$row( 'Theme emits Open Graph image', true );
	}

	// 8. Product featured images
	if ( function_exists( 'wc_get_products' ) ) {
		global $wpdb;
		// Direct count, so this page stays fast as the catalogue grows instead
		// of instantiating every product object.
		$missing = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			   AND ( m.meta_id IS NULL OR m.meta_value = '' )"
		);
		$row( "Products missing a featured image: {$missing}", 0 === $missing, 'Set a Featured image on each product; that image is what WhatsApp previews.' );
	}

	echo '</tbody></table>';
	echo '<p style="margin-top:20px"><a href="' . esc_url( admin_url( 'tools.php?page=wghs-setup' ) ) . '" class="button button-primary">Go to Full Setup (Tools)</a> ';
	echo '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '" class="button">Flush permalinks</a></p>';
	echo '</div>';
}
