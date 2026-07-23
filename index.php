<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header(); ?>
<div class="wrap py-14">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="mb-10"><span class="eyebrow">Journal</span><h1 class="section-title mt-2"><?php single_post_title(); ?></h1></header>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="card-glow overflow-hidden flex flex-col hover:border-wgh-green/40 transition">
					<a href="<?php the_permalink(); ?>" class="aspect-video bg-wgh-bg block overflow-hidden">
						<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'w-full h-full object-cover' ) ); } else { echo wghs_placeholder_svg( 'w-full h-full' ); } ?>
					</a>
					<div class="p-5 flex flex-col flex-1">
						<span class="font-mono text-[10px] uppercase tracking-widest text-wgh-ink2"><?php echo esc_html( get_the_date() ); ?></span>
						<a href="<?php the_permalink(); ?>"><h2 class="font-display font-semibold text-lg mt-1 text-wgh-ink hover:text-wgh-green leading-snug"><?php the_title(); ?></h2></a>
						<p class="text-sm text-wgh-ink2 mt-2 flex-1"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<a href="<?php the_permalink(); ?>" class="menu-link mt-4 text-wgh-green">Read more &rarr;</a>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<div class="mt-12"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '&larr;', 'next_text' => '&rarr;' ) ); ?></div>
	<?php else : ?>
		<p class="text-wgh-ink2"><?php esc_html_e( 'Nothing here yet.', 'wghshop' ); ?></p>
	<?php endif; ?>
</div>
<?php get_footer();
