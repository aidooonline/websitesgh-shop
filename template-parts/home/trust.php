<?php
/**
 * How ordering works. Three steps. This block exists to kill the one
 * objection that stops Ghanaians buying online: paying before seeing.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$steps = array(
	array( '1', __( 'Order it', 'wghshop' ), __( 'On the site or on WhatsApp. Name, phone, location. Nothing else.', 'wghshop' ) ),
	array( '2', __( 'We call you', 'wghshop' ), __( 'Usually within the hour. We confirm the item, the price and the delivery window before anything moves.', 'wghshop' ) ),
	array( '3', __( 'Check it, then pay', 'wghshop' ), __( 'The rider hands it over. Open it, look at it, test it. Then pay. If it is wrong, hand it back and pay nothing.', 'wghshop' ) ),
);
?>
<section class="bg-wgh-line2 py-14 sm:py-20 wghs-tex">
	<div class="wrap">
		<p class="eyebrow"><?php esc_html_e( 'Pay on delivery', 'wghshop' ); ?></p>
		<h2 class="section-title mt-2 max-w-xl"><?php esc_html_e( 'You pay when it reaches you. Not before.', 'wghshop' ); ?></h2>
		<div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-3">
			<?php foreach ( $steps as $s ) : ?>
				<div class="wghs-step">
					<span class="wghs-step__num"><?php echo esc_html( $s[0] ); ?></span>
					<h3 class="wghs-step__title"><?php echo esc_html( $s[1] ); ?></h3>
					<p class="wghs-step__body"><?php echo esc_html( $s[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="mt-8 text-sm text-wgh-ink2">
			<?php esc_html_e( 'Prefer to pay ahead? Mobile money works too: 054 214 8020. But you never have to.', 'wghshop' ); ?>
		</p>
	</div>
</section>
