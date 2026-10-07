( function( root ) {
	'use strict';

	// Keep Woo's opaque identifiers exact. Never trim or sanitize into another ID.
	function identifier( value, packageId ) {
		if ( packageId && 'number' === typeof value ) {
			return Number.isSafeInteger( value ) && value >= 0 ? String( value ) : null;
		}
		if ( 'string' !== typeof value || ! value.length || value.length > 256 ) { return null; }
		for ( var index = 0; index < value.length; index++ ) {
			var code = value.charCodeAt( index );
			if ( code <= 31 || ( code >= 127 && code <= 159 ) ) { return null; }
		}
		return value;
	}

	function normalize( packages ) {
		var result = [];
		var seen = Object.create( null );
		if ( ! Array.isArray( packages ) || ! packages.length ) { return null; }
		for ( var index = 0; index < packages.length; index++ ) {
			var entry = packages[ index ];
			var packageId = entry && identifier( entry.package_id, true );
			var rateId = entry && identifier( entry.rate_id, false );
			if ( null === packageId || null === rateId || ! entry || seen[ packageId ] ) { return null; }
			seen[ packageId ] = true;
			result.push( { package_id: packageId, rate_id: rateId } );
		}
		return result;
	}

	function copy( packages ) {
		return packages.map( function( entry ) {
			return { package_id: entry.package_id, rate_id: entry.rate_id };
		} );
	}

	function equal( reviewed, packages ) {
		if ( ! reviewed.length || ! packages || reviewed.length !== packages.length ) { return false; }
		return reviewed.every( function( entry ) {
			return packages.some( function( candidate ) {
				return entry.package_id === candidate.package_id && entry.rate_id === candidate.rate_id;
			} );
		} );
	}

	/**
	 * Pure buyer intent, not a quote cache. Adapters supply complete selected
	 * package/rate pairs, never prices or labels. Seed only after a stable initial
	 * render. Reconcile on native updates; review/choose only on user interaction.
	 * Missing quotes and checkout errors must not clear the previous review.
	 */
	function create( options ) {
		var reviewed = [];
		var current = null;
		var seeded = false;
		var onChange = options && 'function' === typeof options.onChange ? options.onChange : function() {};

		function snapshot() {
			return { version: 1, packages: copy( reviewed ) };
		}

		function accept( packages ) {
			var changed = ! equal( reviewed, packages );
			reviewed = copy( packages );
			current = copy( packages );
			seeded = true;
			if ( changed ) { onChange( snapshot() ); }
			return true;
		}

		function seed( packages ) {
			var normalized = normalize( packages );
			if ( seeded || ! normalized ) { return false; }
			return accept( normalized );
		}

		function review( packages ) {
			var normalized = normalize( packages );
			return normalized ? accept( normalized ) : false;
		}

		function reconcile( packages ) {
			current = normalize( packages );
			return equal( reviewed, current );
		}

		/**
		 * Pass the complete next selected set from the native user event, or first
		 * reconcile it. The exact chosen pair must be present; no synthetic rate
		 * is trusted. An explicit choice reviews the whole visible set, including
		 * intentional package additions/removals, without a second confirmation.
		 */
		function choose( packageId, rateId, packages ) {
			var id = identifier( packageId, true );
			var rate = identifier( rateId, false );
			var next = undefined === packages ? current : normalize( packages );
			if ( null === id || null === rate || ! next || ! next.some( function( entry ) {
				return entry.package_id === id && entry.rate_id === rate;
			} ) ) { return false; }
			return accept( next );
		}

		return {
			seed: seed,
			choose: choose,
			review: review,
			reconcile: reconcile,
			snapshot: snapshot,
			matches: function( packages ) { return equal( reviewed, normalize( packages ) ); }
		};
	}

	root.kiriofShippingSelection = { create: create };
}( 'undefined' !== typeof window ? window : globalThis ) );
