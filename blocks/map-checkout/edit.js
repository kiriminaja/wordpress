( function( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element ) { return; }
	var h = wp.element.createElement;
	wp.blocks.registerBlockType( 'kiriminaja-official/map-checkout', {
		title: wp.i18n.__( 'KiriminAja Delivery Map', 'kiriminaja-official' ),
		description: wp.i18n.__( 'Optional delivery pin for the shipping address.', 'kiriminaja-official' ),
		category: 'woocommerce',
		parent: [ 'woocommerce/checkout-shipping-address-block' ],
		attributes: { lock: { type: 'object', default: { remove: true, move: true } } },
		supports: { html: false, multiple: false, reusable: false, inserter: false, lock: false },
		edit: function() {
			return h( 'div', wp.blockEditor.useBlockProps( { className: 'kiriof-buyer-map kiriof-buyer-map--editor' } ),
				h( 'strong', null, wp.i18n.__( 'Delivery pin', 'kiriminaja-official' ) ),
				h( 'p', null, wp.i18n.__( 'Optional. Customers can choose a delivery pin at checkout.', 'kiriminaja-official' ) ) );
		},
		save: function() { return null; }
	} );
} )( window.wp );
