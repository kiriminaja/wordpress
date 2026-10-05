( function( root ) {
	'use strict';
	var fields = [ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
	function address( input ) {
		var result = {};
		fields.forEach( function( field ) { result[ field ] = String( input[ field ] || '' ).trim().replace( /\s+/g, ' ' ); } );
		result.country = result.country.toUpperCase();
		result.postcode = result.postcode.replace( /\s+/g, '' ).toUpperCase();
		return result;
	}
	function sameAddress( first, second ) { return JSON.stringify( address( first || {} ) ) === JSON.stringify( address( second || {} ) ); }
	function create( options ) {
		var session = root.kiriofBuyerCheckoutSession;
		var current = address( options.address || {} );
		var scope = options.scope || 'billing';
		var selection = null;
		var point = null;
		var destroyed = false;
		var listeners = options.onChange || function() {};
		var saved = options.savedDestination;
		if ( saved && saved.country === current.country && saved.postcode === current.postcode && saved.district_id ) {
			selection = { id: saved.district_id, label: saved.district_label };
			if ( saved.version === 2 && sameAddress( saved.shipping_address, current ) ) point = normalizePoint( saved.destination_latitude, saved.destination_longitude );
		}
		function destination() {
			var value = session.normalizeDestination( { district_id: current.country === 'ID' && selection ? selection.id : '', district_label: selection ? selection.label : '', country: current.country, postcode: current.postcode, address_type: 'shipping' } );
			if ( point && value.district_id ) value = Object.assign( {}, value, { version: 2, destination_latitude: point.latitude, destination_longitude: point.longitude, shipping_address: address( current ) } );
			return value;
		}
		function normalizePoint( latitude, longitude ) {
			if ( ! root.kiriofMapCheckout ) return null;
			return root.kiriofMapCheckout.normalizePoint( latitude, longitude );
		}
		var queue = session.createQueue( {
			send: options.send,
			isBlocked: options.isBlocked,
			onChange: function() { if ( ! destroyed ) listeners( state() ); },
			setTimeout: options.setTimeout || root.setTimeout.bind( root ),
			clearTimeout: options.clearTimeout || root.clearTimeout.bind( root )
		} );
		function state() { return { address: address( current ), scope: scope, selection: selection, point: point, destination: destination(), queue: queue.getState() }; }
		function sync() {
			if ( destroyed ) return Promise.resolve( { status: 'disposed' } );
			var settings = options.settings();
			return queue.update( { action: 'sync_checkout', destination: destination(), address_scope: scope, effective_address: address( current ), payment_method: settings.payment_method, insurance: settings.insurance, shipping_methods: settings.shipping_methods } );
		}
		return {
			getState: state,
			updateAddress: function( input, nextScope ) {
				var next = address( input );
				var changed = ! sameAddress( current, next ) || scope !== nextScope;
				if ( ! changed ) return false;
				if ( current.postcode !== next.postcode || current.country !== next.country || scope !== nextScope ) selection = null;
				current = next; scope = nextScope; point = null;
				listeners( state() ); sync(); return true;
			},
			selectDistrict: function( next ) { selection = next && /^[1-9][0-9]*$/.test( String( next.id ) ) && next.label ? { id: String( next.id ), label: String( next.label ) } : null; point = null; listeners( state() ); return sync(); },
			selectPoint: function( next, expectedAddress ) {
				if ( current.country !== 'ID' || ! selection || ( expectedAddress && ! sameAddress( expectedAddress, current ) ) ) return false;
				point = next ? normalizePoint( next.latitude, next.longitude ) : null;
				listeners( state() ); sync(); return true;
			},
			sync: sync,
			retry: function() { return queue.retry(); },
			flush: function() { queue.resume(); },
			dispose: function() { destroyed = true; queue.dispose(); }
		};
	}
	root.kiriofClassicCheckoutCore = { create: create, address: address, sameAddress: sameAddress };
}( window ) );
