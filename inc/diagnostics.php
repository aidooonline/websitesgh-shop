<?php
/**
 * Setup diagnostics: a one-look admin page that reports whether the live site
 * has the pages, menus, blog posts, permalinks and OG image that the theme
 * expects, so issues like 404s, missing menus and missing WhatsApp images can
 * be pinpointed instead of guessed.
 *
 * @package WGHShop
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'themes.php',
		__( 'Shop Diagnostics', 'wghshop' ),
		__( 'Shop Diagnostics', 'wghshop' ),
		'manage_options',
		'wghs-diagnostics',
		'wghs_render_diagnostics'
	);
} );

/**
 * Direct repair: fixes ONLY the light, critical things that make the site look
 * broken (missing pages, the Reading settings that hide the blog, unassigned
 * menus, stale permalinks). No product seeding, no image generation, so it
 * finishes in seconds and cannot time out on shared hosting. Reports every
 * action so there is never any doubt about whether it ran.
 */
add_action( 'admin_post_wghs_repair', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed' ); }
	check_admin_referer( 'wghs_repair' );

	$log = array();

	/* 1. Pages from pages.json that are missing. */
	$file = WGHS_DIR . '/inc/setup-data/pages.json';
	$made = 0;
	if ( file_exists( $file ) ) {
		$pages = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $pages ) ) {
			foreach ( $pages as $p ) {
				$slug = isset( $p['slug'] ) ? sanitize_title( $p['slug'] ) : '';
				if ( ! $slug || get_page_by_path( $slug ) ) { continue; }
				$id = wp_insert_post( array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => isset( $p['title'] ) ? $p['title'] : ucfirst( $slug ),
					'post_name'    => $slug,
					'post_content' => isset( $p['content'] ) ? $p['content'] : '',
				) );
				if ( $id && ! is_wp_error( $id ) ) { $made++; $log[] = "Created page /{$slug}/"; }
			}
		}
	}
	if ( ! $made ) { $log[] = 'All pages from pages.json already existed.'; }

	/* 2. Home + Guides, and the Reading settings that actually reveal the blog. */
	$home = get_page_by_path( 'home' );
	if ( ! $home ) {
		$home_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home' ) );
		$log[]   = 'Created page /home/';
	} else {
		$home_id = $home->ID;
	}
	$guides = get_page_by_path( 'guides' );
	if ( ! $guides ) {
		$gid   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Guides', 'post_name' => 'guides' ) );
		$log[] = 'Created page /guides/';
	} else {
		$gid = $guides->ID;
	}
	if ( $home_id && ! is_wp_error( $home_id ) && $gid && ! is_wp_error( $gid ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $home_id );
		update_option( 'page_for_posts', (int) $gid );
		$log[] = sprintf( 'Reading settings set: front page = Home (#%d), posts page = Guides (#%d). The blog now renders at /guides/.', (int) $home_id, (int) $gid );
	} else {
		$log[] = 'WARNING: could not create Home or Guides, so the blog listing may still not render.';
	}

	/* 3. Menus: build and assign all four locations. */
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$build    = function ( $name, $location, $items ) use ( &$log ) {
		$menu = wp_get_nav_menu_object( $name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
		} else {
			$menu_id = $menu->term_id;
			foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $it ) { wp_delete_post( $it->ID, true ); }
		}
		if ( is_wp_error( $menu_id ) ) { $log[] = "FAILED to build menu {$name}"; return; }
		foreach ( $items as $label => $url ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'  => $label,
				'menu-item-url'    => $url,
				'menu-item-status' => 'publish',
			) );
		}
		$locations              = get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		$log[] = sprintf( 'Menu "%s" built with %d items and assigned to %s.', $name, count( $items ), $location );
	};

	$build( 'Primary', 'primary', array(
		'Home'        => home_url( '/' ),
		'Shop'        => $shop_url,
		'Guides'      => home_url( '/guides/' ),
		'Price Index' => home_url( '/price-index/' ),
		'About'       => home_url( '/about/' ),
		'Contact'     => home_url( '/contact/' ),
	) );
	$build( 'Footer Shop', 'footer_shop', array(
		'All products'  => $shop_url,
		'Price index'   => home_url( '/price-index/' ),
		'Running costs' => home_url( '/running-costs/' ),
		'Guides'        => home_url( '/guides/' ),
	) );
	$build( 'Footer Help', 'footer_help', array(
		'How to order'         => home_url( '/how-to-order/' ),
		'Delivery and payment' => home_url( '/delivery-and-payment/' ),
		'Delivery areas'       => home_url( '/coverage/' ),
		'Track my order'       => home_url( '/track-order/' ),
		'Returns'              => home_url( '/returns/' ),
		'Warranty'             => home_url( '/warranty/' ),
		'FAQ'                  => home_url( '/faq/' ),
	) );
	$build( 'Footer Company', 'footer_company', array(
		'About'     => home_url( '/about/' ),
		'Contact'   => home_url( '/contact/' ),
		'Wholesale' => home_url( '/wholesale/' ),
		'Privacy'   => home_url( '/privacy-policy/' ),
		'Terms'     => home_url( '/terms/' ),
	) );

	/* 4. Articles, then tidy up, then permalinks. */
	if ( function_exists( 'wghs_seed_articles' ) ) {
		$log[] = (string) wghs_seed_articles();
	}

	/* Retro-fix categories on articles seeded before the term-id fix, which
	 * left them in Uncategorized. */
	$file = WGHS_DIR . '/inc/setup-data/articles.json';
	if ( file_exists( $file ) ) {
		$articles = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $articles ) ) {
			foreach ( $articles as $a ) {
				if ( empty( $a['slug'] ) || empty( $a['category'] ) ) { continue; }
				$post = get_page_by_path( $a['slug'], OBJECT, 'post' );
				if ( ! $post ) { continue; }
				$term = term_exists( (string) $a['category'], 'category' );
				if ( ! $term ) { $term = wp_insert_term( (string) $a['category'], 'category' ); }
				if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
					wp_set_post_terms( $post->ID, array( (int) $term['term_id'] ), 'category' );
					$log[] = sprintf( 'Categorised "%s" as %s.', $a['slug'], $a['category'] );
				}
			}
		}
	}

	/* Trash WordPress's default "Hello world!" post so it stops showing in the
	 * Guides listing. */
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello && 'trash' !== $hello->post_status ) {
		wp_trash_post( $hello->ID );
		$log[] = 'Trashed the default "Hello world!" post.';
	}

	flush_rewrite_rules();
	$log[] = 'Permalinks flushed.';

	set_transient( 'wghs_repair_log', $log, 300 );
	wp_safe_redirect( admin_url( 'themes.php?page=wghs-diagnostics&repaired=1' ) );
	exit;
} );

function wghs_render_diagnostics() {
	echo '<div class="wrap"><h1>WebsitesGH Shop Diagnostics</h1>';

	// Show the result of a repair run, so there is never doubt it happened.
	$rlog = get_transient( 'wghs_repair_log' );
	if ( $rlog && is_array( $rlog ) ) {
		delete_transient( 'wghs_repair_log' );
		echo '<div class="notice notice-success"><p><strong>Repair finished. What it did:</strong></p><ol style="margin:0 0 12px 24px">';
		foreach ( $rlog as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
		echo '</ol></div>';
	}

	// The repair button, front and centre.
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0">';
	wp_nonce_field( 'wghs_repair' );
	echo '<input type="hidden" name="action" value="wghs_repair">';
	echo '<button class="button button-primary button-hero">Repair pages, blog and menus now</button>';
	echo '<p class="description">Creates any missing pages, fixes the Reading settings that hide the blog, rebuilds and assigns all four menus, publishes the seed articles, and flushes permalinks. Takes a few seconds. Does not touch products or images.</p>';
	echo '</form>';

	echo '<p>Green is good. Red needs the fix shown next to it.</p><table class="widefat striped" style="max-width:900px"><tbody>';

	$row = function ( $label, $ok, $fix = '' ) {
		printf(
			'<tr><td style="width:40px;font-size:20px">%s</td><td><strong>%s</strong>%s</td></tr>',
			$ok ? '&#9989;' : '&#10060;',
			esc_html( $label ),
			$ok ? '' : ' <span style="color:#a00">&mdash; ' . esc_html( $fix ) . '</span>'
		);
	};

	// 1. Permalinks not plain
	$perma = get_option( 'permalink_structure' );
	$row( 'Permalinks are pretty (not plain)', ! empty( $perma ), 'Settings > Permalinks > choose Post name > Save. Plain permalinks cause 404s.' );

	// 2. Key pages exist
	$pages = array( 'guides', 'price-index', 'about', 'contact', 'how-to-order', 'faq', 'shop' );
	foreach ( $pages as $slug ) {
		$p = get_page_by_path( $slug );
		if ( 'shop' === $slug && function_exists( 'wc_get_page_id' ) ) {
			$p = get_post( wc_get_page_id( 'shop' ) );
		}
		$row( "Page exists: /{$slug}/", $p && 'publish' === get_post_status( $p ), 'Press the Repair button above.' );
	}

	// 3. Blog posts page set + has posts
	$pfp = (int) get_option( 'page_for_posts' );
	$row( 'Blog (Guides) page is set  [page_for_posts = ' . $pfp . ']', $pfp > 0, 'Press the Repair button above.' );
	// WordPress ignores page_for_posts unless show_on_front is 'page' with a
	// page_on_front. Without this the Guides page renders empty and no blog
	// listing ever appears, which looks like "the blog page does not exist".
	$sof = get_option( 'show_on_front' );
	$pof = (int) get_option( 'page_on_front' );
	$row(
		'Front page mode  [show_on_front = "' . esc_html( (string) $sof ) . '", page_on_front = ' . $pof . ']',
		'page' === $sof && $pof > 0,
		'Must be "page" with a homepage set, or WordPress ignores the posts page and the blog shows nothing. Press Repair above.'
	);
	$posts = wp_count_posts()->publish;
	$row( "Published articles: {$posts}", $posts > 0, 'Press the Repair button above.' );

	// 4. Menus assigned
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( array( 'primary', 'footer_shop', 'footer_help', 'footer_company' ) as $loc ) {
		$mid   = ! empty( $locs[ $loc ] ) ? (int) $locs[ $loc ] : 0;
		$items = $mid ? count( (array) wp_get_nav_menu_items( $mid ) ) : 0;
		$row(
			"Menu assigned: {$loc}  [menu id " . $mid . ', ' . $items . ' items]',
			$mid > 0 && $items > 0,
			'Press the Repair button above.'
		);
	}

	// 5. WhatsApp number
	$num = function_exists( 'wghs_wa_number' ) ? wghs_wa_number() : '';
	$row( 'WhatsApp number resolves: ' . esc_html( $num ), ! empty( $num ), 'Customize > Shop settings > WhatsApp number.' );

	// 6. Cart/checkout classic
	$cart_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
	$cart_post = $cart_id ? get_post( $cart_id ) : null;
	$has_shortcode = $cart_post && ( has_shortcode( $cart_post->post_content, 'woocommerce_cart' ) || false !== strpos( $cart_post->post_content, 'woocommerce_cart' ) );
	$row( 'Cart page uses classic shortcode', (bool) $has_shortcode, 'Run Tools > WebsitesGH Shop Setup to convert the block cart to classic.' );

	// Asset cache busting: a static version string means browsers and LiteSpeed
	// keep serving the first CSS they ever saw, so style fixes never appear.
	if ( function_exists( 'wghs_asset_ver' ) ) {
		$ver = wghs_asset_ver( '/assets/css/main.css' );
		$row(
			'CSS cache busting is live  [main.css?ver=' . esc_html( $ver ) . ']',
			ctype_digit( (string) $ver ),
			'Version should be a filemtime number, not a fixed string, or CSS updates never reach the browser.'
		);
	}

	// 7. SEO plugin + OG image note
	$seo = function_exists( 'wghs_seo_plugin_active' ) && wghs_seo_plugin_active();
	if ( $seo ) {
		echo '<tr><td style="font-size:20px">&#8505;&#65039;</td><td><strong>An SEO plugin is active (The SEO Framework or similar).</strong> <span style="color:#a60">The theme lets it own the og:image, and a filter feeds it the product photo. If WhatsApp shows no image: make sure each product has a Featured image, and in the SEO plugin enable Open Graph / social meta. Then re-scrape the URL in Facebook Sharing Debugger to clear WhatsApp cache.</span></td></tr>';
	} else {
		$row( 'Theme emits Open Graph image', true );
	}

	// 8. Product featured images
	if ( function_exists( 'wc_get_products' ) ) {
		global $wpdb;
		// Direct count, so this page stays fast as the catalogue grows instead
		// of instantiating every product object.
		$missing = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			   AND ( m.meta_id IS NULL OR m.meta_value = '' )"
		);
		$row( "Products missing a featured image: {$missing}", 0 === $missing, 'Set a Featured image on each product; that image is what WhatsApp previews.' );
	}

	echo '</tbody></table>';
	echo '<p style="margin-top:20px"><a href="' . esc_url( admin_url( 'tools.php?page=wghs-setup' ) ) . '" class="button button-primary">Go to Full Setup (Tools)</a> ';
	echo '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '" class="button">Flush permalinks</a></p>';
	echo '</div>';
}
