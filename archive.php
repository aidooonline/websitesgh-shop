<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header(); ?>
<div class="wrap py-14">
	<header class="mb-10"><span class="eyebrow">Archive</span><h1 class="section-title mt-2"><?php the_archive_title(); ?></h1></header>
	<?php if ( have_posts() ) : ?>
		<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="card-glow overflow-hidden p-5">
					<a href="<?php the_permalink(); ?>"><h2 class="font-display font-semibold text-lg text-wgh-ink hover:text-wgh-green"><?php the_title(); ?></h2></a>
					<p class="text-sm text-wgh-ink2 mt-2"><?php echo esc_html( get_the_excerpt() ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<div class="mt-12"><?php the_posts_pagination(); ?></div>
	<?php endif; ?>
</div>
<?php get_footer();
