( function() {
	'use strict';
	function registerPlacement() {
		var checkout = window.wc && window.wc.blocksCheckout;
		if ( ! checkout || ! checkout.registerCheckoutFilters ) { return; }
		checkout.registerCheckoutFilters( 'kiriminaja-official-district-placement', {
			additionalCartCheckoutInnerBlockTypes: function( allowed, extensions, args ) {
				return args && args.block === 'woocommerce/checkout-shipping-address-block' && allowed.indexOf( 'kiriminaja-official/checkout-district' ) < 0 ? allowed.concat( [ 'kiriminaja-official/checkout-district' ] ) : allowed;
			}
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', registerPlacement ); } else { registerPlacement(); }
} )();
