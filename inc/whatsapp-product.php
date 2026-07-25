<?php
/**
 * WhatsApp product ordering, and the Open Graph tags that make the preview work.
 *
 * IMPORTANT, and the reason this file exists:
 * A wa.me deep link cannot attach an image. The ?text= parameter carries text
 * only. What WhatsApp does instead is unfurl the first URL in the message into
 * a preview card, and it builds that card from the Open Graph tags on the target
 * page. So the product photo shows up if and only if the product page emits a
 * valid og:image. Without that the message arrives as bare text.
 *
 * We therefore do two things here:
 *   1. Emit og:image, og:title, og:url and friends on product pages, but only
 *      when no SEO plugin is already doing it. Two sets of OG tags is worse
 *      than none, and websitesgh.com already has a schema duplication problem
 *      from exactly this kind of overlap.
 *   2. Build the order link with a message template the owner controls.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is another plugin already emitting Open Graph tags?
 *
 * @return bool
 */
function wghs_seo_plugin_active() {
	return (
		function_exists( 'the_seo_framework' )        // The SEO Framework
		|| class_exists( 'RankMath' )                 // Rank Math
		|| defined( 'WPSEO_VERSION' )                 // Yoast
		|| defined( 'SEOPRESS_VERSION' )              // SEOPress
		|| defined( 'AIOSEO_VERSION' )                // All in One SEO
	);
}

/**
 * Return the best share image URL for the current singular view: the product
 * (or post) featured image, falling back to the site logo. Large size, so the
 * WhatsApp and social preview cards render a proper photo.
 *
 * @return string Image URL or empty string.
 */
function wghs_share_image_url() {
	$image = '';
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( has_post_thumbnail( $id ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'large' );
			if ( $src ) { $image = $src[0]; }
		}
	}
	if ( ! $image ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src ) { $image = $src[0]; }
		}
	}
	return $image;
}

/**
 * Guarantee the product image is the OG image even when an SEO plugin owns the
 * Open Graph output. Without this, a product with no image set in the SEO
 * plugin can fall back to a generic site image, so the WhatsApp preview shows
 * the wrong picture or none. These filters force the featured image.
 *
 * The SEO Framework and others expose filters for exactly this; we hook the
 * common ones so whichever plugin is active gets the right image.
 */
add_filter( 'the_seo_framework_image_generation_params', function ( $params ) {
	$img = wghs_share_image_url();
	if ( $img && is_array( $params ) ) {
		// Force our image as the first candidate the framework considers.
		$params['cbs'] = array( 'wghs' => function () use ( $img ) { return $img; } );
	}
	return $params;
}, 10 );
// Rank Math and Yoast image filters, harmless if those plugins are absent.
add_filter( 'rank_math/opengraph/facebook/og_image', function ( $img ) {
	$our = wghs_share_image_url();
	return $our ? $our : $img;
} );
add_filter( 'wpseo_opengraph_image', function ( $img ) {
	$our = wghs_share_image_url();
	return $our ? $our : $img;
} );

/**
 * Open Graph and Twitter tags. Skipped entirely when an SEO plugin is present.
 */
function wghs_open_graph() {
	if ( wghs_seo_plugin_active() ) {
		return;
	}

	$title = get_bloginfo( 'name' );
	$desc  = get_bloginfo( 'description' );
	$url   = home_url( '/' );
	$image = '';

	if ( is_singular() ) {
		$id    = get_queried_object_id();
		$title = get_the_title( $id );
		$url   = get_permalink( $id );
		$excerpt = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_strip_all_tags( get_post_field( 'post_content', $id ) );
		$desc  = wp_trim_words( (string) $excerpt, 30 );

		if ( has_post_thumbnail( $id ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'large' );
			if ( $src ) {
				$image = $src[0];
			}
		}
	}

	// Fall back to the site logo so a preview card always renders.
	if ( ! $image ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src ) {
				$image = $src[0];
			}
		}
	}

	$type = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : ( is_singular( 'post' ) ? 'article' : 'website' );

	echo "\n<!-- Open Graph, emitted by the theme because no SEO plugin was detected -->\n";
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( get_locale() ) );

	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		printf( '<meta property="og:image:alt" content="%s">' . "\n", esc_attr( $title ) );
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	} else {
		echo '<meta name="twitter:card" content="summary">' . "\n";
	}

	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );

	// Price and availability help the preview on product pages.
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( $product->get_price() ) );
			printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
			printf( '<meta property="product:availability" content="%s">' . "\n", $product->is_in_stock() ? 'in stock' : 'out of stock' );
		}
	}
	echo "\n";
}
add_action( 'wp_head', 'wghs_open_graph', 5 );

/**
 * Build the WhatsApp order message for a product from the owner's template.
 *
 * Placeholders: {{product}} {{price}} {{url}} {{sku}} {{site}}
 *
 * @param WC_Product|null $product Product.
 * @return string
 */
function wghs_wa_product_message( $product = null ) {
	if ( ! $product && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( get_the_ID() );
	}
	if ( ! $product ) {
		return '';
	}

	$default = "*NEW ORDER*\nWebsitesGH Shop\n\n- - - - - - - - - - -\n\n*{{product}}*\nPrice: {{price}}\n{{url}}\n\n- - - - - - - - - - -\n\n*MY DETAILS*\n\nName: \nPhone: \nLocation: \n\nPay on delivery";
	$tpl     = (string) get_theme_mod( 'wghs_wa_product_template', $default );

	$price = html_entity_decode( wp_strip_all_tags( (string) $product->get_price_html() ), ENT_QUOTES, 'UTF-8' );

	$map = array(
		'{{product}}' => $product->get_name(),
		'{{price}}'   => $price,
		'{{url}}'     => get_permalink( $product->get_id() ),
		'{{sku}}'     => (string) $product->get_sku(),
		'{{site}}'    => get_bloginfo( 'name' ),
	);

	return str_replace( array_keys( $map ), array_values( $map ), $tpl );
}

/**
 * Full wa.me link for a product.
 *
 * @param WC_Product|null $product Product.
 * @return string Empty string when no number is configured.
 */
function wghs_wa_product_link( $product = null ) {
	if ( ! function_exists( 'wghs_wa_number' ) || ! wghs_wa_number() ) {
		return '';
	}
	$msg = wghs_wa_product_message( $product );
	return $msg ? wghs_wa_link( $msg ) : '';
}

/**
 * Order on WhatsApp button under the add to cart form on single products.
 */
function wghs_wa_product_button() {
	$link = wghs_wa_product_link();
	if ( ! $link ) {
		return;
	}
	?>
	<div class="wghs-wa-order">
		<a class="wghs-wa-order__btn" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"
			data-wghs-event="whatsapp_order" data-product-id="<?php echo esc_attr( wc_get_product( get_the_ID() ) ? get_the_ID() : 0 ); ?>">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.84-.2-.49-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.22.25-.85.83-.85 2.03s.87 2.35.99 2.51c.12.16 1.71 2.61 4.15 3.66.58.25 1.03.4 1.39.51.58.19 1.11.16 1.53.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.1-.22-.16-.47-.28Z"/></svg>
			<span><?php esc_html_e( 'Order on WhatsApp', 'wghshop' ); ?></span>
		</a>
		<p class="wghs-wa-order__note"><?php esc_html_e( 'Opens WhatsApp with this product ready to send. You still pay on delivery.', 'wghshop' ); ?></p>
	</div>
	<?php
}
// Button now rendered by wghs_two_order_buttons in express-order.php.

/**
 * Customizer control for the message template.
 */
add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_setting( 'wghs_wa_product_template', array(
		'default'           => "*NEW ORDER*\nWebsitesGH Shop\n\n- - - - - - - - - - -\n\n*{{product}}*\nPrice: {{price}}\n{{url}}\n\n- - - - - - - - - - -\n\n*MY DETAILS*\n\nName: \nPhone: \nLocation: \n\nPay on delivery",
		'sanitize_callback' => 'sanitize_textarea_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'wghs_wa_product_template', array(
		'label'       => __( 'WhatsApp order message', 'wghshop' ),
		'description' => __( 'Placeholders: {{product}} {{price}} {{url}} {{sku}} {{site}}. Keep {{url}} in the message, that is what makes WhatsApp show the product photo.', 'wghshop' ),
		'section'     => 'wghs_contact',
		'type'        => 'textarea',
	) );
}, 20 );
