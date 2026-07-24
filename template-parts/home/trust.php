<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
$items = array(
	array('M12 2l2.4 7.4H22l-6 4.5 2.3 7.1L12 16.7 5.7 21l2.3-7.1-6-4.5h7.6z','Tested &amp; graded','Every products inspected and graded before listing.'),
	array('M12 2l8 4v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6z','Check before you pay','Open it at the door. If it is wrong, hand it back and pay nothing.'),
	array('M3 7h18v10H3zM3 11h18','Flexible payment','MoMo, bank transfer, or pay on delivery in Accra.'),
	array('M5 12h14M13 6l6 6-6 6','Fast delivery','Same/next-day in Accra and Tema, nationwide courier.'),
);
?>
<section class="bg-wgh-bg border-y border-wgh-line">
	<div class="wrap py-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
		<?php foreach ( $items as $it ) : ?>
			<div class="card-glow p-6 hover:border-wgh-green/40 transition">
				<div class="w-11 h-11 rounded-xl bg-green-fade grid place-items-center mb-4">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0E8C5A" stroke-width="1.8"><path d="<?php echo esc_attr( $it[0] ); ?>" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<h3 class="font-display font-semibold text-wgh-ink"><?php echo wp_kses_post( $it[1] ); ?></h3>
				<p class="text-sm text-wgh-ink2 mt-1.5 leading-relaxed"><?php echo wp_kses_post( $it[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
