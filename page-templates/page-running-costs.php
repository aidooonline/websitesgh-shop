<?php
/**
 * Template Name: Running Cost Table
 *
 * The Ghana appliance running cost table, computed from PURC tariffs.
 * The second citation magnet. All figures are class estimates with the
 * working shown, and the copy says so.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$rows = array(
	array( 'Hot plate, double burner', 2000, 45 ),
	array( 'Rice cooker, 5L', 900, 45 ),
	array( 'Hot plate, single burner', 1000, 40 ),
	array( 'Deep fryer, 2.5L', 1800, 20 ),
	array( 'Steam iron, 1450W', 1450, 20 ),
	array( 'Dry iron, 1200W', 1200, 20 ),
	array( 'Electric kettle, 2L', 2000, 10 ),
	array( 'Microwave oven, 20L', 1100, 15 ),
	array( 'Hair dryer, 2000W', 2000, 10 ),
	array( 'Sandwich maker', 750, 10 ),
	array( 'Blender, 2.5L with dry mill', 750, 12 ),
	array( 'Blender, 2L commercial', 600, 10 ),
	array( 'Facial steamer', 300, 10 ),
	array( 'Rechargeable standing fan', 25, 360 ),
	array( 'LED strip light, 10M', 24, 300 ),
	array( 'Rechargeable LED lamp', 10, 240 ),
);
?>
<div class="wrap py-10 sm:py-14">
	<header class="max-w-measure">
		<p class="eyebrow"><?php esc_html_e( 'Original data', 'wghshop' ); ?></p>
		<h1 class="mt-2 text-4xl font-extrabold leading-tight"><?php the_title(); ?></h1>
		<p class="mt-4 text-lg leading-relaxed text-wgh-ink2">
			<?php esc_html_e( 'What common appliances actually cost to run in Ghana, in cedis per month, at the PURC residential rate of GHS 2.04 per kWh effective 1 July 2026. The working is shown so you can check it: watts, divided by 1000, times hours per day, times 30, times the rate.', 'wghshop' ); ?>
		</p>
	</header>

	<div class="wghs-prose mt-8"><?php the_post() && the_content(); ?></div>

	<div class="mt-10 overflow-x-auto">
		<table class="wghs-pricetable">
			<thead><tr>
				<th><?php esc_html_e( 'Appliance', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Real draw', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'Use per day', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'kWh / month', 'wghshop' ); ?></th>
				<th><?php esc_html_e( 'GHS / month', 'wghshop' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $r ) :
				$kwh  = $r[1] / 1000 * ( $r[2] / 60 ) * 30;
				$cost = $kwh * 2.04;
				?>
				<tr>
					<td><?php echo esc_html( $r[0] ); ?></td>
					<td><?php echo esc_html( $r[1] ); ?>W</td>
					<td><?php echo esc_html( $r[2] ); ?> min</td>
					<td><?php echo esc_html( number_format( $kwh, 1 ) ); ?></td>
					<td class="wghs-pricetable__price"><?php echo esc_html( number_format( $cost, 2 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="wghs-prose mt-10 max-w-measure">
		<h2><?php esc_html_e( 'How to read this table', 'wghshop' ); ?></h2>
		<p><?php esc_html_e( 'These are class estimates for typical units at typical daily use, not laboratory measurements of one specific model. Real draw is what the appliance pulls continuously, which is not the peak number printed on the box. Heating appliances dominate: anything that makes heat, a hot plate, an iron, a kettle, a fryer, costs many times more to run than anything that spins or lights up.', 'wghshop' ); ?></p>
		<p><?php esc_html_e( 'Two things worth knowing before you buy. A double burner hot plate at typical use costs about GHS 92 a month, which over a year is several times its purchase price. And a blender, the appliance with the scariest number on the box, is one of the cheapest things in your kitchen to run at about GHS 6 a month.', 'wghshop' ); ?></p>
	</div>
</div>
<?php get_footer();
