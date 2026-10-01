( function( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element ) { return; }
	var h = wp.element.createElement;
	wp.blocks.registerBlockType( 'kiriminaja-official/checkout-district', {
		edit: function() {
			return h( 'div', wp.blockEditor.useBlockProps( { className: 'kiriof-buyer-district kiriof-buyer-district--editor' } ),
				h( 'label', null, wp.i18n.__( 'District', 'kiriminaja-official' ) ),
				h( 'input', { disabled: true, placeholder: wp.i18n.__( 'Select District', 'kiriminaja-official' ) } ) );
		},
		save: function() { return null; }
	} );
} )( window.wp );
