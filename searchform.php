<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<form role="search" method="get" class="flex gap-2" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<input type="search" class="input" name="s" placeholder="<?php esc_attr_e( 'Search products...', 'wghshop' ); ?>" value="<?php echo get_search_query(); ?>">
	<input type="hidden" name="post_type" value="product">
	<button type="submit" class="btn-primary px-5"><?php esc_html_e( 'Search', 'wghshop' ); ?></button>
</form>
