<?php
/**
 * Proof block.
 *
 * This used to lead with what a blender costs to run per month, which was a
 * mistake: nobody buys an appliance to save six cedis on ECG. It reads as
 * though the shop thinks its customers are counting pesewas, and it says
 * nothing about whether the thing is any good.
 *
 * What actually decides a purchase here is whether the specification is honest,
 * whether the machine survives the job, and whether you can check it before any
 * money changes hands. So the block leads with the gap between what a box
 * claims and what you receive, and the supporting facts are about build and
 * risk, not electricity.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="wrap py-14 sm:py-20">
	<div class="wghs-proof">
		<div class="wghs-proof__main">
			<p class="eyebrow"><?php esc_html_e( 'We check the numbers', 'wghshop' ); ?></p>
			<p class="wghs-proof__figure">
				<span class="wghs-proof__was">8000W</span>
				<span class="wghs-proof__arrow" aria-hidden="true">&rarr;</span>
				<span class="wghs-proof__is">500W</span>
			</p>
			<p class="wghs-proof__caption"><?php esc_html_e( 'What the box claims, and what the motor actually draws. A Ghanaian socket cannot deliver 8000W to anything. We measure before we list, and we publish the working so you can check it yourself.', 'wghshop' ); ?></p>
		</div>
		<div class="wghs-proof__side">
			<p class="wghs-proof__sidehead"><?php esc_html_e( 'What we check before we sell it:', 'wghshop' ); ?></p>
			<ul class="wghs-proof__facts">
				<li><strong><?php esc_html_e( 'The coupling', 'wghshop' ); ?></strong> <?php esc_html_e( 'is the part that decides whether a blender survives daily pepper. Metal lasts years. Plastic strips in months, and most sellers never mention it exists.', 'wghshop' ); ?></li>
				<li><strong><?php esc_html_e( 'The steel grade', 'wghshop' ); ?></strong> <?php esc_html_e( 'is why one kettle rusts within a year and another never does. 304 or 201, and the box almost never tells you which.', 'wghshop' ); ?></li>
				<li><strong><?php esc_html_e( 'The real capacity', 'wghshop' ); ?></strong> <?php esc_html_e( 'of a power bank, not the number printed on it. 30,000mAh delivers about 15,500 to your phone. We state the honest charge count.', 'wghshop' ); ?></li>
			</ul>
			<a class="wghs-readon" href="<?php echo esc_url( home_url( '/how-to-order/' ) ); ?>"><?php esc_html_e( 'How buying from us works', 'wghshop' ); ?></a>
		</div>
	</div>
</section>
