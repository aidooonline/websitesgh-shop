<?php
/**
 * Front page (v2).
 * @package WebsitesGHShop
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/brands' );
get_template_part( 'template-parts/home/promo' );
get_template_part( 'template-parts/home/featured' );
get_template_part( 'template-parts/home/trust' );
get_template_part( 'template-parts/home/cta' );

get_footer();
