<?php
/**
 * Single article. Two column editorial layout mirroring websitesgh.com r26:
 * wide body plus a sticky right rail, breadcrumbs, reading progress, an
 * auto-built table of contents, a share bar, an author box and related posts.
 *
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

while ( have_posts() ) : the_post();
	$cats = get_the_category();
	$cat  = $cats ? $cats[0] : null;
?>
<div class="wghs-progress" id="wghs-progress"></div>

<article class="wghs-art">

	<header class="wghs-art__hero">
		<div class="wrap">
			<nav class="wghs-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wghshop' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'wghshop' ); ?></a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Guides', 'wghshop' ); ?></a>
				<?php if ( $cat ) : ?>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
				<?php endif; ?>
			</nav>

			<?php if ( $cat ) : ?>
				<a class="wghs-art__cat" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endif; ?>
			<h1 class="wghs-art__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="wghs-art__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="wghs-art__meta">
				<span class="wghs-art__author"><?php echo esc_html( get_the_author() ); ?></span>
				<span aria-hidden="true">&middot;</span>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<span aria-hidden="true">&middot;</span>
				<span><?php echo esc_html( wghs_reading_time() ); ?></span>
				<?php if ( get_the_modified_date( 'Ymd' ) > get_the_date( 'Ymd' ) ) : ?>
					<span class="wghs-art__verified"><?php printf( esc_html__( 'Updated %s', 'wghshop' ), esc_html( get_the_modified_date( 'F Y' ) ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="wrap"><figure class="wghs-art__cover"><?php the_post_thumbnail( 'large' ); ?></figure></div>
	<?php endif; ?>

	<div class="wrap">
		<div class="wghs-art__layout">
			<div class="wghs-art__body">
				<div class="wghs-share" data-wghs-share aria-label="<?php esc_attr_e( 'Share', 'wghshop' ); ?>">
					<span class="wghs-share__label"><?php esc_html_e( 'Share', 'wghshop' ); ?></span>
					<a class="wghs-share__btn" data-net="whatsapp" href="#" rel="noopener" aria-label="WhatsApp">WhatsApp</a>
					<a class="wghs-share__btn" data-net="facebook" href="#" rel="noopener" aria-label="Facebook">Facebook</a>
					<a class="wghs-share__btn" data-net="x" href="#" rel="noopener" aria-label="X">X</a>
					<button class="wghs-share__btn" data-net="copy" type="button"><?php esc_html_e( 'Copy link', 'wghshop' ); ?></button>
				</div>

				<div class="wghs-toc" id="wghs-toc" hidden>
					<p class="wghs-toc__h"><?php esc_html_e( 'On this page', 'wghshop' ); ?></p>
					<ul></ul>
				</div>

				<div class="wghs-prose" id="wghs-article-content">
					<?php the_content(); ?>
				</div>

				<?php // In-article conversion band. ?>
				<aside class="wghs-cta">
					<p class="wghs-cta__eyebrow"><?php esc_html_e( 'Pay on delivery', 'wghshop' ); ?></p>
					<p class="wghs-cta__title"><?php esc_html_e( 'Check it before you pay for it.', 'wghshop' ); ?></p>
					<div class="wghs-cta__row">
						<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Shop the range', 'wghshop' ); ?></a>
						<?php $wa = function_exists( 'wghs_whatsapp_link' ) ? wghs_whatsapp_link() : ''; ?>
						<?php if ( $wa ) : ?><a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Order on WhatsApp', 'wghshop' ); ?></a><?php endif; ?>
					</div>
				</aside>

				<?php $tags = get_the_tags(); if ( $tags ) : ?>
					<ul class="wghs-tags">
						<?php foreach ( $tags as $t ) : ?><li><a href="<?php echo esc_url( get_tag_link( $t->term_id ) ); ?>"><?php echo esc_html( $t->name ); ?></a></li><?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="wghs-author">
					<div class="wghs-author__avatar"><?php echo get_avatar( get_the_author_meta( 'ID' ), 64 ); ?></div>
					<div>
						<p class="wghs-author__name"><?php echo esc_html( get_the_author() ); ?></p>
						<p class="wghs-author__bio"><?php echo esc_html( get_the_author_meta( 'description' ) ?: __( 'We check the numbers before we sell you anything. Real specs, real prices, pay on delivery in Accra.', 'wghshop' ) ); ?></p>
					</div>
				</div>
			</div>

			<?php get_sidebar(); ?>
		</div>
	</div>
</article>

<?php
$related = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'category__in' => wp_get_post_categories( get_the_ID() ), 'orderby' => 'rand' ) );
if ( $related ) : ?>
<section class="wghs-related"><div class="wrap">
	<h2 class="wghs-related__title"><?php esc_html_e( 'More guides', 'wghshop' ); ?></h2>
	<div class="wghs-related__grid">
		<?php foreach ( $related as $r ) : ?>
			<article class="wghs-related__item">
				<a class="wghs-related__media" href="<?php echo esc_url( get_permalink( $r ) ); ?>" tabindex="-1" aria-hidden="true">
					<?php $th = get_the_post_thumbnail( $r, 'medium', array( 'class' => 'wghs-img', 'loading' => 'lazy' ) ); echo $th ? wp_kses_post( $th ) : wghs_placeholder_svg( 'wghs-img', get_the_title( $r ) ); ?>
				</a>
				<h3><a href="<?php echo esc_url( get_permalink( $r ) ); ?>"><?php echo esc_html( get_the_title( $r ) ); ?></a></h3>
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $r ) ); ?>"><?php echo esc_html( get_the_date( '', $r ) ); ?></time>
			</article>
		<?php endforeach; ?>
	</div>
</div></section>
<?php endif; ?>

<?php endwhile; get_footer();
