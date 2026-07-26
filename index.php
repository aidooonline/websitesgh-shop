<?php
/**
 * Blog listing. White page, green only as accent.
 * Lead story then a two column river, with the sticky rail on the right.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<div class="wghs-blog wrap">

	<header class="wghs-blog__masthead">
		<p class="wghs-kicker"><?php esc_html_e( 'Guides', 'wghshop' ); ?></p>
		<h1 class="wghs-blog__title">
			<?php echo is_home() && ! is_front_page() ? esc_html( single_post_title( '', false ) ) : esc_html__( 'Guides', 'wghshop' ); ?>
		</h1>
		<p class="wghs-blog__standfirst">
			<?php esc_html_e( 'We check the numbers before we sell you anything. Real wattages, real running costs in cedis, real prices verified this month.', 'wghshop' ); ?>
		</p>
	</header>

	<div class="wghs-blog__body">
		<div class="wghs-blog__main">

			<?php if ( have_posts() ) : $i = 0; ?>

				<?php while ( have_posts() ) : the_post(); $i++; ?>

						<?php // River. Small image left, text right. Reads like a paper, not a card grid. ?>
						<article class="wghs-river">
							<a class="wghs-river__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
								<?php
								if ( has_post_thumbnail() ) {
									the_post_thumbnail( 'medium', array( 'class' => 'wghs-img', 'loading' => 'lazy' ) );
								} else {
									echo wghs_placeholder_svg( 'wghs-img' );
								}
								?>
							</a>
							<div class="wghs-river__text">
								<?php wghs_post_meta(); ?>
								<h2 class="wghs-river__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>
								<p class="wghs-river__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
							</div>
						</article>

					<?php // In feed ad after the fourth item, mobile and desktop. ?>
					<?php if ( 4 === $i ) : ?>
						<div class="wghs-infeed">
							<p class="wghs-railcard__label"><?php esc_html_e( 'Sponsored', 'wghshop' ); ?></p>
							<div class="wgh-adslot wgh-adslot--infeed" data-slot="infeed"></div>
						</div>
					<?php endif; ?>

				<?php endwhile; ?>

				<nav class="wghs-pagination">
					<?php
					the_posts_pagination( array(
						'mid_size'  => 1,
						'prev_text' => esc_html__( 'Newer', 'wghshop' ),
						'next_text' => esc_html__( 'Older', 'wghshop' ),
					) );
					?>
				</nav>

			<?php else : ?>
				<p class="wghs-empty"><?php esc_html_e( 'No guides published yet.', 'wghshop' ); ?></p>
			<?php endif; ?>

		</div>

		<?php get_sidebar(); ?>
	</div>
</div>

<?php get_footer();
