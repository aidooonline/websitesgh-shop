<?php
/**
 * Structured data (JSON-LD).
 *
 * Ownership rule, decided once so the websitesgh.com duplication problem is
 * never repeated here: an SEO plugin (The SEO Framework) owns WebSite,
 * Organization and Article. THIS FILE owns Product, Offer, FAQPage and
 * BreadcrumbList, which no general SEO plugin emits correctly for this shop.
 * One emitter per type, no exceptions. WooCommerce core's own Product schema
 * is disabled below so there are never two Product blocks.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* WooCommerce emits its own minimal Product schema. Ours is richer, so
 * switch the core one off to keep exactly one Product block per page. */
add_action( 'init', function () {
	if ( function_exists( 'WC' ) ) {
		remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
		remove_action( 'woocommerce_email_order_details', array( WC()->structured_data, 'output_email_structured_data' ), 30 );
	}
}, 20 );

/**
 * Print one JSON-LD block.
 *
 * @param array $data Schema graph.
 */
function wghs_jsonld( $data ) {
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}

/**
 * Product + Offer with priceValidUntil, plus FAQPage mirroring the visible
 * question block, plus BreadcrumbList. Emitted on single product pages.
 */
function wghs_product_schema() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) { return; }

	$image = '';
	if ( has_post_thumbnail( $product->get_id() ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'large' );
		if ( $src ) { $image = $src[0]; }
	}

	// Price is valid until the end of the current month: matches the visible
	// "verified [month]" stamp and forces the freshness discipline we want.
	$valid_until = gmdate( 'Y-m-t' );

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'@id'         => get_permalink( $product->get_id() ) . '#product',
		'name'        => $product->get_name(),
		'sku'         => (string) $product->get_sku(),
		'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
		'url'         => get_permalink( $product->get_id() ),
		'offers'      => array(
			'@type'           => 'Offer',
			'price'           => (string) $product->get_price(),
			'priceCurrency'   => get_woocommerce_currency(),
			'priceValidUntil' => $valid_until,
			'availability'    => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			'itemCondition'   => 'https://schema.org/NewCondition',
			'url'             => get_permalink( $product->get_id() ),
			'seller'          => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'shippingDetails' => array(
				'@type'               => 'OfferShippingDetails',
				'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => 'GH' ),
				'deliveryTime'        => array(
					'@type'        => 'ShippingDeliveryTime',
					'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 4, 'unitCode' => 'DAY' ),
				),
			),
		),
	);
	if ( $image ) { $schema['image'] = $image; }

	$cats = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	if ( $cats ) { $schema['category'] = $cats[0]; }

	wghs_jsonld( $schema );

	// FAQPage: mirror the exact visible question block, single source of truth.
	if ( function_exists( 'wghs_art_key' ) ) {
		$faq = wghs_schema_faq_for( wghs_art_key( $product->get_name() ) );
		if ( $faq ) { wghs_jsonld( $faq ); }
	}

	// BreadcrumbList.
	$crumbs = array(
		array( get_bloginfo( 'name' ), home_url( '/' ) ),
	);
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$crumbs[] = array( __( 'Shop', 'wghshop' ), wc_get_page_permalink( 'shop' ) );
	}
	if ( $cats ) {
		$term = get_term_by( 'name', $cats[0], 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$crumbs[] = array( $term->name, get_term_link( $term ) );
		}
	}
	$crumbs[] = array( $product->get_name(), get_permalink( $product->get_id() ) );

	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c[0],
			'item'     => $c[1],
		);
	}
	wghs_jsonld( array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ) );
}
add_action( 'wp_head', 'wghs_product_schema', 8 );

/**
 * FAQ schema data per category key. Must stay in step with the visible
 * question block in inc/conversion.php: same questions, same answers.
 *
 * @param string $key Category key.
 * @return array|null
 */
function wghs_schema_faq_for( $key ) {
	$sets = array(
		'blender' => array(
			array( 'Can it grind dry pepper and grains, or only wet?', 'The 2L commercial jar handles wet blending. For dry grinding of grains and spices, choose the model with the separate dry mill cup, which is built for it.' ),
			array( 'Why did my blender stop mid-blend?', 'That is the overheat protection, not a fault. Heavy loads trip it. Unplug, let it cool for 15 to 20 minutes, and it resets itself.' ),
			array( 'Why is the same blender different prices everywhere in Ghana?', 'Waiting time and stock location. The cheapest listings ship from abroad and take weeks. Local stock costs more and arrives today. There are also different motor variants sold under the same brand name.' ),
		),
		'power' => array(
			array( 'How many times will a power bank charge my phone?', 'Divide the real delivered capacity, roughly 52 to 63 per cent of the advertised mAh, by your phone battery capacity. A 20,000mAh bank gives a 5,000mAh phone about two full charges.' ),
			array( 'Can I take a power bank on a flight?', 'Up to 20,000mAh is fine in hand luggage. A 30,000mAh unit stores about 111Wh, which is over the usual 100Wh airline limit, so check with your airline first.' ),
		),
		'kettle' => array(
			array( 'How much power does an electric kettle use in Ghana?', 'A 2000W kettle used about 8 minutes a day costs roughly GHS 16 a month at the PURC residential rate effective 1 July 2026.' ),
		),
		'bag' => array(
			array( 'Which school bag size for which class?', 'Lower primary takes the small single bags. Upper primary and JHS take the full size sets. If the child carries a laptop, choose an anti theft laptop backpack instead.' ),
		),
	);
	if ( empty( $sets[ $key ] ) ) { return null; }

	$main = array();
	foreach ( $sets[ $key ] as $qa ) {
		$main[] = array(
			'@type'          => 'Question',
			'name'           => $qa[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
		);
	}
	return array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main );
}

/* --------------------------------------------------------------------------
 * llms.txt, served at the domain root via rewrite. A theme cannot write
 * files to the docroot on shared hosting, so serve it from WordPress.
 * Visit Settings > Permalinks once after activation to flush rules.
 * ------------------------------------------------------------------------ */
add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?wghs_llms=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'wghs_llms';
	return $vars;
} );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'wghs_llms' ) ) { return; }
	header( 'Content-Type: text/plain; charset=utf-8' );
	$home = home_url( '/' );
	$name = get_bloginfo( 'name' );
	echo "# {$name}\n\n";
	echo "> Appliances and electronics shop for Ghana. Pay on delivery in Accra. ";
	echo "We verify manufacturer claims before selling: real wattages, real power bank capacity, running costs in cedis at current PURC electricity tariffs.\n\n";
	echo "## Primary data pages\n";
	echo "- {$home}price-index/ : every product with its current GHS price, verified monthly\n";
	echo "- {$home}running-costs/ : appliance running costs in GHS per month at PURC rates, working shown\n\n";
	echo "## Buying\n";
	echo "- {$home}shop/ : full catalogue\n";
	echo "- {$home}how-to-order/ : pay on delivery, how it works\n";
	echo "- {$home}delivery-and-payment/ : delivery times and payment options\n";
	echo "- {$home}returns/ : returns policy\n\n";
	echo "## About\n";
	echo "- {$home}about/ : why we check the numbers, with the arithmetic\n";
	exit;
} );
