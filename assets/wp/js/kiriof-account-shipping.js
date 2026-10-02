( function( root ) {
	'use strict';

	var fields = [ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
	function normalizeAddress( address ) {
		var snapshot = {};
		fields.forEach( function( field ) { snapshot[ field ] = String( address && address[ field ] || '' ).trim(); } );
		snapshot.postcode = snapshot.postcode.replace( /\s+/g, '' ).toUpperCase();
		snapshot.country = snapshot.country.toUpperCase();
		return snapshot;
	}
	function addressKey( address ) { return JSON.stringify( normalizeAddress( address ) ); }
	function lookupKey( address ) { return address.country + '|' + address.postcode; }
	function coordinate( value, limit ) {
		if ( ( 'number' !== typeof value && 'string' !== typeof value ) || ! /^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test( String( value ).trim() ) ) { return null; }
		var number = Number( value );
		return Number.isFinite( number ) && Math.abs( number ) <= limit ? number.toFixed( 7 ) : null;
	}

	function initialize( wrapper ) {
		var form = wrapper.closest( 'form' );
		var hidden = wrapper.querySelector( '[name="kiriof_account_destination"]' );
		var district = wrapper.querySelector( '#kiriof-account-district' );
		var districtStatus = wrapper.querySelector( '#kiriof-account-district-status' );
		var districtRetry = wrapper.querySelector( '#kiriof-account-district-retry' );
		var badges = wrapper.querySelector( '.kiriof-account-shipping-status' );
		if ( ! form || ! hidden || ! district ) { return; }
		var config = root.kiriofAccountShippingConfig || {};
		var strings = config.i18n || {};
		var mapConfig = config.map || {};
		var mapStrings = Object.assign( {}, strings, mapConfig.i18n || {} );
		var canvas = wrapper.querySelector( '.kiriof-buyer-map__canvas' );
		var viewport = wrapper.querySelector( '.kiriof-buyer-map__viewport' );
		var indicator = wrapper.querySelector( '.kiriof-buyer-map__indicator' );
		var locate = wrapper.querySelector( '.kiriof-buyer-map__locate' );
		var mapStatus = wrapper.querySelector( '.kiriof-buyer-map__status' );
		var coverageWarning = wrapper.querySelector( '.kiriof-buyer-map__coverage-warning' );
		var coverageLegend = wrapper.querySelector( '.kiriof-buyer-map__coverage-legend' );
		var mapSection = wrapper.querySelector( '.kiriof-buyer-map' );
		var disposed = false;
		var generation = 0;
		var timer;
		var networkTimer;
		var controller;
		var mapSession;
		var locationGate;
		var mapGeneration = 0;
		var selection = null;
		var pin = null;
		var options = [];
		var loading = false;
		var failed = false;
		function readAddress() {
			var address = {};
			fields.forEach( function( field ) {
				// WooCommerce replaces the state control when the country changes.
				var input = form.elements.namedItem( 'shipping_' + field );
				address[ field ] = input ? input.value : '';
			} );
			return normalizeAddress( address );
		}
		var address = readAddress();
		var saved;
		try { saved = hidden.value ? JSON.parse( hidden.value ) : config.savedDestination; } catch { saved = null; }
		if ( saved && lookupKey( normalizeAddress( saved ) ) === lookupKey( address ) && /^[1-9][0-9]*$/.test( String( saved.district_id ) ) ) {
			selection = { id: String( saved.district_id ), label: String( saved.district_label || '' ) };
			if ( 2 === saved.version && saved.shipping_address && addressKey( saved.shipping_address ) === addressKey( address ) && 'ID' === address.country ) {
				var latitude = coordinate( saved.destination_latitude, 90 );
				var longitude = coordinate( saved.destination_longitude, 180 );
				if ( null !== latitude && null !== longitude ) { pin = { latitude: latitude, longitude: longitude }; }
			}
		}
		function publish() {
			var value = {
				version: pin ? 2 : 1,
				district_id: selection ? selection.id : '',
				district_label: selection ? selection.label : '',
				postcode: address.postcode,
				country: address.country,
				address_type: 'shipping'
			};
			if ( pin ) {
				value.destination_latitude = pin.latitude;
				value.destination_longitude = pin.longitude;
				value.shipping_address = Object.assign( {}, address );
			}
			hidden.value = JSON.stringify( value );
			renderBadges();
			renderCoverage( getCoverageStatus( pin ) );
		}
		function getCoverageStatus( point ) {
			var api = root.kiriofMapCheckout;
			return api && 'function' === typeof api.coverageStatus ? api.coverageStatus( mapConfig.coverage, point ) : null;
		}
		function renderCoverage( status ) {
			if ( coverageWarning ) {
				coverageWarning.hidden = ! status || false !== status.inside;
				coverageWarning.setAttribute( 'role', 'note' );
				coverageWarning.setAttribute( 'aria-live', 'polite' );
				coverageWarning.textContent = coverageWarning.hidden ? '' : mapStrings.mapOutsideRadius || '';
			}
			if ( coverageLegend ) {
				coverageLegend.hidden = ! getCoverageStatus( mapConfig.coverage && mapConfig.coverage.origin );
				coverageLegend.setAttribute( 'role', 'note' );
				coverageLegend.textContent = coverageLegend.hidden ? '' : mapStrings.mapCoverage || '';
			}
		}
		function renderBadges() {
			if ( ! badges ) { return; }
			badges.replaceChildren();
			if ( 'ID' !== address.country ) { return; }
			var ready = selection && ! loading && ! failed;
			function badge( text, complete ) {
				var node = root.document.createElement( 'span' );
				node.className = 'kiriof-address-status__badge ' + ( complete ? 'is-complete' : 'is-warning' );
				node.textContent = ( complete ? '✓ ' : '⚠ ' ) + ( text || '' );
				node.title = strings.pinRequirement || '';
				badges.appendChild( node );
			}
			badges.className = 'kiriof-account-shipping-status kiriof-address-status';
			badges.setAttribute( 'role', 'status' );
			badges.setAttribute( 'aria-live', 'polite' );
			badges.setAttribute( 'aria-atomic', 'true' );
			if ( ! ready ) { badge( loading ? strings.checkingDistrict : strings.districtNotSet, false ); }
			badge( pin ? strings.pinLocation : strings.needPinLocation, Boolean( pin ) );
		}
		function renderDistrict() {
			renderBadges();
			var required = 'ID' === address.country;
			district.required = required;
			district.disabled = ! required || address.postcode.length < 3 || loading;
			if ( districtRetry ) { districtRetry.hidden = ! failed; }
			district.setAttribute( 'aria-required', String( required ) );
			district.setAttribute( 'aria-invalid', String( required && ( failed || ( ! loading && ! selection ) ) ) );
			district.replaceChildren();
			var placeholder = root.document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = strings.selectDistrict || '';
			district.appendChild( placeholder );
			options.forEach( function( row ) {
				var option = root.document.createElement( 'option' );
				option.value = row.id; option.textContent = row.label;
				district.appendChild( option );
			} );
			// Keep a remembered district visible while awaiting canonical confirmation.
			if ( selection && ( loading || failed ) && ! options.some( function( row ) { return row.id === selection.id; } ) ) {
				var remembered = root.document.createElement( 'option' );
				remembered.value = selection.id; remembered.textContent = selection.label;
				district.appendChild( remembered );
			}
			district.value = selection ? selection.id : '';
			if ( districtStatus ) {
				districtStatus.textContent = ! required ? '' : ( address.postcode.length < 3 ? strings.postcodeRequired : ( loading ? strings.loading : ( failed ? strings.lookupFailed : ( ! options.length ? strings.empty : ( selection ? '' : strings.districtRequired ) ) ) ) ) || '';
			}
		}
		function cancelLookup() {
			generation++;
			root.clearTimeout( timer );
			root.clearTimeout( networkTimer );
			if ( controller ) { controller.abort(); controller = null; }
		}
		function lookup() {
			cancelLookup();
			var currentGeneration = generation;
			var key = lookupKey( address );
			var postcode = address.postcode;
			options = []; failed = false;
			loading = 'ID' === address.country && postcode.length >= 3;
			renderDistrict();
			if ( ! loading ) { return; }
			controller = new root.AbortController();
			var requestController = controller;
			function current() { return ! disposed && currentGeneration === generation && key === lookupKey( readAddress() ) && ! requestController.signal.aborted; }
			function fail() {
				if ( ! current() ) { return; }
				root.clearTimeout( networkTimer );
				loading = false; failed = true; options = [];
				// Preserve posted identity on transport errors: only the server can validate it.
				renderDistrict();
			}
			timer = root.setTimeout( function() {
				if ( ! current() ) { return; }
				networkTimer = root.setTimeout( function() {
					if ( ! current() ) { return; }
					// Set failure before aborting: aborted promise handlers must remain inert.
					fail(); requestController.abort();
				}, 10000 );
				var body = new root.URLSearchParams( { action: 'kiriminaja_subdistrict_search', nonce: config.nonce || '', term: postcode } );
				root.fetch( config.ajaxUrl, {
					method: 'POST', credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString(), signal: requestController.signal
				} ).then( function( response ) {
					if ( ! response.ok ) { throw new Error( 'District lookup failed' ); }
					return response.json();
				} ).then( function( response ) {
					if ( ! current() ) { return; }
					if ( ! response.success || ! Array.isArray( response.data ) ) { throw new Error( 'District lookup failed' ); }
					root.clearTimeout( networkTimer );
					options = response.data.filter( function( row ) { return row && /^[1-9][0-9]*$/.test( String( row.id ) ) && 'string' === typeof row.text && row.text.trim(); } ).map( function( row ) { return { id: String( row.id ), label: row.text.trim() }; } );
					// A remembered identity is not trusted until this postcode lookup confirms it.
					selection = selection ? options.find( function( row ) { return row.id === selection.id; } ) || null : null;
					loading = false;
					renderDistrict(); publish();
				} ).catch( fail );
			}, 250 );
		}
		function renderMap() {
			renderCoverage( getCoverageStatus( pin ) );
			var currentGeneration = ++mapGeneration;
			if ( locationGate ) { locationGate.dispose(); locationGate = null; }
			if ( mapSession ) { mapSession.dispose(); mapSession = null; }
			if ( viewport ) { viewport.hidden = true; viewport.classList.remove( 'is-moving' ); }
			if ( indicator ) { indicator.hidden = ! pin; }
			if ( mapStatus ) { mapStatus.textContent = ''; }
			var visible = 'ID' === address.country && false !== mapConfig.enabled;
			if ( mapSection ) { mapSection.hidden = ! visible; }
			if ( locate ) { locate.disabled = true; }
			if ( ! visible ) { return; }
			var key = addressKey( address );
			function current() { return ! disposed && currentGeneration === mapGeneration && key === addressKey( readAddress() ); }
			function mapError( code ) {
				if ( ! current() ) { return; }
				if ( 'invalid' !== code ) {
					if ( viewport ) { viewport.hidden = true; }
					if ( locate ) { locate.disabled = true; }
				}
				if ( mapStatus ) { mapStatus.textContent = ( code === 'invalid' ? mapStrings.mapInvalid : ( code === 'permission' ? mapStrings.mapPermission : ( code === 'location' ? mapStrings.mapLocationFailed : mapStrings.mapUnavailable ) ) ) || ''; }
			}
			if ( ! canvas || ! root.kiriofMapCheckout || ! root.kiriofMapCheckout.createLocationGate ) { mapError( 'unavailable' ); return; }
			locationGate = root.kiriofMapCheckout.createLocationGate( {
				geolocation: root.navigator && root.navigator.geolocation,
				onError: mapError,
				onSuccess: function( devicePoint ) {
					if ( ! current() ) { return; }
					if ( viewport ) { viewport.hidden = false; }
					mapSession = root.kiriofMapCheckout.createMapSession( {
						leaflet: root.L, node: canvas, defaultCenter: [ Number( devicePoint.latitude ), Number( devicePoint.longitude ) ],
						tiles: mapConfig.tiles, attribution: mapConfig.attribution, initial: pin, coverage: mapConfig.coverage,
						geolocation: root.navigator && root.navigator.geolocation,
						onCoverage: function( status ) {
							if ( current() ) { renderCoverage( status ); }
						},
						onSelect: function( point ) {
							if ( ! current() ) { return false; }
							pin = point; publish();
							if ( indicator ) { indicator.hidden = ! pin; }
							if ( mapStatus ) { mapStatus.textContent = pin ? mapStrings.mapPlaced || '' : ''; }
							return true;
						},
						onMove: function( moving ) {
							if ( ! current() ) { return; }
							if ( viewport ) { viewport.classList.toggle( 'is-moving', moving ); }
							if ( indicator ) { indicator.hidden = ! moving && ! pin; }
							if ( mapStatus ) { mapStatus.textContent = ( moving ? mapStrings.mapMoving : ( pin ? mapStrings.mapPlaced : '' ) ) || ''; }
						},
						onError: mapError
					} );
					if ( ! mapSession.isAvailable() ) { return; }
					if ( ! pin ) { mapSession.pick( devicePoint.latitude, devicePoint.longitude, true ); }
					if ( mapStatus ) { mapStatus.textContent = pin ? mapStrings.mapPlaced || '' : ''; }
					if ( locate ) { locate.disabled = false; }
				}
			} );
			locationGate.start();
		}
		function synchronize() {
			var next = readAddress();
			if ( addressKey( next ) === addressKey( address ) ) { return; }
			var changedLookup = lookupKey( next ) !== lookupKey( address );
			address = next; pin = null;
			if ( changedLookup ) { selection = null; }
			publish(); renderMap(); lookup();
		}
		function onEdit( event ) {
			if ( disposed ) { return; }
			synchronize();
			if ( event.target === district && 'change' === event.type ) {
				if ( loading || failed || district.disabled || ! options.length ) { return; }
				selection = options.find( function( row ) { return row.id === district.value; } ) || null;
				publish(); renderDistrict();
			}
		}
		function onSubmit() { if ( ! disposed ) { synchronize(); publish(); } }
		function onRetry( event ) {
			event.preventDefault();
			if ( disposed || ! failed ) { return; }
			lookup();
		}
		function onLocate( event ) {
			event.preventDefault();
			if ( disposed || ! viewport || viewport.hidden || ! locate || locate.disabled ) { return; }
			synchronize();
			if ( mapSession && ! viewport.hidden && ! locate.disabled ) { mapSession.locate(); }
		}
		function dispose() {
			if ( disposed ) { return; }
			disposed = true; cancelLookup();
			if ( locationGate ) { locationGate.dispose(); locationGate = null; }
			if ( mapSession ) { mapSession.dispose(); mapSession = null; }
			form.removeEventListener( 'input', onEdit ); form.removeEventListener( 'change', onEdit ); form.removeEventListener( 'submit', onSubmit );
			if ( locate ) { locate.removeEventListener( 'click', onLocate ); }
			if ( districtRetry ) { districtRetry.removeEventListener( 'click', onRetry ); }
			root.removeEventListener( 'pagehide', dispose );
		}
		form.addEventListener( 'input', onEdit ); form.addEventListener( 'change', onEdit ); form.addEventListener( 'submit', onSubmit );
		if ( locate ) { locate.addEventListener( 'click', onLocate ); }
		if ( districtRetry ) { districtRetry.addEventListener( 'click', onRetry ); }
		root.addEventListener( 'pagehide', dispose );
		// Preserve matching saved pins even without location permission; discard stale pins.
		if ( saved && 2 === saved.version && ! pin ) { publish(); }
		renderMap(); lookup();
	}
	function ready() { root.document.querySelectorAll( '.kiriof-account-shipping' ).forEach( initialize ); }
	if ( root.document.readyState === 'loading' ) { root.document.addEventListener( 'DOMContentLoaded', ready, { once: true } ); }
	else { ready(); }
} )( window );
