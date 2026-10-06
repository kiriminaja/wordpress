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
		var panel = root.document.createElement( 'section' ); panel.className = 'kiriof-classic-pin form-row form-row-wide';
		var title = root.document.createElement( 'label' ); title.textContent = strings.mapTitle || 'Delivery pin'; title.htmlFor = 'kiriof-classic-map';
		var viewport = root.document.createElement( 'div' ); viewport.className = 'kiriof-classic-map-viewport';
		var canvas = root.document.createElement( 'div' ); canvas.id = 'kiriof-classic-map'; canvas.className = 'kiriof-classic-map'; canvas.tabIndex = 0; canvas.setAttribute( 'aria-label', strings.mapHelp || 'Delivery location map' );
		var locate = button( strings.mapLocate || 'Current location' ); locate.className += ' kiriof-classic-map-locate';
		var status = root.document.createElement( 'p' ); status.setAttribute( 'role', 'status' );
		var retry = button( strings.retry || 'Retry' ); retry.hidden = true;
		var badge = root.document.createElement( 'span' ); badge.className = 'kiriof-classic-pin-state'; badge.setAttribute('role','status');
		locate.textContent = ''; locate.setAttribute('aria-label',strings.mapLocate || 'Current location'); locate.title = strings.mapLocate || 'Current location'; locate.append(icon('M12 3v3m0 12v3M3 12h3m12 0h3M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8'));
		viewport.append( canvas, locate, badge ); panel.append( title, viewport, status, retry );
		var hidden = root.document.createElement( 'input' ); hidden.type = 'hidden'; hidden.name = 'kiriof_buyer_destination_snapshot'; form.append( hidden );
		var busy = false, disposed = false, map, gate, mapKey = '', lastDistrict = '', timer, point = null;
		function button( text ) { var node = root.document.createElement( 'button' ); node.type = 'button'; node.className = 'button'; node.textContent = text; return node; }
		function field( id ) { return form.querySelector( '#' + id ); }
		function icon(path) { var svg=root.document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('width','20');svg.setAttribute('height','20');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','2');svg.setAttribute('aria-hidden','true');var p=root.document.createElementNS('http://www.w3.org/2000/svg','path');p.setAttribute('d',path);svg.append(p);return svg; }
		function position() {
			var input=field(scope()==='shipping'?'kiriof_shipping_destination_area':'kiriof_destination_area');
			var row=input && (input.closest('.form-row') || field(input.id+'_field'));
			if(row && (panel.parentNode!==row.parentNode || row.nextElementSibling!==panel)) row.insertAdjacentElement('afterend',panel);
			if(row) {var rawPriority=row.getAttribute('data-priority');var priority=rawPriority===null?61:Number(rawPriority);panel.setAttribute('data-priority',String(Number.isFinite(priority)?priority+0.5:61.5));}
		}
		function contact() {
			var email=field('billing_email');var row=email && (email.closest('.form-row') || field('billing_email_field'));
			var billing=form.querySelector('.woocommerce-billing-fields');var heading=billing && billing.querySelector('h3');
			if(!row || !billing || !heading)return;
			var block=form.querySelector('.kiriof-classic-contact');
			if(!block){block=root.document.createElement('section');block.className='kiriof-classic-contact';var h=root.document.createElement('h3');h.textContent=strings.contactInformation || 'Contact Information';block.append(h);billing.insertBefore(block,heading);}
			if(row.parentNode!==block)block.append(row);
		}
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
			position(); contact();
			panel.hidden = config.needsShipping === false || state.address.country !== 'ID' || Boolean( collection() );
			retry.hidden = ! state.queue.error;
			var checked=Boolean(state.point && !state.queue.pending && !state.queue.inFlight && !state.queue.error);
			badge.replaceChildren(icon(checked?'m5 12 4 4L19 6':'m7 7 10 10M17 7 7 17'),root.document.createTextNode(checked?(strings.pinLocation || 'Pin Location'):(strings.needPinLocation || 'Need Pin Location')));badge.classList.toggle('is-complete',checked);
			status.textContent = state.queue.error ? strings.lookupFailed : state.queue.inFlight || state.queue.pending ? strings.checkingDistrict : '';
			status.hidden=!status.textContent;
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
				map = maps.createMapSession( { node: canvas, leaflet: root.L, tiles: config.map.tiles, attribution: config.map.attribution, coverage: config.map.coverage, initial: state.point, geolocation: root.navigator.geolocation, onSelect: function( next ) { point = next; return district() ? controller.selectPoint(next,expected) : true; }, onError: function() { status.hidden=false;status.textContent = strings.mapUnavailable; } } );
				if ( device && ! state.point ) map.pick(device.latitude,device.longitude,true);
			}
			if ( state.point ) { show(null); return; }
			canvas.hidden = true;
			gate = maps.createLocationGate( { geolocation: root.navigator.geolocation, onSuccess: show, onError: function(error) { if (!disposed && core.sameAddress(expected,address())) {status.hidden=false;status.textContent = error === 'permission' ? strings.mapPermission : strings.mapLocationFailed;} } } ); gate.start();
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
		$(root.document.body).on('country_to_state_changed.kiriofClassicPin init_checkout.kiriofClassicPin',function(){position();contact();});
		// Woo/theme scripts sort native form rows and may replace address wrappers.
		// Repair placement idempotently; do not rebuild the map or its selection.
		var placementObserver = new root.MutationObserver(function(){if(!disposed){position();contact();}});
		placementObserver.observe(form,{childList:true,subtree:true});
		$(form).on('checkout_place_order.kiriofClassicPin',function(){changed(); var state=controller.getState(); var instant=form.querySelector('input.shipping_method:checked[value^="kiriminaja-instant:"]'); if(instant && (!state.point || state.queue.pending || state.queue.inFlight || state.queue.error)) {status.textContent=strings.pinRequirement;return false;} return true;});
		locate.addEventListener('click',function(){if(map)map.locate();else {if(gate)gate.dispose();openMap(controller.getState());}});
		retry.addEventListener('click',function(){controller.retry();});
		root.addEventListener('pagehide',function(){disposed=true;placementObserver.disconnect();controller.dispose();disposeMap();root.clearTimeout(timer);$(form).off('.kiriofClassicPin');$(root.document.body).off('.kiriofClassicPin');});
		var current = district(); lastDistrict = current ? current.id : '';
		if( current && controller.getState().selection && current.id !== controller.getState().selection.id ) controller.selectDistrict(current);
		else if(current && !controller.getState().selection) controller.selectDistrict(current);
		else if(!current)controller.selectDistrict(null);
		render(controller.getState()); if(controller.getState().point)controller.sync();
	});
}(window));
