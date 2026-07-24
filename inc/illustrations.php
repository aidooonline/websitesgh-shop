<?php
/**
 * SVG illustrations.
 *
 * Why SVG rather than stock photography: every product here will eventually
 * carry a real photograph shot by the owner, and those photos are what sell.
 * Until they exist, an empty grey box makes the shop look abandoned. These
 * line drawings fill the gap, weigh nothing, scale to any size, never 404,
 * and carry no licensing risk.
 *
 * They are drawn on the brand palette: ink strokes, green accents, gold for
 * the one detail that should catch the eye.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Map a category or product name to an illustration key.
 *
 * @param string $name Category or product name.
 * @return string
 */
function wghs_art_key( $name ) {
	$n = strtolower( (string) $name );

	$rules = array(
		'blender'   => array( 'blender', 'mill', 'juicer' ),
		'kettle'    => array( 'kettle', 'flask', 'thermos' ),
		'cooker'    => array( 'rice cooker', 'hot plate', 'cooker', 'burner', 'fryer', 'microwave', 'sandwich', 'cookware' ),
		'iron'      => array( 'iron', 'steamer', 'garment', 'laundry', 'drying', 'lint' ),
		'power'     => array( 'power bank', 'charger', 'cable', 'battery', 'powerbank' ),
		'audio'     => array( 'earbud', 'headset', 'speaker', 'audio', 'headphone' ),
		'computing' => array( 'mouse', 'keyboard', 'flash drive', 'usb', 'webcam', 'cooling pad', 'stand', 'computing' ),
		'grooming'  => array( 'clipper', 'hair', 'shaver', 'straighten', 'dryer', 'facial', 'scale', 'personal care' ),
		'bag'       => array( 'backpack', 'bag', 'school', 'lunch' ),
		'light'     => array( 'lamp', 'led', 'light', 'fan', 'solar', 'extension', 'socket' ),
		'phone'     => array( 'phone', 'holder', 'ring light', 'tripod' ),
	);

	foreach ( $rules as $key => $words ) {
		foreach ( $words as $w ) {
			if ( false !== strpos( $n, $w ) ) {
				return $key;
			}
		}
	}
	return 'box';
}

/**
 * The drawing for a given key. Stroke based, currentColor aware.
 *
 * @param string $key Illustration key.
 * @return string Inner SVG markup on a 0 0 120 120 canvas.
 */
function wghs_art_paths( $key ) {
	$G = '#0E8C5A';
	$K = '#14201A';
	$A = '#E2A013';

	$art = array(

		'blender' =>
			'<path d="M42 22h36l-4 34H46z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M46 40h28" stroke="' . $G . '" stroke-width="2.5"/>
			 <path d="M48 56h24v10H48z" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <path d="M44 66h32v22a6 6 0 0 1-6 6H50a6 6 0 0 1-6-6z" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <circle cx="54" cy="80" r="3.5" fill="' . $A . '"/>
			 <path d="M64 78h8M64 84h6" stroke="' . $K . '" stroke-width="2" stroke-linecap="round"/>
			 <path d="M60 26v14" stroke="' . $G . '" stroke-width="2" stroke-linecap="round"/>',

		'kettle' =>
			'<path d="M38 46h40l4 38a8 8 0 0 1-8 9H42a8 8 0 0 1-8-9z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M78 54c10 2 14 8 14 14s-5 11-12 12" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linecap="round"/>
			 <path d="M46 46c0-8 6-12 14-12s14 4 14 12" fill="none" stroke="' . $G . '" stroke-width="2.5" stroke-linecap="round"/>
			 <rect x="46" y="66" width="10" height="20" rx="3" fill="none" stroke="' . $G . '" stroke-width="2"/>
			 <circle cx="70" cy="86" r="3" fill="' . $A . '"/>',

		'cooker' =>
			'<rect x="26" y="40" width="68" height="46" rx="7" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <circle cx="52" cy="63" r="14" fill="none" stroke="' . $G . '" stroke-width="2.5"/>
			 <circle cx="52" cy="63" r="7" fill="none" stroke="' . $G . '" stroke-width="2"/>
			 <circle cx="80" cy="54" r="4" fill="' . $A . '"/>
			 <path d="M74 70h12M74 78h8" stroke="' . $K . '" stroke-width="2" stroke-linecap="round"/>
			 <path d="M36 40V30M60 40V26M84 40V32" stroke="' . $K . '" stroke-width="2" stroke-linecap="round" opacity=".45"/>',

		'iron' =>
			'<path d="M24 76c0-16 14-26 34-26h30v26z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M20 82h72" stroke="' . $G . '" stroke-width="3" stroke-linecap="round"/>
			 <path d="M52 50c2-10 10-14 20-14h16" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linecap="round"/>
			 <circle cx="70" cy="64" r="4" fill="' . $A . '"/>
			 <path d="M96 40c4 4 4 8 0 12" stroke="' . $G . '" stroke-width="2" stroke-linecap="round" fill="none"/>',

		'power' =>
			'<rect x="40" y="20" width="40" height="80" rx="8" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <rect x="48" y="34" width="24" height="34" rx="3" fill="none" stroke="' . $G . '" stroke-width="2"/>
			 <path d="M62 40l-8 14h8l-6 12" fill="none" stroke="' . $A . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
			 <circle cx="52" cy="82" r="2.5" fill="' . $G . '"/>
			 <circle cx="60" cy="82" r="2.5" fill="' . $G . '"/>
			 <circle cx="68" cy="82" r="2.5" fill="' . $K . '" opacity=".25"/>',

		'audio' =>
			'<path d="M30 68V56a30 30 0 0 1 60 0v12" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linecap="round"/>
			 <rect x="22" y="64" width="16" height="26" rx="7" fill="none" stroke="' . $G . '" stroke-width="2.5"/>
			 <rect x="82" y="64" width="16" height="26" rx="7" fill="none" stroke="' . $G . '" stroke-width="2.5"/>
			 <circle cx="30" cy="77" r="3" fill="' . $A . '"/>
			 <circle cx="90" cy="77" r="3" fill="' . $A . '"/>',

		'computing' =>
			'<rect x="22" y="34" width="76" height="48" rx="5" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <path d="M14 92h92" stroke="' . $G . '" stroke-width="3" stroke-linecap="round"/>
			 <path d="M36 48h34M36 58h44M36 68h24" stroke="' . $K . '" stroke-width="2" stroke-linecap="round" opacity=".55"/>
			 <circle cx="86" cy="48" r="3.5" fill="' . $A . '"/>',

		'grooming' =>
			'<rect x="46" y="18" width="28" height="42" rx="6" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <path d="M46 60h28l6 34a7 7 0 0 1-7 8H47a7 7 0 0 1-7-8z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M52 28h16M52 36h16" stroke="' . $G . '" stroke-width="2.5" stroke-linecap="round"/>
			 <circle cx="60" cy="76" r="4" fill="' . $A . '"/>',

		'bag' =>
			'<path d="M32 46h56v46a8 8 0 0 1-8 8H40a8 8 0 0 1-8-8z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M46 46V34a14 14 0 0 1 28 0v12" fill="none" stroke="' . $G . '" stroke-width="2.5" stroke-linecap="round"/>
			 <rect x="46" y="64" width="28" height="20" rx="4" fill="none" stroke="' . $K . '" stroke-width="2"/>
			 <circle cx="60" cy="74" r="3.5" fill="' . $A . '"/>',

		'light' =>
			'<path d="M60 22a24 24 0 0 1 14 43v9H46v-9a24 24 0 0 1 14-43z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M48 82h24M50 90h20" stroke="' . $G . '" stroke-width="2.5" stroke-linecap="round"/>
			 <path d="M60 40v18" stroke="' . $A . '" stroke-width="2.5" stroke-linecap="round"/>
			 <path d="M24 34l8 4M96 34l-8 4M28 62h9M92 62h-9" stroke="' . $G . '" stroke-width="2" stroke-linecap="round" opacity=".6"/>',

		'phone' =>
			'<rect x="42" y="18" width="36" height="84" rx="8" fill="none" stroke="' . $K . '" stroke-width="2.5"/>
			 <path d="M52 28h16" stroke="' . $K . '" stroke-width="2" stroke-linecap="round" opacity=".5"/>
			 <rect x="50" y="38" width="20" height="34" rx="3" fill="none" stroke="' . $G . '" stroke-width="2"/>
			 <circle cx="60" cy="88" r="4" fill="' . $A . '"/>',

		'box' =>
			'<path d="M60 24l32 16v40L60 96 28 80V40z" fill="none" stroke="' . $K . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <path d="M28 40l32 16 32-16M60 56v40" fill="none" stroke="' . $G . '" stroke-width="2.5" stroke-linejoin="round"/>
			 <circle cx="76" cy="48" r="3.5" fill="' . $A . '"/>',
	);

	return isset( $art[ $key ] ) ? $art[ $key ] : $art['box'];
}

/**
 * A complete illustration block, tinted card background plus the drawing.
 *
 * @param string $name  Product or category name, used to pick the drawing.
 * @param string $class Wrapper classes.
 * @return string
 */
function wghs_art( $name = '', $class = '' ) {
	$key = wghs_art_key( $name );
	return sprintf(
		'<span class="wghs-art %s" aria-hidden="true">
			<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" role="presentation" focusable="false">
				<circle cx="60" cy="60" r="52" fill="#E9F7F0"/>
				%s
			</svg>
		</span>',
		esc_attr( $class ),
		wghs_art_paths( $key )
	);
}

/**
 * Replace the old flat placeholder with the illustration set.
 * Kept under the original name so every existing template picks it up.
 *
 * @param string $class Classes.
 * @param string $name  Optional name for choosing the drawing.
 * @return string
 */
function wghs_placeholder_svg( $class = '', $name = '' ) {
	if ( ! $name && function_exists( 'get_the_title' ) ) {
		$name = get_the_title();
	}
	return wghs_art( $name, $class );
}
