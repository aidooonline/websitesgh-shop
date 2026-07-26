<?php
/**
 * About page.
 *
 * Picked up automatically for the page with slug "about".
 *
 * The brand is "we check the numbers", so the page is built out of numbers and
 * diagrams rather than paragraphs. The two claims that define the shop, the
 * socket ceiling and the power bank conversion loss, are drawn as inline SVG so
 * a reader understands them in three seconds without reading a sentence. No
 * photography is required, which matters because the product photos are being
 * shot by hand.
 *
 * @package WGHShop
 */

defined( 'ABSPATH' ) || exit;

get_header();

$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$wa   = function_exists( 'wghs_wa_link' ) ? wghs_wa_link( 'Hello, I have a question about a product.' ) : '';
?>

<main id="main" class="wghs-ab">

	<!-- HERO: split, statement left, live diagram right -->
	<section class="wghs-ab__hero">
		<div class="wrap wghs-ab__herogrid">
			<div>
				<p class="wghs-ab__kicker"><?php esc_html_e( 'About the shop', 'wghshop' ); ?></p>
				<h1 class="wghs-ab__h1">
					<?php esc_html_e( 'We check', 'wghshop' ); ?><br>
					<?php esc_html_e( 'the', 'wghshop' ); ?> <span class="wghs-ab__hl"><?php esc_html_e( 'numbers', 'wghshop' ); ?></span>
				</h1>
				<p class="wghs-ab__sub">
					<?php esc_html_e( 'Every shop copies the specification off the box. Nobody checks whether it is true. We check, we publish the working, and we let you check it yourself before you pay a cedi.', 'wghshop' ); ?>
				</p>
				<div class="wghs-ab__btns">
					<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Shop the range', 'wghshop' ); ?></a>
					<a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Read the working', 'wghshop' ); ?></a>
				</div>
			</div>

			<!-- The socket ceiling, drawn -->
			<figure class="wghs-ab__fig">
				<svg viewBox="0 0 420 300" role="img" aria-label="A Ghanaian socket delivers at most 2,990 watts, so an 8000 watt claim is impossible">
					<defs>
						<linearGradient id="gWall" x1="0" y1="0" x2="0" y2="1">
							<stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#F5F2E8"/>
						</linearGradient>
					</defs>
					<rect x="0" y="0" width="420" height="300" rx="14" fill="url(#gWall)"/>
					<!-- claimed bar -->
					<text x="26" y="52" class="ab-lab"><?php esc_html_e( 'CLAIMED ON THE BOX', 'wghshop' ); ?></text>
					<rect x="26" y="62" width="368" height="40" rx="8" fill="#E2A013" opacity=".25"/>
					<rect x="26" y="62" width="368" height="40" rx="8" fill="none" stroke="#E2A013" stroke-width="2" stroke-dasharray="5 4"/>
					<text x="42" y="89" class="ab-big" fill="#8a6a12">8000W</text>
					<!-- ceiling line -->
					<line x1="164" y1="52" x2="164" y2="248" stroke="#C0392B" stroke-width="2" stroke-dasharray="4 4"/>
					<text x="172" y="130" class="ab-note" fill="#C0392B"><?php esc_html_e( 'socket ceiling', 'wghshop' ); ?></text>
					<!-- possible bar -->
					<text x="26" y="168" class="ab-lab"><?php esc_html_e( 'WHAT A 13A SOCKET CAN DELIVER', 'wghshop' ); ?></text>
					<rect x="26" y="178" width="138" height="40" rx="8" fill="#0E8C5A"/>
					<text x="42" y="205" class="ab-big" fill="#ffffff">2,990W</text>
					<!-- real draw -->
					<text x="26" y="246" class="ab-lab"><?php esc_html_e( 'WHAT THE MOTOR ACTUALLY DRAWS', 'wghshop' ); ?></text>
					<rect x="26" y="256" width="28" height="26" rx="6" fill="#20211C"/>
					<text x="64" y="275" class="ab-note" fill="#20211C">~500W</text>
				</svg>
				<figcaption><?php esc_html_e( '230 volts times 13 amps. The arithmetic anyone can check.', 'wghshop' ); ?></figcaption>
			</figure>
		</div>
	</section>

	<!-- NUMBER BAND -->
	<section class="wghs-ab__band">
		<div class="wrap wghs-ab__stats">
			<div class="wghs-ab__stat">
				<span class="wghs-ab__n">2,990<i>W</i></span>
				<span class="wghs-ab__sl"><?php esc_html_e( 'Ceiling of any Ghanaian wall socket. Every bigger number is marketing.', 'wghshop' ); ?></span>
			</div>
			<div class="wghs-ab__stat">
				<span class="wghs-ab__n">60<i>%</i></span>
				<span class="wghs-ab__sl"><?php esc_html_e( 'How much of a power bank actually reaches your phone. The rest is heat.', 'wghshop' ); ?></span>
			</div>
			<div class="wghs-ab__stat">
				<span class="wghs-ab__n">0<i>&#8373;</i></span>
				<span class="wghs-ab__sl"><?php esc_html_e( 'What you pay before the item is in your hands and you have checked it.', 'wghshop' ); ?></span>
			</div>
			<div class="wghs-ab__stat">
				<span class="wghs-ab__n">20<i>+</i></span>
				<span class="wghs-ab__sl"><?php esc_html_e( 'Guides published with the arithmetic shown, so you can repeat it.', 'wghshop' ); ?></span>
			</div>
		</div>
	</section>

	<!-- THE POWER BANK DIAGRAM -->
	<section class="wrap wghs-ab__sec">
		<div class="wghs-ab__split">
			<figure class="wghs-ab__fig wghs-ab__fig--flat">
				<svg viewBox="0 0 420 240" role="img" aria-label="A 30,000mAh power bank delivers roughly 15,500mAh to a phone">
					<rect x="0" y="0" width="420" height="240" rx="14" fill="#F5F2E8"/>
					<!-- full cell -->
					<rect x="26" y="60" width="150" height="110" rx="10" fill="#0E8C5A"/>
					<text x="101" y="112" text-anchor="middle" class="ab-big" fill="#fff">30,000</text>
					<text x="101" y="136" text-anchor="middle" class="ab-note" fill="#cfeadd">mAh on the case</text>
					<!-- arrow -->
					<path d="M188 115 H236" stroke="#20211C" stroke-width="3" fill="none"/>
					<path d="M232 108 L244 115 L232 122 Z" fill="#20211C"/>
					<text x="212" y="98" text-anchor="middle" class="ab-note" fill="#8a857b">3.7V &#8594; 5V</text>
					<text x="212" y="146" text-anchor="middle" class="ab-note" fill="#C0392B">lost as heat</text>
					<!-- delivered -->
					<rect x="256" y="82" width="138" height="66" rx="10" fill="#20211C"/>
					<text x="325" y="115" text-anchor="middle" class="ab-big" fill="#fff">15,500</text>
					<text x="325" y="136" text-anchor="middle" class="ab-note" fill="#b9b6ae">reaches the phone</text>
				</svg>
			</figure>
			<div>
				<h2 class="wghs-ab__h2"><?php esc_html_e( 'Two claims, both checkable', 'wghshop' ); ?></h2>
				<p class="wghs-ab__p"><?php esc_html_e( 'A power bank advertised at 30,000mAh does not put 30,000mAh into your phone. The cells run at 3.7 volts, your phone charges at 5, and converting between them loses energy as heat every single time.', 'wghshop' ); ?></p>
				<p class="wghs-ab__p"><?php esc_html_e( 'Real delivered capacity is roughly 15,500 to 18,900mAh. That is three to four full charges on a typical phone, not six. We put the honest charge count on the product, and we would rather lose the sale than print a number we cannot defend.', 'wghshop' ); ?></p>
				<div class="wghs-ab__links">
					<a href="<?php echo esc_url( home_url( '/blender-wattage-ghana/' ) ); ?>"><?php esc_html_e( 'The blender working', 'wghshop' ); ?> &rarr;</a>
					<a href="<?php echo esc_url( home_url( '/power-bank-real-capacity/' ) ); ?>"><?php esc_html_e( 'The power bank working', 'wghshop' ); ?> &rarr;</a>
				</div>
			</div>
		</div>
	</section>

	<!-- DARK: why -->
	<section class="wghs-ab__dark">
		<div class="wrap">
			<h2 class="wghs-ab__h2 wghs-ab__h2--light"><?php esc_html_e( 'Why a wrong number matters', 'wghshop' ); ?></h2>
			<div class="wghs-ab__why">
				<div class="wghs-ab__whycard">
					<span class="wghs-ab__whyn">01</span>
					<h3><?php esc_html_e( 'You pay twice', 'wghshop' ); ?></h3>
					<p><?php esc_html_e( 'Once for a specification you never receive, then again when the thing cannot do the job and you replace it early.', 'wghshop' ); ?></p>
				</div>
				<div class="wghs-ab__whycard">
					<span class="wghs-ab__whyn">02</span>
					<h3><?php esc_html_e( 'You buy the wrong thing', 'wghshop' ); ?></h3>
					<p><?php esc_html_e( 'Chasing the biggest number on the box pushes people toward machines built for a different job. A smoothie blender is poor at pepper.', 'wghshop' ); ?></p>
				</div>
				<div class="wghs-ab__whycard">
					<span class="wghs-ab__whyn">03</span>
					<h3><?php esc_html_e( 'Everybody pays more', 'wghshop' ); ?></h3>
					<p><?php esc_html_e( 'In a market where nobody trusts anybody, every price carries a risk premium. Honest specifications take that premium out.', 'wghshop' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ORDER FLOW as a visual track -->
	<section class="wrap wghs-ab__sec">
		<h2 class="wghs-ab__h2"><?php esc_html_e( 'You never pay before you see it', 'wghshop' ); ?></h2>
		<p class="wghs-ab__lede"><?php esc_html_e( 'The other half of trust is not asking for money first.', 'wghshop' ); ?></p>
		<ol class="wghs-ab__track">
			<li>
				<span class="wghs-ab__dot">1</span>
				<h4><?php esc_html_e( 'Order', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'Add to the cart and send the whole basket on WhatsApp. No account, no card.', 'wghshop' ); ?></p>
			</li>
			<li>
				<span class="wghs-ab__dot">2</span>
				<h4><?php esc_html_e( 'We confirm', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'We check the item, the price and your area, and tell you honestly when it reaches you.', 'wghshop' ); ?></p>
			</li>
			<li>
				<span class="wghs-ab__dot">3</span>
				<h4><?php esc_html_e( 'Check it', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'Open the box at your door. Plug it in. Read it against what we told you.', 'wghshop' ); ?></p>
			</li>
			<li>
				<span class="wghs-ab__dot wghs-ab__dot--gold">4</span>
				<h4><?php esc_html_e( 'Then pay', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'Only then, and only the rider. If it is not what we said, you pay nothing.', 'wghshop' ); ?></p>
			</li>
		</ol>
	</section>

	<!-- CLOSE -->
	<section class="wrap">
		<div class="wghs-ab__close">
			<div>
				<h3><?php esc_html_e( 'Ask us the spec you are unsure about', 'wghshop' ); ?></h3>
				<p><?php esc_html_e( 'We will give you the real number, even when it costs us the sale.', 'wghshop' ); ?></p>
			</div>
			<div class="wghs-ab__closebtns">
				<?php if ( $wa ) : ?>
					<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener" data-wghs-event="about_whatsapp"><?php esc_html_e( 'Ask on WhatsApp', 'wghshop' ); ?></a>
				<?php endif; ?>
				<a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Use the form', 'wghshop' ); ?></a>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
