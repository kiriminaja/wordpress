( function( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element ) { return; }
	var h = wp.element.createElement;
	wp.blocks.registerBlockType( 'kiriminaja-official/checkout-district', {
		title: wp.i18n.__( 'KiriminAja Subdistrict', 'kiriminaja-official' ),
		description: wp.i18n.__( 'Searchable shipping destination subdistrict.', 'kiriminaja-official' ),
		category: 'woocommerce',
		parent: [ 'woocommerce/checkout-shipping-address-block' ],
		attributes: { lock: { type: 'object', default: { remove: true, move: true } } },
		supports: { html: false, multiple: false, reusable: false, inserter: false, lock: false },
		edit: function() {
			return h( 'div', wp.blockEditor.useBlockProps( { className: 'kiriof-buyer-district kiriof-buyer-district--editor' } ),
				h( 'label', null, wp.i18n.__( 'Subdistrict', 'kiriminaja-official' ) ),
				h( 'input', { disabled: true, placeholder: wp.i18n.__( 'Select Subdistrict', 'kiriminaja-official' ) } ) );
		},
		save: function() { return null; }
	} );
} )( window.wp );
