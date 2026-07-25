<?php
/**
 * About page.
 *
 * Picked up automatically by WordPress for the page with slug "about".
 *
 * The page content in pages.json was rendering as a wall of prose, which read
 * as plain and dated for the page whose whole job is to earn trust. This
 * template carries the same argument, but as designed evidence: the claim, the
 * numbers that prove it, the two worked examples with the figure as the hero,
 * and the ordering steps that remove the fear of paying first.
 *
 * @package WGHShop
 */

defined( 'ABSPATH' ) || exit;

get_header();

$wa = function_exists( 'wghs_wa_link' ) ? wghs_wa_link( "Hello, I have a question about a product." ) : '';
?>

<main id="main" class="wghs-about">

	<!-- Hero -->
	<section class="wghs-about__hero">
		<div class="wrap">
			<p class="wghs-about__kicker"><?php esc_html_e( 'About WebsitesGH Shop', 'wghshop' ); ?></p>
			<h1 class="wghs-about__title">
				<?php esc_html_e( 'We check the numbers', 'wghshop' ); ?><span class="wghs-about__dot">.</span>
			</h1>
			<p class="wghs-about__standfirst">
				<?php esc_html_e( 'Most shops in Ghana copy the specification off the box and put it online. Nobody checks whether it is true. We do, and we publish the working so you can check it yourself.', 'wghshop' ); ?>
			</p>
			<div class="wghs-about__cta">
				<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>">
					<?php esc_html_e( 'Shop the range', 'wghshop' ); ?>
				</a>
				<a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_url( home_url( '/guides/' ) ); ?>">
					<?php esc_html_e( 'Read the guides', 'wghshop' ); ?>
				</a>
			</div>
		</div>
	</section>

	<!-- Proof strip -->
	<section class="wrap">
		<div class="wghs-about__proof">
			<div class="wghs-proofcard">
				<div class="wghs-proofcard__n">2,990<span>W</span></div>
				<div class="wghs-proofcard__l"><?php esc_html_e( 'The most any Ghanaian wall socket can deliver. Every bigger number on a box is marketing.', 'wghshop' ); ?></div>
			</div>
			<div class="wghs-proofcard">
				<div class="wghs-proofcard__n">~500<span>W</span></div>
				<div class="wghs-proofcard__l"><?php esc_html_e( 'What a blender advertised at 8000W actually draws while grinding your pepper.', 'wghshop' ); ?></div>
			</div>
			<div class="wghs-proofcard">
				<div class="wghs-proofcard__n">15,500<span>mAh</span></div>
				<div class="wghs-proofcard__l"><?php esc_html_e( 'What a "30,000mAh" power bank really delivers to your phone after conversion losses.', 'wghshop' ); ?></div>
			</div>
		</div>
	</section>

	<!-- Worked examples -->
	<section class="wrap wghs-about__section">
		<h2 class="wghs-about__h2"><?php esc_html_e( 'Two examples, with the working', 'wghshop' ); ?></h2>
		<p class="wghs-about__lede"><?php esc_html_e( 'Not opinions. Arithmetic you can repeat yourself.', 'wghshop' ); ?></p>

		<div class="wghs-about__examples">
			<article class="wghs-excard">
				<div class="wghs-excard__tag"><?php esc_html_e( 'Blenders', 'wghshop' ); ?></div>
				<h3 class="wghs-excard__h"><?php esc_html_e( 'The blender that claims 8000W', 'wghshop' ); ?></h3>
				<p><?php esc_html_e( 'Ghanaian mains is 230 volts. A standard Type G socket is rated 13 amps. Power is volts times amps, so the most any socket in your house can deliver is 2,990W.', 'wghshop' ); ?></p>
				<div class="wghs-excard__math">
					<span>230V</span><em>&times;</em><span>13A</span><em>=</em><strong>2,990W</strong>
				</div>
				<p><?php esc_html_e( 'An 8000W load would need 34.8 amps. The fuse in the plug blows long before the motor ever sees that power. The number on the box is a peak marketing figure, not what the motor draws.', 'wghshop' ); ?></p>
				<a class="wghs-excard__link" href="<?php echo esc_url( home_url( '/blender-wattage-ghana/' ) ); ?>"><?php esc_html_e( 'Read the full working', 'wghshop' ); ?> &rarr;</a>
			</article>

			<article class="wghs-excard">
				<div class="wghs-excard__tag"><?php esc_html_e( 'Power banks', 'wghshop' ); ?></div>
				<h3 class="wghs-excard__h"><?php esc_html_e( 'The power bank that claims 30,000mAh', 'wghshop' ); ?></h3>
				<p><?php esc_html_e( 'The cells inside run at 3.7 volts. Your phone charges at 5 volts. Converting between them loses energy as heat, every time.', 'wghshop' ); ?></p>
				<div class="wghs-excard__math">
					<span>3.7V cells</span><em>&rarr;</em><span>5V output</span><em>=</em><strong>~60% delivered</strong>
				</div>
				<p><?php esc_html_e( 'Real delivered capacity is roughly 15,500 to 18,900mAh. That is about three to four full charges on a typical phone, not six. We tell you the honest charge count before you buy.', 'wghshop' ); ?></p>
				<a class="wghs-excard__link" href="<?php echo esc_url( home_url( '/power-bank-real-capacity/' ) ); ?>"><?php esc_html_e( 'Read the full working', 'wghshop' ); ?> &rarr;</a>
			</article>
		</div>
	</section>

	<!-- Why -->
	<section class="wghs-about__band">
		<div class="wrap">
			<h2 class="wghs-about__h2 wghs-about__h2--light"><?php esc_html_e( 'Why we tell you this', 'wghshop' ); ?></h2>
			<div class="wghs-about__why">
				<div>
					<h4><?php esc_html_e( 'A wrong number costs you twice', 'wghshop' ); ?></h4>
					<p><?php esc_html_e( 'You pay for a spec you never receive, then you replace the thing early when it cannot do the job you bought it for.', 'wghshop' ); ?></p>
				</div>
				<div>
					<h4><?php esc_html_e( 'Honest specs make better choices', 'wghshop' ); ?></h4>
					<p><?php esc_html_e( 'Once you know what a number really means, you stop paying extra for the biggest one on the box and start buying what actually suits your kitchen.', 'wghshop' ); ?></p>
				</div>
				<div>
					<h4><?php esc_html_e( 'We would rather keep the customer', 'wghshop' ); ?></h4>
					<p><?php esc_html_e( 'Selling you the wrong thing once is worth less than selling you the right thing for years, and telling your family about us.', 'wghshop' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- How ordering works -->
	<section class="wrap wghs-about__section">
		<h2 class="wghs-about__h2"><?php esc_html_e( 'You never pay before you see it', 'wghshop' ); ?></h2>
		<p class="wghs-about__lede"><?php esc_html_e( 'The other half of trust is not asking for money first.', 'wghshop' ); ?></p>
		<div class="wghs-about__steps">
			<div class="wghs-stepcard"><div class="wghs-stepcard__n">1</div>
				<h4><?php esc_html_e( 'Order on the site or on WhatsApp', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'Add what you want to the cart and send the whole order in one message. No account, no card.', 'wghshop' ); ?></p></div>
			<div class="wghs-stepcard"><div class="wghs-stepcard__n">2</div>
				<h4><?php esc_html_e( 'We call you to confirm', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'We check the item, the price and your area, and tell you honestly when it can reach you.', 'wghshop' ); ?></p></div>
			<div class="wghs-stepcard"><div class="wghs-stepcard__n">3</div>
				<h4><?php esc_html_e( 'Check it, then pay the rider', 'wghshop' ); ?></h4>
				<p><?php esc_html_e( 'Open the box at your door. If it is not what we said it was, do not pay. That is the whole promise.', 'wghshop' ); ?></p></div>
		</div>
	</section>

	<!-- Closing CTA -->
	<section class="wrap">
		<div class="wghs-about__close">
			<div>
				<h3><?php esc_html_e( 'Questions before you buy?', 'wghshop' ); ?></h3>
				<p><?php esc_html_e( 'Ask us the spec you are unsure about. We will give you the real number, even if it costs us the sale.', 'wghshop' ); ?></p>
			</div>
			<div class="wghs-about__closebtns">
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
