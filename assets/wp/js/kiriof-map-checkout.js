( function( root ) {
	'use strict';

	function coordinate( value, limit ) {
		if ( ( 'string' !== typeof value && 'number' !== typeof value ) || '' === String( value ).trim() ) { return null; }
		var number = Number( value );
		return Number.isFinite( number ) && Math.abs( number ) <= limit ? number.toFixed( 7 ) : null;
	}

	/** Straight-line metres, not a driving route. Invalid points have unknown coverage. */
	function coverageDistance( origin, destination ) {
		if ( ! origin || ! destination || ! normalizePoint( origin.latitude, origin.longitude ) || ! normalizePoint( destination.latitude, destination.longitude ) ) { return null; }
		var radians = Math.PI / 180;
		var latitude = ( Number( destination.latitude ) - Number( origin.latitude ) ) * radians;
		var longitude = ( Number( destination.longitude ) - Number( origin.longitude ) ) * radians;
		var a = Math.sin( latitude / 2 ) ** 2 + Math.cos( Number( origin.latitude ) * radians ) * Math.cos( Number( destination.latitude ) * radians ) * Math.sin( longitude / 2 ) ** 2;
		return 6371000 * 2 * Math.asin( Math.sqrt( Math.min( 1, Math.max( 0, a ) ) ) );
	}
	function coverageStatus( coverage, point ) {
		if ( ! coverage || ( 'number' !== typeof coverage.radiusMeters && 'string' !== typeof coverage.radiusMeters ) || '' === String( coverage.radiusMeters ).trim() ) { return null; }
		var radius = Number( coverage.radiusMeters );
		var distance = coverageDistance( coverage.origin, point );
		return null === distance || ! Number.isFinite( radius ) || radius <= 0 ? null : { distanceMeters: distance, inside: distance <= radius + 0.001 };
	}

	/** One authorization request per editor lifecycle; disposal ignores browser callbacks. */
	function createLocationGate( options ) {
		var started = false;
		var disposed = false;
		var sequence = 0;
		return {
			start: function() {
				if ( started || disposed ) { return; }
				started = true;
				var request = ++sequence;
				function fail( code ) {
					if ( disposed || request !== sequence ) { return; }
					sequence++;
					if ( options.onError ) { options.onError( code ); }
				}
				var geolocation = options.geolocation;
				if ( ! geolocation || 'function' !== typeof geolocation.getCurrentPosition ) { fail( 'unavailable' ); return; }
				try {
					geolocation.getCurrentPosition( function( position ) {
						if ( disposed || request !== sequence ) { return; }
						var coords = position && position.coords;
						var point = coords && normalizePoint( coords.latitude, coords.longitude );
						if ( ! point ) { fail( 'location' ); return; }
						sequence++;
						if ( options.onSuccess ) { options.onSuccess( point ); }
					}, function( error ) { fail( error && error.code === 1 ? 'permission' : 'location' ); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 } );
				} catch { fail( 'unavailable' ); }
			},
			dispose: function() { disposed = true; sequence++; }
		};
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
		// Copy the origin so a caller mutating its config cannot move coverage silently.
		var coverage = options.coverage && { origin: options.coverage.origin && Object.assign( {}, options.coverage.origin ), radiusMeters: options.coverage.radiusMeters };
		var schedule = options.schedule || root.setTimeout.bind( root );
		var cancel = options.cancel || root.clearTimeout.bind( root );
		function report( message ) { if ( ! disposed && options.onError ) { options.onError( message ); } }
		function reportCoverage( point ) { if ( ! disposed && options.onCoverage ) { options.onCoverage( coverageStatus( coverage, point ) ); } }
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
			if ( selected && selected.latitude === point.latitude && selected.longitude === point.longitude ) { if ( pan ) { show( point ); } reportCoverage( point ); return true; }
			if ( options.onSelect && false === options.onSelect( point ) ) { return false; }
			selected = point;
			if ( pan ) { show( point ); }
			reportCoverage( point );
			return true;
		}
		try {
			if ( ! options.node || ! L || ! String( options.tiles || '' ).startsWith( 'https://' ) ) { throw new Error( 'Map unavailable' ); }
			map = L.map( options.node, { scrollWheelZoom: false } ).setView( options.defaultCenter || [ -6.2088, 106.8456 ], 13 );
			if ( coverageStatus( coverage, coverage && coverage.origin ) && 'function' === typeof L.circle ) {
				// A missing optional overlay must not disable Express pin selection.
				try { L.circle( [ Number( coverage.origin.latitude ), Number( coverage.origin.longitude ) ], { radius: Number( coverage.radiusMeters ), interactive: false, fill: false, fillOpacity: 0, color: '#64748b', weight: 2, opacity: 0.85, dashArray: '1 6', lineCap: 'round' } ).addTo( map ); } catch { /* Keep the unrestricted map usable. */ }
			}
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
			reportCoverage( selected );
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
				reportCoverage( null );
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

	root.kiriofMapCheckout = { createMapSession: createMapSession, createLocationGate: createLocationGate, normalizePoint: normalizePoint, coverageDistance: coverageDistance, coverageStatus: coverageStatus };
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
	// Resolve the optional hook once so later bridge availability cannot change hook order.
	var usePresentation = root.kiriofAddressPresentation && root.kiriofAddressPresentation.usePresentation;
	var strings = config.i18n || {};
	var buyerStrings = ( root.kiriofBuyerCheckoutConfig || integration ).i18n || {};
	function addressSnapshot( address ) {
		var snapshot = {};
		[ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ].forEach( function( field ) { snapshot[ field ] = String( address[ field ] || '' ).trim(); } );
		snapshot.postcode = snapshot.postcode.replace( /\s+/g, '' ).toUpperCase();
		snapshot.country = snapshot.country.toUpperCase();
		return snapshot;
	}
	function MapControl() {
		var presentation = usePresentation ? usePresentation() : { editing: true };
		var data = wp.data.useSelect( function( select ) {
			var checkout = select( 'wc/store/checkout' );
			return { cart: select( 'wc/store/cart' ).getCartData(), collection: checkout.prefersCollection ? checkout.prefersCollection() : false };
		}, [] );
		var cart = data.cart || {};
		var coverageExtension = cart.extensions && cart.extensions[ 'kiriminaja-official-instant-coverage' ];
		var coverage = coverageExtension && Object.prototype.hasOwnProperty.call( coverageExtension, 'coverage' ) ? coverageExtension.coverage : config.coverage;
		var coverageKey = JSON.stringify( coverage || null );
		var hasCoverage = Boolean( coverageStatus( coverage, coverage && coverage.origin ) );
		var address = addressSnapshot( cart.shippingAddress || {} );
		var addressKey = JSON.stringify( address );
		var visible = Boolean( config.enabled && presentation.editing && cart.needsShipping && address.country === 'ID' && ! data.collection );
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
		var coverageState = useState( null );
		var coverageResult = coverageState[ 0 ];
		var setCoverage = coverageState[ 1 ];
		var grantState = useState( null );
		var grant = grantState[ 0 ];
		var setGrant = grantState[ 1 ];
		var granted = visible && grant && grant.key === addressKey;
		var selected = point && point.key === addressKey ? point : null;
		function apply( next, snapshot, key ) {
			var buyer = root.kiriofBuyerCheckout;
			if ( latest.current.key !== key || ! buyer || ! buyer.setCoordinates || ! buyer.setCoordinates( snapshot, next ) ) { return false; }
			setPoint( next ? Object.assign( { key: key }, next ) : null );
			setError( '' );
			return true;
		}
		useEffect( function() {
			setGrant( null ); setPoint( null ); setError( '' ); setMoving( false ); setCoverage( null );
			if ( ! visible ) { return; }
			var gate = createLocationGate( {
				geolocation: root.navigator && root.navigator.geolocation,
				onSuccess: function( next ) { if ( latest.current.key === addressKey ) { setGrant( { key: addressKey, point: next } ); } },
				onError: function( code ) { setError( code === 'permission' ? strings.mapPermission : ( code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable ) ); }
			} );
			gate.start();
			return function() { gate.dispose(); };
		}, [ addressKey, visible ] );
		useEffect( function() {
			if ( ! granted || ! node.current ) { return; }
			var initial = root.kiriofBuyerCheckout && root.kiriofBuyerCheckout.getCoordinates( address );
			if ( initial ) { setPoint( Object.assign( { key: addressKey }, initial ) ); }
			var mapSession = createMapSession( {
				leaflet: root.L, node: node.current, defaultCenter: [ Number( grant.point.latitude ), Number( grant.point.longitude ) ],
				tiles: config.tiles, attribution: config.attribution, initial: initial, label: strings.mapTitle, coverage: coverage,
				onCoverage: function( status ) { setCoverage( { key: coverageKey, status: status } ); },
				geolocation: root.navigator && root.navigator.geolocation,
				onSelect: function( next ) { return apply( next, address, addressKey ); },
				onMove: setMoving,
				onError: function( code ) { setError( code === 'invalid' ? strings.mapInvalid : ( code === 'permission' ? strings.mapPermission : ( code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable ) ) ); }
			} );
			session.current = mapSession;
			if ( ! initial && mapSession.isAvailable() ) { mapSession.pick( grant.point.latitude, grant.point.longitude, true ); }
			return function() { mapSession.dispose(); if ( session.current === mapSession ) { session.current = null; } };
		}, [ addressKey, visible, grant, coverageKey ] );
		if ( ! visible ) { return null; }
		var status = moving ? '' : error || ( ! granted ? strings.mapLocating : '' );
		var outside = coverageResult && coverageResult.key === coverageKey ? coverageResult.status : coverageStatus( coverage, root.kiriofBuyerCheckout && root.kiriofBuyerCheckout.getCoordinates( address ) );
		return h( 'section', { className: 'kiriof-buyer-map', 'aria-label': strings.mapTitle },
			h( 'h3', { className: 'kiriof-buyer-map__title' }, strings.mapTitle ),
			granted ? h( 'div', { className: 'kiriof-buyer-map__viewport' + ( moving ? ' is-moving' : '' ) },
				h( 'div', { className: 'kiriof-buyer-map__canvas', ref: node, 'aria-label': strings.mapHelp, 'aria-description': strings.mapKeyboard } ),
				h( 'div', { className: 'kiriof-buyer-map__information', role: 'note', hidden: moving, tabIndex: 0, 'aria-label': strings.mapTitle },
					h( 'p', { className: 'kiriof-buyer-map__optional' }, strings.mapOptional ),
					hasCoverage ? h( 'p', { className: 'kiriof-buyer-map__coverage' }, strings.mapCoverage ) : null ),
				h( 'div', { className: 'kiriof-buyer-map__pin-status ' + ( selected ? 'is-complete' : 'is-warning' ), hidden: moving, role: 'status', 'aria-live': 'polite' },
					h( 'svg', { viewBox: '0 0 24 24', width: 18, height: 18, fill: 'none', stroke: 'currentColor', strokeWidth: 2, 'aria-hidden': 'true', focusable: 'false' },
						h( 'path', { d: selected ? 'm5 12 4 4 10-10' : 'M5 5h14v14H5Z' } ) ),
					selected ? ( strings.pinLocation || buyerStrings.pinLocation ) : ( strings.needPinLocation || buyerStrings.needPinLocation ) ),
				h( 'div', { className: 'kiriof-buyer-map__indicator', 'aria-hidden': 'true' },
					h( 'svg', { viewBox: '0 0 32 44', width: 32, height: 44, focusable: 'false' },
						h( 'path', { d: 'M16 1C7.7 1 1 7.7 1 16c0 11 15 28 15 28s15-17 15-28C31 7.7 24.3 1 16 1Z', fill: 'currentColor', stroke: '#fff', strokeWidth: 2 } ),
						h( 'circle', { cx: 16, cy: 16, r: 5, fill: '#fff' } ) ) ),
				h( 'button', { type: 'button', className: 'kiriof-buyer-map__locate', 'aria-label': strings.mapLocate, title: strings.mapLocate, onClick: function() { if ( session.current ) { session.current.locate(); } } },
					h( 'svg', { viewBox: '0 0 24 24', width: 22, height: 22, fill: 'none', stroke: 'currentColor', strokeWidth: 2, 'aria-hidden': 'true', focusable: 'false' },
						h( 'circle', { cx: 12, cy: 12, r: 7 } ),
						h( 'circle', { cx: 12, cy: 12, r: 2 } ),
						h( 'path', { d: 'M12 2v3 M12 19v3 M2 12h3 M19 12h3' } ) ) ) ) : null,
			status ? h( 'p', { className: 'kiriof-buyer-map__status', role: 'status', 'aria-live': 'polite' }, status ) : null,
			! moving && outside && ! outside.inside ? h( 'p', { className: 'kiriof-buyer-map__coverage-warning', role: 'note', 'aria-live': 'polite' }, strings.mapOutsideRadius ) : null );

	}
	blocks.registerCheckoutBlock( {
		force: true,
		metadata: { name: 'kiriminaja-official/map-checkout', parent: [ 'woocommerce/checkout-shipping-address-block' ], attributes: { lock: { type: 'object', default: { remove: true, move: true } } } },
		component: MapControl
	} );
} )( typeof window !== 'undefined' ? window : globalThis );
