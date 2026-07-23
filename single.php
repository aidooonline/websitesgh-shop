<?php
/**
 * Blog detail. Editorial layout, white page, generous measure, sticky rail.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

while ( have_posts() ) : the_post(); ?>

<div class="wghs-single wrap">

	<?php // Breadcrumb. Also feeds BreadcrumbList schema in Sprint 4. ?>
	<nav class="wghs-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wghshop' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'wghshop' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Guides', 'wghshop' ); ?></a>
	</nav>

	<article <?php post_class( 'wghs-article' ); ?>>

		<header class="wghs-article__head">
			<?php
			$cats = get_the_category();
			if ( $cats ) : ?>
				<a class="wghs-kicker wghs-kicker--link" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
					<?php echo esc_html( $cats[0]->name ); ?>
				</a>
			<?php endif; ?>

			<h1 class="wghs-article__title"><?php the_title(); ?></h1>

			<?php if ( has_excerpt() ) : ?>
				<p class="wghs-article__standfirst"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="wghs-byline">
				<span class="wghs-byline__author"><?php echo esc_html( get_the_author() ); ?></span>
				<span class="wghs-byline__sep" aria-hidden="true">&middot;</span>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<span class="wghs-byline__sep" aria-hidden="true">&middot;</span>
				<span><?php echo esc_html( wghs_reading_time() ); ?></span>
				<?php if ( get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' ) ) : ?>
					<span class="wghs-byline__updated">
						<?php
						/* translators: %s: date the guide was last checked. */
						printf( esc_html__( 'Verified %s', 'wghshop' ), esc_html( get_the_modified_date( 'F Y' ) ) );
						?>
					</span>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="wghs-article__lede">
				<?php the_post_thumbnail( 'large', array( 'class' => 'wghs-img', 'loading' => 'eager' ) ); ?>
				<?php $cap = get_the_post_thumbnail_caption(); ?>
				<?php if ( $cap ) : ?><figcaption><?php echo esc_html( $cap ); ?></figcaption><?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="wghs-article__body">
			<div class="wghs-prose">
				<?php the_content(); ?>
			</div>

			<?php // Mid article ad. Sits after the content on mobile, inside the flow on desktop. ?>
			<div class="wghs-inarticle lg:hidden">
				<p class="wghs-railcard__label"><?php esc_html_e( 'Sponsored', 'wghshop' ); ?></p>
				<div class="wgh-adslot wgh-adslot--inarticle" data-slot="inarticle"></div>
			</div>

			<?php // Conversion band. Green here is earned, it is the one strong block on the page. ?>
			<aside class="wghs-cta">
				<p class="wghs-cta__eyebrow"><?php esc_html_e( 'Pay on delivery', 'wghshop' ); ?></p>
				<p class="wghs-cta__title"><?php esc_html_e( 'Check it before you pay for it.', 'wghshop' ); ?></p>
				<p class="wghs-cta__sub"><?php esc_html_e( 'Same day delivery in Accra on orders confirmed before 4pm. You inspect the item, then you pay the rider.', 'wghshop' ); ?></p>
				<div class="wghs-cta__row">
					<a class="wghs-btn wghs-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Shop the range', 'wghshop' ); ?>
					</a>
					<?php $wa = function_exists( 'wghs_whatsapp_link' ) ? wghs_whatsapp_link() : ''; ?>
					<?php if ( $wa ) : ?>
						<a class="wghs-btn wghs-btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Order on WhatsApp', 'wghshop' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</aside>

			<?php
			$tags = get_the_tags();
			if ( $tags ) : ?>
				<ul class="wghs-tags">
					<?php foreach ( $tags as $t ) : ?>
						<li><a href="<?php echo esc_url( get_tag_link( $t->term_id ) ); ?>"><?php echo esc_html( $t->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

	</article>

	<?php get_sidebar(); ?>
</div>

<?php // Related. Full width under the article and rail. ?>
<?php
$related = get_posts( array(
	'post_type'      => 'post',
	'posts_per_page' => 3,
	'post__not_in'   => array( get_the_ID() ),
	'category__in'   => wp_get_post_categories( get_the_ID() ),
	'orderby'        => 'rand',
) );
if ( $related ) : ?>
<section class="wghs-related">
	<div class="wrap">
		<h2 class="wghs-related__title"><?php esc_html_e( 'More guides', 'wghshop' ); ?></h2>
		<div class="wghs-related__grid">
			<?php foreach ( $related as $r ) : ?>
				<article class="wghs-related__item">
					<a class="wghs-related__media" href="<?php echo esc_url( get_permalink( $r ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php
						$thumb = get_the_post_thumbnail( $r, 'medium', array( 'class' => 'wghs-img', 'loading' => 'lazy' ) );
						echo $thumb ? wp_kses_post( $thumb ) : wghs_placeholder_svg( 'wghs-img' );
						?>
					</a>
					<h3><a href="<?php echo esc_url( get_permalink( $r ) ); ?>"><?php echo esc_html( get_the_title( $r ) ); ?></a></h3>
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $r ) ); ?>"><?php echo esc_html( get_the_date( '', $r ) ); ?></time>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php endwhile; get_footer();
