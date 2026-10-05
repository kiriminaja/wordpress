( function( root ) {
	'use strict';
	var config = root.kiriofClassicCheckoutConfig || {};
	var $ = root.jQuery;
	var core = root.kiriofClassicCheckoutCore;
	var maps = root.kiriofMapCheckout;
	if ( ! config.enabled || ! $ || ! core || ! maps ) return;
	$( function() {
		var form = root.document.querySelector( 'form.checkout' );
		if ( ! form || root.document.querySelector( '.wc-block-checkout' ) ) return;
		var strings = Object.assign( {}, config.i18n, config.map && config.map.i18n );
		var panel = root.document.createElement( 'section' );
		panel.className = 'kiriof-classic-destination';
		panel.setAttribute( 'data-priority', '999' );
		panel.setAttribute( 'aria-label', strings.district || 'District' );
		var label = root.document.createElement( 'label' ); label.textContent = strings.district || 'District'; label.htmlFor = 'kiriof-classic-district';
		var select = root.document.createElement( 'select' ); select.id = 'kiriof-classic-district'; select.setAttribute( 'aria-required', 'true' );
		var status = root.document.createElement( 'p' ); status.className = 'kiriof-classic-status'; status.setAttribute( 'role', 'status' );
		var retry = button( strings.retry || 'Retry' ); retry.hidden = true;
		var mapPanel = root.document.createElement( 'div' ); mapPanel.className = 'kiriof-classic-map-editor'; mapPanel.hidden = true;
		var mapTitle = root.document.createElement( 'h3' ); mapTitle.textContent = strings.mapTitle || 'Delivery pin';
		var help = root.document.createElement( 'p' ); help.textContent = strings.mapDeviceNotice || 'Check that the pin matches the delivery address.';
		var canvas = root.document.createElement( 'div' ); canvas.className = 'kiriof-classic-map'; canvas.tabIndex = 0; canvas.setAttribute( 'aria-label', strings.mapHelp || 'Delivery location map' );
		var locate = button( strings.mapLocate || 'Current location' );
		locate.classList.add( 'kiriof-classic-map-locate' );
		var mapViewport = root.document.createElement( 'div' ); mapViewport.className = 'kiriof-classic-map-viewport'; mapViewport.append( canvas, locate );
		mapPanel.append( mapTitle, help, mapViewport ); panel.append( label, select, retry, mapPanel, status );
		var hidden = root.document.createElement( 'input' ); hidden.type = 'hidden'; hidden.name = 'kiriof_buyer_destination_snapshot';
		form.append( hidden );
		var busy = false, destroyed = false, lookupVersion = 0, lookupKey = '', lookupAbort, lookupTimer, lookupDeadline, editTimer, mapSession, gate, mapAddress, mapScope, quoteTimer, mapAttemptKey = '', devicePoint = null;
		function button( text ) { var node = root.document.createElement( 'button' ); node.type = 'button'; node.className = 'button'; node.textContent = text; return node; }
		function field( id ) { return form.querySelector( '#' + id ); }
		function value( id ) { var node = field( id ); return node ? node.value : ''; }
		function scope() { var checkbox = field( 'ship-to-different-address-checkbox' ) || form.querySelector( '[name="ship_to_different_address"]' ); return checkbox && checkbox.checked ? 'shipping' : 'billing'; }
		function address() { var result = {}; var prefix = scope() + '_'; [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ].forEach( function( key ) { result[ key ] = value( prefix + key ); } ); return core.address( result ); }
		function settings() {
			var payment = form.querySelector( '[name="payment_method"]:checked' );
			var insurance = form.querySelector( '#kiriof_insurance, #kiriof_shipping_insurance' );
			return { payment_method: payment ? payment.value : '', insurance: config.globalInsurance || Boolean( insurance && insurance.checked ), shipping_methods: Array.from( form.querySelectorAll( 'input.shipping_method:checked, input.shipping_method[type="hidden"]' ) ).map( function( input ) { return input.value; } ) };
		}
		function collection() { var methods = settings().shipping_methods; return methods.length > 0 && methods.every( function( method ) { return method.indexOf( 'local_pickup' ) === 0 || method.indexOf( 'pickup_location' ) === 0; } ); }
		function ajax( data, signal ) {
			var body = new URLSearchParams( { action: 'kiriof-session-save', 'data[nonce]': config.nonce } );
			Object.keys( data ).forEach( function( key ) { body.set( 'data[' + key + ']', 'object' === typeof data[ key ] ? JSON.stringify( data[ key ] ) : 'boolean' === typeof data[ key ] ? ( data[ key ] ? '1' : '0' ) : String( data[ key ] ) ); } );
			return root.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString(), signal: signal } ).then( function( response ) { if ( ! response.ok ) throw new Error( 'Shipping update failed' ); return response.json(); } ).then( function( response ) { if ( ! response.success ) throw new Error( 'Shipping update failed' ); return response; } );
		}
		var controller = core.create( { address: address(), scope: scope(), savedDestination: config.savedDestination, settings: settings, isBlocked: function() { return busy; }, send: function( snapshot ) { return ajax( snapshot ).then( function( result ) { if ( ! destroyed ) $( root.document.body ).trigger( 'update_checkout' ); return result; } ); }, onChange: render } );
		function syncLegacy( state ) {
			hidden.value = JSON.stringify( state.destination );
			var prefix = scope() === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			var district = field( prefix ); var name = field( prefix + '_name' );
			if ( district ) {
				if ( state.selection && ! Array.from( district.options || [] ).some( function( option ) { return option.value === state.selection.id; } ) ) district.append( new Option( state.selection.label, state.selection.id ) );
				district.value = state.selection ? state.selection.id : '';
			}
			if ( name ) name.value = state.selection ? state.selection.label : '';
		}
		function mount() {
			var anchor = form.querySelector( scope() === 'shipping' ? '.woocommerce-shipping-fields__field-wrapper' : '.woocommerce-billing-fields__field-wrapper' ) || form.querySelector( '.woocommerce-billing-fields' ) || form;
			if ( panel.parentNode !== anchor || anchor.lastElementChild !== panel ) anchor.append( panel );
			[ 'kiriof_destination_area', 'kiriof_shipping_destination_area' ].forEach( function( id ) { var node = field( id + '_field' ); if ( node ) node.hidden = true; } );
		}
		function render( state ) {
			if ( destroyed ) return;
			mount(); syncLegacy( state );
			panel.hidden = config.needsShipping === false || state.address.country !== 'ID' || collection();
			if ( panel.hidden ) disposeMap();
			select.value = state.selection ? state.selection.id : '';
			var q = state.queue; retry.hidden = ! q.error;
			if ( q.error ) status.textContent = q.stalled ? strings.saveStalled : strings.lookupFailed;
			else if ( q.pending || q.inFlight ) status.textContent = strings.checkingDistrict || 'Updating delivery…';
			else status.textContent = state.selection ? ( state.point ? strings.mapPlaced : strings.pinRequirement ) : strings.districtRequired;
			if ( ( mapSession || gate ) && mapAddress && ( mapScope !== state.scope || ! core.sameAddress( mapAddress, state.address ) ) ) disposeMap();
			if ( ! panel.hidden ) autoMap( state );
		}
		function options( rows ) {
			select.replaceChildren( new Option( strings.selectDistrict || 'Select District', '' ) );
			rows.forEach( function( row ) { select.append( new Option( row.text, String( row.id ) ) ); } );
			select.disabled = false; render( controller.getState() );
		}
		function lookup() {
			var state = controller.getState(); var key = state.address.country + ':' + state.address.postcode;
			if ( key === lookupKey ) return;
			lookupKey = key; lookupVersion++; var version = lookupVersion;
			root.clearTimeout( lookupTimer ); root.clearTimeout( lookupDeadline ); if ( lookupAbort ) lookupAbort.abort();
			options( [] );
			if ( state.address.country !== 'ID' || state.address.postcode.length < 3 || panel.hidden ) return;
			select.disabled = true; status.textContent = strings.loading || 'Loading districts…';
			lookupTimer = root.setTimeout( function() {
				lookupAbort = new AbortController(); var signal = lookupAbort.signal;
				lookupDeadline = root.setTimeout( function() { lookupAbort.abort(); if ( ! destroyed && version === lookupVersion ) { select.disabled = false; retry.hidden = false; status.textContent = strings.lookupTimeout || 'District lookup timed out.'; } }, 10000 );
				var body = new URLSearchParams( { action: 'kiriminaja_subdistrict_search', nonce: config.nonce, term: state.address.postcode } );
				root.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString(), signal: signal } ).then( function( response ) { if ( ! response.ok ) throw new Error(); return response.json(); } ).then( function( response ) {
					if ( destroyed || signal.aborted || version !== lookupVersion ) return;
					if ( ! response.success || ! Array.isArray( response.data ) ) throw new Error();
					root.clearTimeout( lookupDeadline ); var rows = response.data.filter( function( row ) { return /^[1-9][0-9]*$/.test( String( row.id ) ) && 'string' === typeof row.text; } ); options( rows );
					var current = controller.getState().selection;
					if ( current && ! rows.some( function( row ) { return String( row.id ) === current.id; } ) ) controller.selectDistrict( null );
				}).catch( function() { if ( destroyed || signal.aborted || version !== lookupVersion ) return; root.clearTimeout( lookupDeadline ); select.disabled = false; retry.hidden = false; status.textContent = strings.lookupFailed || 'District lookup failed.'; } );
			}, 300 );
		}
		function changed() { controller.updateAddress( address(), scope() ); lookup(); }
		select.addEventListener( 'change', function() {
			var point = devicePoint; var expected = mapAddress;
			controller.selectDistrict( select.value ? { id: select.value, label: select.options[ select.selectedIndex ].text } : null );
			if ( point && expected && core.sameAddress( expected, controller.getState().address ) && select.value ) controller.selectPoint( point, expected );
		} );
		retry.addEventListener( 'click', function() { if ( controller.getState().queue.error ) controller.retry(); else { lookupKey = ''; lookup(); } } );
		function disposeMap() { if ( gate ) gate.dispose(); gate = null; if ( mapSession ) mapSession.dispose(); mapSession = null; devicePoint = null; mapPanel.hidden = true; }
		function showMap( device ) {
			var state = controller.getState(); mapAddress = state.address; mapScope = state.scope; mapPanel.hidden = false; canvas.hidden = false;
			mapSession = maps.createMapSession( { node: canvas, leaflet: root.L, tiles: config.map.tiles, attribution: config.map.attribution, coverage: config.map.coverage, initial: state.point, geolocation: root.navigator.geolocation,
				onSelect: function( point ) { devicePoint = point; return controller.getState().selection ? controller.selectPoint( point, mapAddress ) : true; },
				onError: function() { status.textContent = strings.mapUnavailable; },
				onCoverage: function( coverage ) { if ( coverage && ! coverage.inside ) status.textContent = strings.mapOutsideRadius; }
			} );
			if ( device && ! state.point ) mapSession.pick( device.latitude, device.longitude, true );
		}
		function autoMap( state ) {
			if ( mapSession || ! config.map || ! config.map.enabled || ! state.address.address_1 || ! state.address.postcode ) return;
			var key = state.scope + ':' + JSON.stringify( state.address );
			if ( key === mapAttemptKey ) return;
			mapAttemptKey = key; mapAddress = state.address; mapScope = state.scope;
			if ( state.point ) { showMap( null ); return; }
			var expected = state.address; status.textContent = strings.mapLocating;
			gate = maps.createLocationGate( { geolocation: root.navigator.geolocation, onSuccess: function( point ) {
				if ( ! destroyed && ! panel.hidden && core.sameAddress( expected, controller.getState().address ) ) { devicePoint = point; showMap( point ); }
			}, onError: function( error ) { if ( ! destroyed && core.sameAddress( expected, controller.getState().address ) ) { status.textContent = error === 'permission' ? strings.mapPermission : strings.mapLocationFailed; mapPanel.hidden = false; canvas.hidden = true; } } } ); gate.start();
		}
		locate.addEventListener( 'click', function() { if ( mapSession ) mapSession.locate(); else { if ( gate ) gate.dispose(); mapAttemptKey = ''; autoMap( controller.getState() ); } } );
		$( form ).on( 'input.kiriofClassic change.kiriofClassic', 'input, select', function( event ) {
			if ( event.target === select || event.target === hidden ) return;
			root.clearTimeout( editTimer ); editTimer = root.setTimeout( function() { changed(); render( controller.getState() ); controller.sync(); }, 200 );
		} );
		$( root.document.body ).on( 'update_checkout.kiriofClassic', function() { busy = true; } ).on( 'updated_checkout.kiriofClassic checkout_error.kiriofClassic', function() { busy = false; changed(); controller.flush(); render( controller.getState() ); scheduleQuote(); } );
		$( form ).on( 'checkout_place_order.kiriofClassic', function() {
			changed(); var state = controller.getState(); var q = state.queue;
			if ( config.needsShipping === false || collection() ) return true;
			var instant = settings().shipping_methods.some( function( method ) { return method.indexOf( 'kiriminaja-instant:' ) === 0; } );
			if ( instant && ( ! state.selection || ! state.point ) ) { status.textContent = strings.pinRequirement || 'A delivery pin is required for Instant.'; panel.scrollIntoView( { block: 'center' } ); return false; }
			if ( q.pending || q.inFlight || q.error || ( ! panel.hidden && ! state.selection ) ) { status.textContent = strings.districtRequired || 'Wait for the delivery update before placing your order.'; panel.scrollIntoView( { block: 'center' } ); return false; }
			return true;
		} );
		function scheduleQuote() {
			root.clearTimeout( quoteTimer );
			if ( form.querySelector( 'input.shipping_method:checked[value^="kiriminaja-instant"]' ) ) quoteTimer = root.setTimeout( function() { if ( ! destroyed && ! busy ) $( root.document.body ).trigger( 'update_checkout' ); }, 110000 );
		}
		root.addEventListener( 'pagehide', function() { destroyed = true; controller.dispose(); disposeMap(); lookupVersion++; if ( lookupAbort ) lookupAbort.abort(); [ lookupTimer, lookupDeadline, editTimer, quoteTimer ].forEach( root.clearTimeout ); $( form ).off( '.kiriofClassic' ); $( root.document.body ).off( '.kiriofClassic' ); } );
		var saved = controller.getState().selection; if ( saved ) options( [ { id: saved.id, text: saved.label } ] ); else options( [] );
		render( controller.getState() ); lookup(); controller.sync(); scheduleQuote();
	} );
}( window ) );
