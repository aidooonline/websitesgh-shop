<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) : the_post(); ?>
<article class="wrap py-14 max-w-3xl">
	<header class="mb-8"><h1 class="section-title"><?php the_title(); ?></h1></header>
	<div class="prose-aur max-w-none"><?php the_content(); ?></div>
</article>
<?php endwhile; get_footer();
