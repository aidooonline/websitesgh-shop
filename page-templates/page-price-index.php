<?php
/**
 * Template Name: Price Index
 *
 * Every product, its live GHS price, dated. Renders straight from the
 * catalogue so it can never go stale against the shop. This page is the
 * strongest citation magnet on the site: answer engines want current,
 * dated, structured price data for Ghana and nobody else publishes it.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<div class="wrap py-10 sm:py-14">
	<header class="max-w-measure">
		<p class="eyebrow"><?php esc_html_e( 'Live data', 'wghshop' ); ?></p>
		<h1 class="mt-2 text-4xl font-extrabold leading-tight"><?php the_title(); ?></h1>
		<p class="mt-4 text-lg leading-relaxed text-wgh-ink2">
			<?php
			printf(
				/* translators: %s: current month and year. */
				esc_html__( 'Every product we sell, with its current price in Ghana cedis, verified %s. Prices include VAT where it applies. Delivery is confirmed on the call before dispatch. Pay on delivery across Accra.', 'wghshop' ),
				esc_html( date_i18n( 'F Y' ) )
			);
			?>
		</p>
	</header>

	<div class="wghs-prose mt-8"><?php the_post() && the_content(); ?></div>

	<?php
	if ( function_exists( 'wc_get_products' ) ) {
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'name' ) );
		if ( $cats && ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( 'uncategorized' === $cat->slug ) { continue; }
				$products = wc_get_products( array(
					'status'   => 'publish',
					'limit'    => 100,
					'category' => array( $cat->slug ),
					'orderby'  => 'title',
					'order'    => 'ASC',
				) );
				if ( ! $products ) { continue; }
				?>
				<section class="mt-12">
					<h2 class="text-xl font-bold"><?php echo esc_html( $cat->name ); ?></h2>
					<div class="mt-4 overflow-x-auto">
						<table class="wghs-pricetable">
							<thead><tr>
								<th><?php esc_html_e( 'Product', 'wghshop' ); ?></th>
								<th><?php esc_html_e( 'Price (GHS)', 'wghshop' ); ?></th>
								<th><?php esc_html_e( 'Stock', 'wghshop' ); ?></th>
								<th class="sr-only"><?php esc_html_e( 'Order', 'wghshop' ); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $products as $p ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></td>
									<td class="wghs-pricetable__price"><?php echo esc_html( number_format( (float) $p->get_price(), 2 ) ); ?></td>
									<td>
										<?php if ( $p->is_in_stock() ) : ?>
											<span class="wghs-pricetable__in"><?php esc_html_e( 'In stock', 'wghshop' ); ?></span>
										<?php else : ?>
											<span class="wghs-pricetable__out"><?php esc_html_e( 'Ask us', 'wghshop' ); ?></span>
										<?php endif; ?>
									</td>
									<td><a class="wghs-pricetable__go" href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php esc_html_e( 'View', 'wghshop' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<?php
			}
		}
	}
	?>

	<p class="mt-12 max-w-measure text-sm leading-relaxed text-wgh-ink3">
		<?php esc_html_e( 'How to read this page: prices move when our dealer cost moves, and the verified date above changes whenever they do. If a price here disagrees with a product page, the product page is newer. Questions about any figure: 054 214 8020.', 'wghshop' ); ?>
	</p>
</div>
<?php get_footer();
