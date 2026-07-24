<?php
/**
 * Static pages. Same editorial furniture as the blog: kicker, big title,
 * proper prose spacing, and the rail-free centred measure.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) : the_post(); ?>
<article class="wrap py-12 sm:py-16">
	<header class="max-w-measure">
		<p class="eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<h1 class="mt-2 text-4xl sm:text-5xl font-extrabold leading-[1.08]"><?php the_title(); ?></h1>
	</header>
	<div class="wghs-prose mt-8 max-w-measure"><?php the_content(); ?></div>
</article>
<?php endwhile; get_footer();
