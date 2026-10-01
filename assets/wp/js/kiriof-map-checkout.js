( function( root ) {
	'use strict';

	function coordinate( value, limit ) {
		if ( ( 'string' !== typeof value && 'number' !== typeof value ) || '' === String( value ).trim() ) { return null; }
		var number = Number( value );
		return Number.isFinite( number ) && Math.abs( number ) <= limit ? number.toFixed( 7 ) : null;
	}
	function normalizePoint( latitude, longitude ) {
		var lat = coordinate( latitude, 90 );
		var lng = coordinate( longitude, 180 );
		return null === lat || null === lng ? null : { latitude: lat, longitude: lng };
	}

	/** Owns one Leaflet container; defaultCenter is a view, never a selected pin. */
	function createMapSession( options ) {
		var L = options.leaflet;
		var map = null;
		var moving = false;
		var ignoreMove = false;
		var selected = null;
		var disposed = false;
		var locationSequence = 0;
		var timer;
		var observer;
		var schedule = options.schedule || root.setTimeout.bind( root );
		var cancel = options.cancel || root.clearTimeout.bind( root );
		function report( message ) { if ( ! disposed && options.onError ) { options.onError( message ); } }
		function show( point ) {
			ignoreMove = true;
			try { map.setView( [ Number( point.latitude ), Number( point.longitude ) ], 16, { animate: false } ); }
			finally { ignoreMove = false; }
			if ( moving ) { moving = false; if ( options.onMove ) { options.onMove( false ); } }
		}

		function pick( latitude, longitude, pan ) {
			if ( disposed || ! map ) { return false; }
			var point = normalizePoint( latitude, longitude );
			if ( ! point ) { report( 'invalid' ); return false; }
			locationSequence++;
			if ( selected && selected.latitude === point.latitude && selected.longitude === point.longitude ) { if ( pan ) { show( point ); } return true; }
			if ( options.onSelect && false === options.onSelect( point ) ) { return false; }
			selected = point;
			if ( pan ) { show( point ); }
			return true;
		}
		try {
			if ( ! options.node || ! L || ! String( options.tiles || '' ).startsWith( 'https://' ) ) { throw new Error( 'Map unavailable' ); }
			map = L.map( options.node, { scrollWheelZoom: false } ).setView( options.defaultCenter || [ -6.2088, 106.8456 ], 13 );
			var tiles = L.tileLayer( options.tiles, { maxZoom: 19, attribution: options.attribution } ).addTo( map );
			tiles.on( 'tileerror', function() { report( 'unavailable' ); } );
			map.on( 'click', function( event ) { pick( event.latlng.lat, event.latlng.lng, true ); } );
			map.on( 'movestart', function() {
				if ( disposed || ignoreMove ) { return; }
				moving = true; locationSequence++;
				if ( options.onMove ) { options.onMove( true ); }
			} );
			map.on( 'moveend', function() {
				if ( disposed || ignoreMove || ! moving ) { return; }
				moving = false;
				if ( options.onMove ) { options.onMove( false ); }
				var center = map.getCenter();
				pick( center.lat, center.lng );
			} );
			map.on( 'keydown', function( event ) {
				if ( event.originalEvent && event.originalEvent.key === 'Enter' ) {
					var center = map.getCenter();
					pick( center.lat, center.lng );
				}
			} );
			if ( options.initial ) {
				selected = normalizePoint( options.initial.latitude, options.initial.longitude );
				if ( selected ) { show( selected ); }
			}
			timer = schedule( function() { if ( ! disposed ) { map.invalidateSize( { pan: false } ); } }, 150 );
			if ( root.ResizeObserver ) {
				observer = new root.ResizeObserver( function() { if ( ! disposed ) { map.invalidateSize( { pan: false } ); } } );
				observer.observe( options.node );
			}
		} catch {
			if ( map ) { map.remove(); map = null; }
			report( 'unavailable' );
		}
		return {
			pick: pick,
			clear: function() {
				if ( disposed ) { return; }
				locationSequence++;
				if ( options.onSelect && false === options.onSelect( null ) ) { return; }
				selected = null;
			},
			locate: function() {
				if ( disposed || ! map ) { return; }
				var sequence = ++locationSequence;
				var geolocation = options.geolocation;
				if ( ! geolocation ) { report( 'permission' ); return; }
				geolocation.getCurrentPosition( function( position ) {
					if ( ! disposed && sequence === locationSequence ) { pick( position.coords.latitude, position.coords.longitude, true ); }
				}, function( error ) {
					if ( ! disposed && sequence === locationSequence ) { report( error.code === 1 ? 'permission' : 'location' ); }
				}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 } );
			},
			getPoint: function() { return selected ? Object.assign( {}, selected ) : null; },
			isAvailable: function() { return Boolean( map && ! disposed ); },
			dispose: function() {
				if ( disposed ) { return; }
				disposed = true;
				locationSequence++;
				cancel( timer );
				if ( observer ) { observer.disconnect(); }
				if ( map ) { map.remove(); map = null; }
			}
		};
	}

	root.kiriofMapCheckout = { createMapSession: createMapSession, normalizePoint: normalizePoint };
	var wp = root.wp;
	var wc = root.wc;
	var settings = wc && wc.wcSettings;
	var integration = settings && settings.getSetting ? settings.getSetting( 'kiriminaja-official-buyer_data', {} ) : {};
	var config = root.kiriofMapCheckoutConfig || integration.map || {};
	var blocks = wc && wc.blocksCheckout;
	if ( ! config.enabled || ! wp || ! wp.element || ! wp.data || ! wp.data.useSelect || ! blocks || ! blocks.registerCheckoutBlock ) { return; }
	var h = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var strings = config.i18n || {};
	function addressSnapshot( address ) {
		var snapshot = {};
		[ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ].forEach( function( field ) { snapshot[ field ] = String( address[ field ] || '' ).trim(); } );
		snapshot.postcode = snapshot.postcode.replace( /\s+/g, '' ).toUpperCase();
		snapshot.country = snapshot.country.toUpperCase();
		return snapshot;
	}
	function MapControl() {
		var data = wp.data.useSelect( function( select ) {
			var checkout = select( 'wc/store/checkout' );
			return { cart: select( 'wc/store/cart' ).getCartData(), collection: checkout.prefersCollection ? checkout.prefersCollection() : false };
		}, [] );
		var cart = data.cart || {};
		var address = addressSnapshot( cart.shippingAddress || {} );
		var addressKey = JSON.stringify( address );
		var visible = Boolean( cart.needsShipping && address.country === 'ID' && ! data.collection );
		var node = useRef( null );
		var session = useRef( null );
		var latest = useRef( { address: address, key: addressKey } );
		latest.current = { address: address, key: addressKey };
		var pointState = useState( null );
		var point = pointState[ 0 ];
		var setPoint = pointState[ 1 ];
		var movingState = useState( false );
		var moving = movingState[ 0 ];
		var setMoving = movingState[ 1 ];
		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];
		var selected = point && point.key === addressKey ? point : null;
		function apply( next, snapshot, key ) {
			var buyer = root.kiriofBuyerCheckout;
			if ( latest.current.key !== key || ! buyer || ! buyer.setCoordinates || ! buyer.setCoordinates( snapshot, next ) ) { return false; }
			setPoint( next ? Object.assign( { key: key }, next ) : null );
			setError( '' );
			return true;
		}
		useEffect( function() {
			setPoint( null ); setError( '' ); setMoving( false );
			if ( ! visible || ! node.current ) { return; }
			var initial = root.kiriofBuyerCheckout && root.kiriofBuyerCheckout.getCoordinates( address );
			if ( initial ) { setPoint( Object.assign( { key: addressKey }, initial ) ); }
			var mapSession = createMapSession( {
				leaflet: root.L, node: node.current, defaultCenter: config.defaultCenter,
				tiles: config.tiles, attribution: config.attribution, initial: initial, label: strings.mapTitle,
				geolocation: root.navigator && root.navigator.geolocation,
				onSelect: function( next ) { return apply( next, address, addressKey ); },
				onMove: setMoving,
				onError: function( code ) { setError( code === 'invalid' ? strings.mapInvalid : ( code === 'permission' ? strings.mapPermission : ( code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable ) ) ); }
			} );
			session.current = mapSession;
			return function() { mapSession.dispose(); if ( session.current === mapSession ) { session.current = null; } };
		}, [ addressKey, visible ] );
		if ( ! visible ) { return null; }
		return h( 'section', { className: 'kiriof-buyer-map', 'aria-label': strings.mapTitle },
			h( 'h3', { className: 'kiriof-buyer-map__title' }, strings.mapTitle ),
			h( 'p', null, strings.mapOptional ),
			h( 'div', { className: 'kiriof-buyer-map__viewport' + ( moving ? ' is-moving' : '' ) },
				h( 'div', { className: 'kiriof-buyer-map__canvas', ref: node, 'aria-label': strings.mapHelp } ),
				h( 'div', { className: 'kiriof-buyer-map__indicator', 'aria-hidden': 'true' },
					h( 'svg', { viewBox: '0 0 32 44', width: 32, height: 44, focusable: 'false' },
						h( 'path', { d: 'M16 1C7.7 1 1 7.7 1 16c0 11 15 26 15 26s15-15 15-26C31 7.7 24.3 1 16 1Z', fill: 'currentColor', stroke: '#fff', strokeWidth: 2 } ),
						h( 'circle', { cx: 16, cy: 16, r: 5, fill: '#fff' } ) ) ),
				h( 'button', { type: 'button', className: 'kiriof-buyer-map__locate', onClick: function() { if ( session.current ) { session.current.locate(); } } }, strings.mapLocate ) ),
			selected ? h( 'button', { type: 'button', className: 'kiriof-buyer-map__clear', onClick: function() { if ( session.current ) { session.current.clear(); } } }, strings.mapClear ) : null,
			h( 'p', { role: 'status', 'aria-live': 'polite' }, error || ( moving ? strings.mapMoving : ( selected ? strings.mapPlaced : strings.mapHelp ) ) ) );

	}
	blocks.registerCheckoutBlock( {
		force: true,
		metadata: { name: 'kiriminaja-official/map-checkout', parent: [ 'woocommerce/checkout-shipping-address-block' ], attributes: { lock: { type: 'object', default: { remove: true, move: true } } } },
		component: MapControl
	} );
} )( typeof window !== 'undefined' ? window : globalThis );
