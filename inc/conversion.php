<?php
/**
 * Conversion engine.
 *
 * Every mechanism here exists to convert, and every claim it renders comes from
 * docs/research or the computed data assets. Nothing is decorative and nothing
 * is invented. The three persuasion levers, in order of strength for this
 * market:
 *
 *   1. Risk reversal. Pay on delivery is the single biggest objection killer
 *      for Ghanaian online buyers, so it is restated at every decision point.
 *   2. Original numbers. Running cost in cedis, real capacity, real wattage.
 *      Competitors cannot copy these without doing the research.
 *   3. Honesty as positioning. The claim check box tells the buyer the truth
 *      about the number on the box. Counterintuitive, converts, and builds the
 *      brand at the same time.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --------------------------------------------------------------------------
 * Data. PURC residential rate from 1 July 2026, and per class figures from
 * content-factory/data/running-costs.json. Class figures, not unit
 * measurements, and the copy says so.
 * ------------------------------------------------------------------------ */

const WGHS_KWH_RATE  = 2.04;   // GHS per kWh, PURC 0 to 300 kWh band, from 1 July 2026.
const WGHS_RATE_NOTE = 'PURC residential rate from 1 July 2026';

/**
 * Running cost class data: real continuous watts, typical minutes per day.
 * Keys are the illustration keys, so matching is shared with the artwork.
 *
 * @return array
 */
function wghs_running_classes() {
	return array(
		'blender' => array( 600, 10, 'a 2L blender' ),
		'kettle'  => array( 2000, 8, 'an electric kettle' ),
		'cooker'  => array( 1000, 40, 'a single burner hot plate' ),
		'iron'    => array( 1200, 20, 'a 1200W dry iron' ),
		'light'   => array( 10, 240, 'a rechargeable LED lamp' ),
		'grooming'=> array( 2000, 10, 'a 2000W hair dryer' ),
	);
}

/**
 * Monthly running cost in GHS for a wattage and daily minutes.
 *
 * @param int $watts Real continuous draw.
 * @param int $mins  Minutes per day.
 * @return float
 */
function wghs_monthly_cost( $watts, $mins ) {
	return round( $watts / 1000 * ( $mins / 60 ) * 30 * WGHS_KWH_RATE, 2 );
}

/* --------------------------------------------------------------------------
 * 1. Quick Answer capsule. First thing on the product summary. This is the
 *    block that answers the buyer in five seconds and gets lifted into AI
 *    answers verbatim.
 * ------------------------------------------------------------------------ */
function wghs_quick_answer() {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }

	$price = wp_strip_all_tags( wc_price( (float) $product->get_price() ) );
	printf(
		'<div class="wghs-qa"><p><strong>%1$s</strong> %2$s %3$s. %4$s</p></div>',
		esc_html( $product->get_name() ),
		esc_html__( 'sells for', 'wghshop' ),
		esc_html( html_entity_decode( $price, ENT_QUOTES, 'UTF-8' ) ),
		esc_html__( 'Pay on delivery anywhere in Accra: the rider brings it, you check it, then you pay. Same day dispatch on orders confirmed before 4pm.', 'wghshop' )
	);
}
add_action( 'woocommerce_single_product_summary', 'wghs_quick_answer', 8 );

/* --------------------------------------------------------------------------
 * 2. Claim check. The brand in one box. Only renders where the research
 *    backs it: blenders and power banks so far.
 * ------------------------------------------------------------------------ */
function wghs_claim_check() {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	$key = function_exists( 'wghs_art_key' ) ? wghs_art_key( $product->get_name() ) : '';

	$claims = array(
		'blender' => array(
			__( 'About that wattage on the box', 'wghshop' ),
			__( 'Most blenders in Ghana are advertised as 4500W or 8000W. Ghanaian sockets deliver at most 2,990W (230V x 13A), so those numbers are peak marketing figures, not what the motor draws. Real continuous draw on this class of blender is around 600W. We tell you because you deserve the real number, and it still blends your pepper just fine.', 'wghshop' ),
		),
		'power' => array(
			__( 'What the mAh number really means', 'wghshop' ),
			__( 'A power bank never delivers its advertised mAh to your phone. The cells run at 3.7V, USB output is 5V, and the conversion loses energy as heat. Expect roughly 52 to 63 per cent of the number on the box as real delivered charge. Every power bank on the market works this way. We are just the shop that says so.', 'wghshop' ),
		),
	);

	if ( empty( $claims[ $key ] ) ) { return; }
	printf(
		'<aside class="wghs-claim"><p class="wghs-claim__label">%s</p><p class="wghs-claim__title">%s</p><p class="wghs-claim__body">%s</p></aside>',
		esc_html__( 'We checked the numbers', 'wghshop' ),
		esc_html( $claims[ $key ][0] ),
		esc_html( $claims[ $key ][1] )
	);
}
add_action( 'woocommerce_single_product_summary', 'wghs_claim_check', 25 );

/* --------------------------------------------------------------------------
 * 3. Running cost box. Original data, per category class.
 * ------------------------------------------------------------------------ */
function wghs_running_cost_box() {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	$key     = function_exists( 'wghs_art_key' ) ? wghs_art_key( $product->get_name() ) : '';
	$classes = wghs_running_classes();
	if ( empty( $classes[ $key ] ) ) { return; }

	list( $watts, $mins, $label ) = $classes[ $key ];
	$cost = wghs_monthly_cost( $watts, $mins );

	printf(
		'<aside class="wghs-runcost">
			<p class="wghs-runcost__label">%1$s</p>
			<p class="wghs-runcost__figure">GHS %2$s<span>/%3$s</span></p>
			<p class="wghs-runcost__note">%4$s</p>
		</aside>',
		esc_html__( 'What it costs to run', 'wghshop' ),
		esc_html( number_format( $cost, 2 ) ),
		esc_html__( 'month', 'wghshop' ),
		esc_html( sprintf(
			/* translators: 1: appliance label, 2: watts, 3: minutes, 4: tariff note. */
			__( 'Typical for %1$s: about %2$dW real draw, %3$d minutes a day, at the %4$s. Class estimate, not a lab measurement of this exact unit.', 'wghshop' ),
			$label, $watts, $mins, WGHS_RATE_NOTE
		) )
	);
}
add_action( 'woocommerce_single_product_summary', 'wghs_running_cost_box', 26 );

/* --------------------------------------------------------------------------
 * 4. Trust strip. Product pages under the summary, and the cart.
 * ------------------------------------------------------------------------ */
function wghs_trust_strip() {
	$items = array(
		array( 'M20 7L9 18l-5-5', __( 'Pay on delivery', 'wghshop' ) ),
		array( 'M13 2L3 14h7l-1 8 10-12h-7z', __( 'Same day in Accra', 'wghshop' ) ),
		array( 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 14l-4-4 1.4-1.4L11 13.2l5.6-5.6L18 9z', __( 'Check it before you pay', 'wghshop' ) ),
		array( 'M4 4h16v12H8l-4 4z', __( 'Order on WhatsApp', 'wghshop' ) ),
	);
	echo '<ul class="wghs-truststrip">';
	foreach ( $items as $it ) {
		printf(
			'<li><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>%s</li>',
			esc_attr( $it[0] ),
			esc_html( $it[1] )
		);
	}
	echo '</ul>';
}
add_action( 'woocommerce_single_product_summary', 'wghs_trust_strip', 35 );
add_action( 'woocommerce_before_cart_collaterals', 'wghs_trust_strip' );

/* --------------------------------------------------------------------------
 * 5. Sticky mobile order bar. Price left, order right, always visible.
 *    Mobile is the entire market here.
 * ------------------------------------------------------------------------ */
function wghs_sticky_order_bar() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	global $post;
	$product = wc_get_product( $post ? $post->ID : 0 );
	if ( ! $product || ! $product->is_in_stock() ) { return; }
	?>
	<div class="wghs-orderbar lg:hidden" role="region" aria-label="<?php esc_attr_e( 'Order', 'wghshop' ); ?>">
		<div class="wghs-orderbar__price">
			<span><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			<small><?php esc_html_e( 'Pay on delivery', 'wghshop' ); ?></small>
		</div>
		<a class="wghs-orderbar__btn" href="<?php echo esc_url( '?add-to-cart=' . $product->get_id() ); ?>" data-wghs-event="orderbar_add">
			<?php esc_html_e( 'Order now', 'wghshop' ); ?>
		</a>
	</div>
	<?php
}
add_action( 'wp_footer', 'wghs_sticky_order_bar' );

/* --------------------------------------------------------------------------
 * 6. Per category question block. The literal questions buyers type, each
 *    answer self contained. Mirrors into FAQ schema in Sprint 4.
 * ------------------------------------------------------------------------ */
function wghs_category_faq() {
	global $product;
	if ( ! $product instanceof WC_Product ) { return; }
	$key = function_exists( 'wghs_art_key' ) ? wghs_art_key( $product->get_name() ) : '';

	$faqs = array(
		'blender' => array(
			array( __( 'Can it grind dry pepper and grains, or only wet?', 'wghshop' ), __( 'The 2L commercial jar handles wet blending. For dry grinding of grains and spices, choose the model with the separate dry mill cup, which is built for it.', 'wghshop' ) ),
			array( __( 'Why did my blender stop mid-blend?', 'wghshop' ), __( 'That is the overheat protection, not a fault. Heavy loads trip it. Unplug, let it cool for 15 to 20 minutes, and it resets itself.', 'wghshop' ) ),
			array( __( 'Why is the same blender different prices everywhere?', 'wghshop' ), __( 'Waiting time and stock location. The cheapest listings ship from abroad and take weeks. Local stock costs more and arrives today. There are also different motor variants sold under the same brand name. Our price is for stock in Accra, dispatched same day.', 'wghshop' ) ),
		),
		'power' => array(
			array( __( 'How many times will it charge my phone?', 'wghshop' ), __( 'Divide the real delivered capacity, roughly 52 to 63 per cent of the advertised mAh, by your phone battery. A 20,000mAh bank gives a 5,000mAh phone about two full charges, sometimes a bit more with a quality unit.', 'wghshop' ) ),
			array( __( 'Can I take it on a flight?', 'wghshop' ), __( 'Up to 20,000mAh is fine in hand luggage. A 30,000mAh unit stores about 111Wh, which is over the usual 100Wh airline limit, so check with your airline first.', 'wghshop' ) ),
		),
		'kettle' => array(
			array( __( 'How much power does a kettle use?', 'wghshop' ), __( 'A 2000W kettle used about 8 minutes a day costs roughly GHS 16 a month at the current PURC rate. Boiling only what you need cuts that fast.', 'wghshop' ) ),
		),
		'bag' => array(
			array( __( 'Which size for which class?', 'wghshop' ), __( 'Lower primary takes the small single bags. Upper primary and JHS take the full size sets. If the child carries a laptop, choose the anti theft laptop backpack instead.', 'wghshop' ) ),
		),
	);

	if ( empty( $faqs[ $key ] ) ) { return; }
	echo '<section class="wghs-pfaq"><h2>' . esc_html__( 'Questions people ask', 'wghshop' ) . '</h2>';
	foreach ( $faqs[ $key ] as $qa ) {
		printf(
			'<details class="wghs-pfaq__item"><summary>%s</summary><p>%s</p></details>',
			esc_html( $qa[0] ),
			esc_html( $qa[1] )
		);
	}
	echo '</section>';
}
add_action( 'woocommerce_after_single_product_summary', 'wghs_category_faq', 12 );
