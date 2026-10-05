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
		var panel = root.document.createElement( 'section' ); panel.className = 'kiriof-classic-pin'; panel.setAttribute( 'data-priority', '999' );
		var title = root.document.createElement( 'h3' ); title.textContent = strings.mapTitle || 'Delivery pin';
		var help = root.document.createElement( 'p' ); help.textContent = strings.mapDeviceNotice || 'Check that the pin matches the delivery address.';
		var viewport = root.document.createElement( 'div' ); viewport.className = 'kiriof-classic-map-viewport';
		var canvas = root.document.createElement( 'div' ); canvas.className = 'kiriof-classic-map'; canvas.tabIndex = 0; canvas.setAttribute( 'aria-label', strings.mapHelp || 'Delivery location map' );
		var locate = button( strings.mapLocate || 'Current location' ); locate.className += ' kiriof-classic-map-locate';
		var status = root.document.createElement( 'p' ); status.setAttribute( 'role', 'status' );
		var retry = button( strings.retry || 'Retry' ); retry.hidden = true;
		viewport.append( canvas, locate ); panel.append( title, help, viewport, status, retry );
		var hidden = root.document.createElement( 'input' ); hidden.type = 'hidden'; hidden.name = 'kiriof_buyer_destination_snapshot'; form.append( hidden );
		var busy = false, disposed = false, map, gate, mapKey = '', lastDistrict = '', timer, point = null;
		function button( text ) { var node = root.document.createElement( 'button' ); node.type = 'button'; node.className = 'button'; node.textContent = text; return node; }
		function field( id ) { return form.querySelector( '#' + id ); }
		function scope() { var checkbox = field( 'ship-to-different-address-checkbox' ) || form.querySelector( '[name="ship_to_different_address"]' ); return checkbox && checkbox.checked ? 'shipping' : 'billing'; }
		function address() { var value = {}; [ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ].forEach( function( name ) { var node = field( scope() + '_' + name ); value[name] = node ? node.value : ''; } ); return core.address( value ); }
		function district() {
			var id = scope() === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			var node = field( id ); var label = field( id + '_name' );
			var text = label ? label.value : node && node.selectedOptions[0] ? node.selectedOptions[0].text : '';
			return node && /^[1-9][0-9]*$/.test( node.value ) && text ? { id: node.value, label: text } : null;
		}
		function collection() { var inputs = Array.from( form.querySelectorAll( 'input.shipping_method:checked, input.shipping_method[type="hidden"]' ) ); return inputs.length && inputs.every( function( input ) { return /^(local_pickup|pickup_location)/.test( input.value ); } ); }
		var controller = core.create( { address: address(), scope: scope(), savedDestination: config.savedDestination, settings: function() { return {}; }, isBlocked: function() { return busy; }, send: function( snapshot ) {
			var data = { action: 'sync_classic_pin', address_scope: snapshot.address_scope, effective_address: snapshot.effective_address, destination: snapshot.destination };
			// Server mutations are serialized, not aborted. This route never writes
			// the existing district, payment, insurance or shipping-method selection.
			return root.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams( { action: 'kiriof-session-save', nonce: config.nonce, data: JSON.stringify(data) } ).toString() } ).then( function( response ) { if ( ! response.ok ) throw new Error(); return response.json(); } ).then( function( response ) { if ( ! response.success ) throw new Error(); if ( ! disposed ) $( root.document.body ).trigger( 'update_checkout' ); } );
		}, onChange: render } );
		function render( state ) {
			if ( disposed ) return;
			hidden.value = JSON.stringify( state.destination );
			var anchor = form.querySelector( scope() === 'shipping' ? '.woocommerce-shipping-fields__field-wrapper' : '.woocommerce-billing-fields__field-wrapper' ) || form;
			if ( panel.parentNode !== anchor ) anchor.append( panel );
			panel.hidden = config.needsShipping === false || state.address.country !== 'ID' || Boolean( collection() );
			retry.hidden = ! state.queue.error;
			status.textContent = state.queue.error ? strings.lookupFailed : state.queue.inFlight || state.queue.pending ? strings.checkingDistrict : state.point ? strings.mapPlaced : strings.pinRequirement;
			if ( panel.hidden ) { disposeMap(); mapKey = ''; return; }
			var key = state.scope + JSON.stringify( state.address );
			if ( key !== mapKey ) { disposeMap(); mapKey = key; openMap( state ); }
		}
		function disposeMap() { if ( gate ) gate.dispose(); gate = null; if ( map ) map.dispose(); map = null; point = null; }
		function openMap( state ) {
			if ( ! config.map || ! config.map.enabled || ! state.address.address_1 || ! state.address.postcode ) return;
			var expected = state.address;
			function show( device ) {
				if ( disposed || panel.hidden || ! core.sameAddress(expected,address()) ) return;
				canvas.hidden = false;
				map = maps.createMapSession( { node: canvas, leaflet: root.L, tiles: config.map.tiles, attribution: config.map.attribution, coverage: config.map.coverage, initial: state.point, geolocation: root.navigator.geolocation, onSelect: function( next ) { point = next; return district() ? controller.selectPoint(next,expected) : true; }, onError: function() { status.textContent = strings.mapUnavailable; } } );
				if ( device && ! state.point ) map.pick(device.latitude,device.longitude,true);
			}
			if ( state.point ) { show(null); return; }
			canvas.hidden = true;
			gate = maps.createLocationGate( { geolocation: root.navigator.geolocation, onSuccess: show, onError: function(error) { if (!disposed && core.sameAddress(expected,address())) status.textContent = error === 'permission' ? strings.mapPermission : strings.mapLocationFailed; } } ); gate.start();
		}
		function changed() {
			controller.updateAddress(address(),scope());
			var selected = district(); var id = selected ? selected.id : '';
			if ( id !== lastDistrict || ( selected && ! controller.getState().selection ) ) { lastDistrict = id; controller.selectDistrict(selected); if ( point && selected ) controller.selectPoint(point,address()); }
			render(controller.getState());
		}
		$(form).on('input.kiriofClassicPin change.kiriofClassicPin','input, select',function(event) {
			if(event.target === hidden) return;
			root.clearTimeout(timer); timer=root.setTimeout(changed,200);
		});
		$(root.document.body).on('update_checkout.kiriofClassicPin',function(){busy=true;}).on('updated_checkout.kiriofClassicPin checkout_error.kiriofClassicPin kiriof:classic-district-synced.kiriofClassicPin',function(event){busy=false;changed(); if(event.type === 'kiriof:classic-district-synced' && controller.getState().queue.error)controller.retry(); else controller.flush();});
		$(form).on('checkout_place_order.kiriofClassicPin',function(){changed(); var state=controller.getState(); var instant=form.querySelector('input.shipping_method:checked[value^="kiriminaja-instant:"]'); if(instant && (!state.point || state.queue.pending || state.queue.inFlight || state.queue.error)) {status.textContent=strings.pinRequirement;return false;} return true;});
		locate.addEventListener('click',function(){if(map)map.locate();else {if(gate)gate.dispose();openMap(controller.getState());}});
		retry.addEventListener('click',function(){controller.retry();});
		root.addEventListener('pagehide',function(){disposed=true;controller.dispose();disposeMap();root.clearTimeout(timer);$(form).off('.kiriofClassicPin');$(root.document.body).off('.kiriofClassicPin');});
		var current = district(); lastDistrict = current ? current.id : '';
		if( current && controller.getState().selection && current.id !== controller.getState().selection.id ) controller.selectDistrict(current);
		else if(current && !controller.getState().selection) controller.selectDistrict(current);
		else if(!current)controller.selectDistrict(null);
		render(controller.getState()); if(controller.getState().point)controller.sync();
	});
}(window));
