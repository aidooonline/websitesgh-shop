/* Order via WhatsApp: payment method registration for WooCommerce Checkout Blocks */
(function () {
	'use strict';
	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings ) { return; }
	var settings = window.wc.wcSettings.getSetting( 'wghs_whatsapp_data', {} );
	var decode   = window.wp.htmlEntities.decodeEntities;
	var el       = window.wp.element.createElement;
	var label    = decode( settings.title || 'Order via WhatsApp' );
	var Content  = function () { return el( 'div', null, decode( settings.description || '' ) ); };

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'wghs_whatsapp',
		label: label,
		ariaLabel: label,
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () { return true; },
		supports: { features: [ 'products' ] }
	} );
})();
