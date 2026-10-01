( function( root, wp, wc ) {
	'use strict';

	var settings = wc && wc.wcSettings;
	var config = root.kiriofBuyerCheckoutConfig || ( settings && settings.getSetting ? settings.getSetting( 'kiriminaja-official-buyer_data', {} ) : {} );
	var blocks = wc && wc.blocksCheckout;
	var session = root.kiriofBuyerCheckoutSession;
	var namespace = 'kiriminaja-official';
	var errorId = 'kiriof-buyer-destination';
	var fieldId = namespace + '/kiriof_destination_area';
	var destinationSlot = blocks && ( blocks.OrderMeta || blocks.ExperimentalOrderMeta );
	destinationSlot = destinationSlot && wp && wp.plugins && typeof wp.plugins.registerPlugin === 'function' ? destinationSlot : null;
	var supportsDistrictInnerBlock = !!( blocks && typeof blocks.registerCheckoutBlock === 'function' );
	var checkoutDispatch;
	var validationDispatch;

	if ( ! document.querySelector( '.wp-block-woocommerce-checkout, .wc-block-checkout, .wp-block-woocommerce-cart, .wc-block-cart' ) || ! config.enabled || ! session || ( ! destinationSlot && ! supportsDistrictInnerBlock ) || ! wp || ! wp.element || ! wp.components || ! wp.components.ComboboxControl || ! wp.data || ! wp.data.useSelect || ! blocks.extensionCartUpdate ) {
		return;
	}
	try {
		checkoutDispatch = wp.data.dispatch( 'wc/store/checkout' );
		validationDispatch = wp.data.dispatch( 'wc/store/validation' );
		var cartStore = wp.data.select( 'wc/store/cart' );
		var paymentStore = wp.data.select( 'wc/store/payment' );
		if ( ! checkoutDispatch || ! checkoutDispatch.setExtensionData || ! validationDispatch || ! validationDispatch.setValidationErrors || ! validationDispatch.clearValidationError || ! cartStore || ! cartStore.isShippingRateBeingSelected || ! cartStore.isCustomerDataUpdating || ! paymentStore || ! paymentStore.getActivePaymentMethod ) {
			return;
		}
	} catch {
		return;
	}

	var element = wp.element;
	var h = element.createElement;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var useRef = element.useRef;
	var strings = config.i18n || {};
	var listeners = new Set();
	var savedSelections = Object.assign( {}, config.savedDistrictByPostcode || {} );
	// Prefer in-address mounts over fallback order-summary mounts.
	var controllers = new Map();
	var owner = null;
	var state = { destination: null, queue: null, selection: null, results: { key: '', options: [], loading: false, error: false }, retryLookup: 0, retryUpdate: 0, lookupKey: '' };
	var savedPin = savedCoordinates( config.savedDestination );
	var restorationAttempted = ! savedPin;
	var resolveReady;
	var api = root.kiriofBuyerCheckout = {
		active: false, pending: true, disabled: false,
		ready: new Promise( function( resolve ) { resolveReady = resolve; } ),
		getDestination: function() { return state.destination; },
		setCoordinates: setCoordinates,
		getCoordinates: getCoordinates
	};
	var readinessTimer = root.setTimeout( function() {
		api.pending = false;
		api.disabled = true;
		resolveReady( false );
	}, 2000 );

	function notify() {
		listeners.forEach( function( listener ) { listener( function( revision ) { return revision + 1; } ); } );
	}

	function setShared( key, next ) {
		state[ key ] = 'function' === typeof next ? next( state[ key ] ) : next;
		notify();
	}

	function setSelection( next ) { setShared( 'selection', next ); }
	function setResults( next ) { setShared( 'results', next ); }
	function setMapPin( next ) {
		var value = 'function' === typeof next ? next( state.mapPin || null ) : next;
		if ( value && ( ! validCoordinate( value.latitude, 90 ) || ! validCoordinate( value.longitude, 180 ) ) ) {
			return;
		}
		setShared( 'mapPin', value );
	}
	var queue = session.createQueue( {
		send: function( snapshot ) {
			return blocks.extensionCartUpdate( {
				namespace: namespace,
				data: snapshot,
				overwriteDirtyCustomerData: false
			} );
		},
		isBlocked: function() {
			var cart = wp.data.select( 'wc/store/cart' );
			return Boolean( ! owner || api.disabled || cart.isShippingRateBeingSelected() || cart.isCustomerDataUpdating() || ( cart.hasPendingItemsOperations && cart.hasPendingItemsOperations() ) );
		},
		onChange: function( next ) {
			state.queue = next;
			notify();
		}
	} );
	state.queue = queue.getState();

	function destinationForAddress( selection, address, coordinates ) {
		var destination = session.normalizeDestination( {
			district_id: selection ? selection.id : '',
			district_label: selection ? selection.label : '',
			postcode: String( address.postcode || '' ).replace( /\s+/g, '' ).toUpperCase(),
			country: address.country || 'ID',
			address_type: 'shipping',
			destination_latitude: '',
			destination_longitude: ''
		} );
		if ( coordinates && validCoordinate( coordinates.latitude, 90 ) && validCoordinate( coordinates.longitude, 180 ) ) {
			destination = Object.assign( {}, destination, {
				destination_latitude: String( coordinates.latitude ).trim(),
				destination_longitude: String( coordinates.longitude ).trim(),
				version: 2,
				shipping_address: shippingAddress( address )
			} );
		}
		return destination;
	}

	function validCoordinate( value, limit ) {
		if ( null === value || undefined === value || ( 'string' === typeof value && '' === value.trim() ) ) {
			return false;
		}
		if ( 'number' !== typeof value && 'string' !== typeof value ) {
			return false;
		}
		var number = Number( value );
		return isFinite( number ) && Math.abs( number ) <= limit;
	}

	function publish( destination ) {
		if ( JSON.stringify( state.destination ) === JSON.stringify( destination ) ) { return; }
		state.destination = destination;
		checkoutDispatch.setExtensionData( namespace, { destination: destination } );
	}

	function setValidation( message ) {
		if ( message ) {
			var errors = {};
			errors[ errorId ] = { message: message, hidden: false };
			validationDispatch.setValidationErrors( errors );
		} else {
			validationDispatch.clearValidationError( errorId );
		}
	}

	function hasInnerPlacement() {
		return Array.from( controllers.values() ).some( function( placement ) { return 'shipping-address' === placement; } );
	}
	function electOwner() {
		var preferred = hasInnerPlacement() ? 'shipping-address' : 'order-summary';
		if ( owner && controllers.get( owner ) === preferred ) { return; }
		owner = null;
		controllers.forEach( function( placement, token ) {
			if ( ! owner && placement === preferred ) { owner = token; }
		} );
	}
	function shippingAddress( address ) {
		var snapshot = {};
		[ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ].forEach( function( key ) {
			snapshot[ key ] = String( address && address[ key ] || '' ).trim();
		} );
		snapshot.postcode = snapshot.postcode.replace( /\s+/g, '' ).toUpperCase();
		snapshot.country = snapshot.country.toUpperCase();
		return snapshot;
	}
	function shippingAddressKey( address ) { return JSON.stringify( shippingAddress( address ) ); }
	function savedCoordinates( destination ) {
		function plainCoordinate( value, limit ) {
			return ( 'number' === typeof value || 'string' === typeof value ) && /^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test( String( value ).trim() ) && validCoordinate( value, limit );
		}
		if ( ! destination || 2 !== destination.version || ! /^[1-9][0-9]*$/.test( String( destination.district_id ) ) || ! destination.shipping_address ||
			! plainCoordinate( destination.destination_latitude, 90 ) || ! plainCoordinate( destination.destination_longitude, 180 ) ) { return null; }
		var address = shippingAddress( destination.shipping_address );
		if ( ! completeShippingAddress( address ) || 'ID' !== address.country || address.country !== String( destination.country || '' ).toUpperCase() || address.postcode !== String( destination.postcode || '' ).replace( /\s+/g, '' ).toUpperCase() ) { return null; }
		return { latitude: Number( destination.destination_latitude ).toFixed( 7 ), longitude: Number( destination.destination_longitude ).toFixed( 7 ), key: shippingAddressKey( address ) };
	}
	function completeShippingAddress( address ) {
		return [ 'address_1', 'city', 'state', 'postcode', 'country' ].every( function( key ) { return Boolean( address[ key ] ); } );
	}
	function getCoordinates( address ) {
		var cart = wp.data.select( 'wc/store/cart' );
		var data = cart.getCartData() || {};
		var key = shippingAddressKey( address );
		if ( key !== shippingAddressKey( data.shippingAddress ) ) { return null; }
		// The map may mount before the District owner. Restore silently here so its
		// first render and the first published snapshot see the same real pin.
		// Empty initial cart/customer data is not a failed restoration attempt.
		if ( ! restorationAttempted && data.needsShipping && ! cart.isCustomerDataUpdating() && completeShippingAddress( shippingAddress( data.shippingAddress ) ) ) {
			restorationAttempted = true;
			if ( savedPin.key === key ) { state.mapPin = savedPin; }
		}
		return state.mapPin && state.mapPin.key === key ? state.mapPin : null;
	}
	function setCoordinates( address, point ) {
		if ( api.disabled || ! api.active ) { return false; }
		var cart = wp.data.select( 'wc/store/cart' ).getCartData() || {};
		if ( shippingAddressKey( address ) !== shippingAddressKey( cart.shippingAddress ) ) { return false; }
		if ( point && ( ! validCoordinate( point.latitude, 90 ) || ! validCoordinate( point.longitude, 180 ) ) ) { return false; }
		restorationAttempted = true;
		setMapPin( point ? { latitude: Number( point.latitude ).toFixed( 7 ), longitude: Number( point.longitude ).toFixed( 7 ), key: shippingAddressKey( address ) } : null );
		return true;
	}

	function DistrictControl( props ) {
		var slot = props && props.slot ? props.slot : 'order-summary';
		var data = wp.data.useSelect( function( select ) {
			var cart = select( 'wc/store/cart' );
			var checkout = select( 'wc/store/checkout' );
			var payment = select( 'wc/store/payment' );
			return {
				cart: cart.getCartData(),
				payment: payment.getActivePaymentMethod(),
				busy: cart.isShippingRateBeingSelected() || cart.isCustomerDataUpdating(),
				collection: checkout.prefersCollection ? checkout.prefersCollection() : false
			};
		}, [] );
		var cart = data.cart || {};
		var address = cart.shippingAddress || {};
		var postcode = String( address.postcode || '' ).replace( /\s+/g, '' ).toUpperCase();
		var country = String( address.country || 'ID' ).toUpperCase();
		var addressKey = country + '|' + postcode;
		var required = Boolean( cart.needsShipping && 'ID' === country && ! data.collection );
		var rates = ( cart.shippingRates || [] ).flatMap( function( pkg ) { return pkg.shipping_rates || []; } );
		var selected = rates.filter( function( rate ) { return rate.selected; } );
		var kiriminajaSelected = ! selected.length || selected.some( function( rate ) {
			return 'kiriminaja-official' === rate.method_id || /^kiriminaja-official(?:_|:)/.test( rate.rate_id || '' );
		} );
		var revision = useState( 0 );
		var token = useRef( {} ).current;
		var isOwner = owner === token;
		var selection = state.selection;
		var mapPin = getCoordinates( address );
		var awaitingSavedPin = ! restorationAttempted;
		var results = state.results;
		var updateState = state.queue;
		var retryLookup = state.retryLookup;
		var filterState = useState( '' );
		var filter = filterState[ 0 ];
		var setFilter = filterState[ 1 ];
		var currentSelection = selection && selection.key === addressKey ? selection : null;
		var currentPin = mapPin && mapPin.key === shippingAddressKey( address ) ? mapPin : null;
		var destination = destinationForAddress( currentSelection, address, currentPin );
		var destinationKey = JSON.stringify( destination );
		var lookupGeneration = useRef( 0 );
		var destinationRef = useRef( destination );
		destinationRef.current = destination;
		var pinAddressKey = shippingAddressKey( address );
		useEffect( function() {
			if ( state.mapPin && state.mapPin.key !== pinAddressKey ) { setMapPin( null ); }
		}, [ pinAddressKey ] );

		function ownsEffects() { return ! api.disabled && owner === token && Boolean( data.cart ); }

		useEffect( function() {
			if ( api.disabled ) { return; }
			controllers.set( token, slot );
			electOwner();
			listeners.add( revision[ 1 ] );
			api.active = true;
			if ( api.pending ) {
				api.pending = false;
				root.clearTimeout( readinessTimer );
				resolveReady( true );
			}
			document.documentElement.classList.add( 'kiriof-buyer-checkout-active' );
			notify();
			return function() {
				listeners.delete( revision[ 1 ] );
				controllers.delete( token );
				electOwner();
				if ( ! owner ) {
					setValidation( '' );
					api.active = false;
					if ( document.documentElement.classList.remove ) {
						document.documentElement.classList.remove( 'kiriof-buyer-checkout-active' );
					}
				}
				notify();
			};
		}, [] );

		useEffect( function() {
			if ( ownsEffects() && ! awaitingSavedPin ) { publish( destinationRef.current ); }
		}, [ destinationKey, isOwner, awaitingSavedPin ] );

		useEffect( function() {
			if ( ownsEffects() ) { queue.resume(); }
		}, [ data.busy, isOwner ] );

		useEffect( function() {
			if ( ! ownsEffects() || awaitingSavedPin || ! cart.needsShipping || data.collection ) {
				return;
			}
			// Restore a saved identity before issuing a mutation with an empty district.
			if ( required && ( results.key !== addressKey || results.loading || results.error || postcode.length < 3 ) ) {
				return;
			}
			queue.update( {
				action: 'sync_checkout',
				destination: destinationRef.current,
				payment_method: data.payment || '',
				insurance: config.globalInsurance ? 1 : 0,
				force_insurance: 0
			} );
		}, [ destinationKey, data.payment, cart.needsShipping, data.collection, required, results.key, results.loading, results.error, isOwner, awaitingSavedPin ] );

		useEffect( function() {
			if ( ! ownsEffects() ) { return; }
			var lookupKey = addressKey + '|' + required + '|' + retryLookup;
			// A replacement owner reuses completed results, but restarts an aborted lookup.
			if ( state.lookupKey === lookupKey && ! state.results.loading ) { return; }
			state.lookupKey = lookupKey;
			var generation = ++lookupGeneration.current;
			var controller = new AbortController();
			var timer;
			setFilter( '' );
			setResults( { key: addressKey, options: [], loading: required && postcode.length >= 3, error: false } );
			if ( required && postcode.length >= 3 ) {
				timer = root.setTimeout( function() {
					var body = new URLSearchParams( {
						action: 'kiriminaja_subdistrict_search',
						nonce: config.nonce,
						term: postcode
					} );
					root.fetch( config.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString(),
						signal: controller.signal
					} ).then( function( response ) {
						if ( ! response.ok ) { throw new Error( 'District lookup failed' ); }
						return response.json();
					} ).then( function( response ) {
						if ( generation !== lookupGeneration.current || controller.signal.aborted ) { return; }
						if ( ! response.success || ! Array.isArray( response.data ) ) { throw new Error( 'District lookup failed' ); }
						var options = response.data.filter( function( row ) { return /^[1-9][0-9]*$/.test( String( row.id ) ) && row.text; } ).map( function( row ) {
							return { value: String( row.id ), label: String( row.text ) };
						} );
						setResults( { key: addressKey, options: options, loading: false, error: false } );
						var saved = savedSelections[ postcode ];
						var stored = config.savedDestination;
						var savedId = stored && stored.postcode === postcode && stored.country === country ? stored.district_id : ( saved && saved.destination_id );
						if ( ! savedId && address[ fieldId ] ) { savedId = address[ fieldId ]; }
						if ( ! savedId && String( config.districtPostcode || '' ).replace( /\s+/g, '' ) === postcode ) {
							savedId = config.district && config.district.id;
						}
						var restored = options.find( function( option ) { return option.value === String( savedId || '' ); } );
						if ( restored ) {
							setSelection( function( previous ) {
								return previous && previous.key === addressKey ? previous : { id: restored.value, label: restored.label, key: addressKey };
							} );
						}
					} ).catch( function() {
						if ( generation === lookupGeneration.current && ! controller.signal.aborted ) {
							setResults( { key: addressKey, options: [], loading: false, error: true } );
						}
					} );
				}, 250 );
			}
			return function() {
				controller.abort();
				root.clearTimeout( timer );
			};
		}, [ addressKey, required, retryLookup, isOwner ] );

		useEffect( function() {
			if ( ownsEffects() && state.retryUpdate ) {
				state.retryUpdate = 0;
				queue.retry();
			}
		}, [ state.retryUpdate, isOwner ] );

		var saving = Boolean( updateState.pending || updateState.inFlight );
		var message = updateState.error ? strings.updateFailed : ( saving ? strings.saving : ( required && ( ! currentSelection || results.key !== addressKey || results.loading || results.error ) ? strings.districtRequired : '' ) );
		useEffect( function() {
			if ( ownsEffects() ) { setValidation( required && kiriminajaSelected ? message : '' ); }
		}, [ message, required, kiriminajaSelected, isOwner ] );

		if ( api.disabled || ! required || ( 'order-summary' === slot && hasInnerPlacement() ) ) { return null; }
		var status = postcode.length < 3 ? strings.postcodeRequired : ( results.loading ? strings.loading : ( results.error ? strings.lookupFailed : ( results.options.length ? message : strings.empty ) ) );
		return h( 'div', { className: 'kiriof-buyer-district kiriof-buyer-district--inner-block' },
			h( wp.components.ComboboxControl, {
				label: strings.district,
				value: currentSelection ? currentSelection.id : null,
				options: results.key === addressKey ? results.options.filter( function( option ) {
					return option.label.toLowerCase().indexOf( filter.toLowerCase() ) !== -1;
				} ) : [],
				onFilterValueChange: setFilter,
				allowReset: true,
				disabled: postcode.length < 3 || results.loading,
				placeholder: strings.selectDistrict,
				onChange: function( value ) {
					var selected = results.options.find( function( option ) { return option.value === value; } );
					if ( selected ) {
						savedSelections[ postcode ] = { destination_id: selected.value, destination_name: selected.label };
					} else {
						delete savedSelections[ postcode ];
					}
					setSelection( selected ? { id: selected.value, label: selected.label, key: addressKey } : null );
				},
				__nextHasNoMarginBottom: true
			} ),
			h( 'p', { role: 'status', 'aria-live': 'polite' }, status ),
			results.error || updateState.error ? h( 'button', {
				type: 'button',
				className: 'wc-block-components-button wp-element-button',
				onClick: function() {
					if ( results.error ) { setShared( 'retryLookup', function( previous ) { return previous + 1; } ); }
					if ( updateState.error ) { setShared( 'retryUpdate', state.retryUpdate + 1 ); }
				}
			}, strings.retry ) : null
		);
	}

	if ( destinationSlot ) {
		wp.plugins.registerPlugin( 'kiriminaja-official-buyer-destination', {
			scope: 'woocommerce-checkout',
			render: function() { return h( destinationSlot, null, h( DistrictControl, { slot: 'order-summary' } ) ); }
		} );
	}
	if ( supportsDistrictInnerBlock ) {
		blocks.registerCheckoutBlock( {
			force: true,
			metadata: {
				name: 'kiriminaja-official/checkout-district',
				parent: [ 'woocommerce/checkout-shipping-address-block' ],
				attributes: { lock: { type: 'object', default: { remove: true, move: true } } }
			},
			component: function() { return h( DistrictControl, { slot: 'shipping-address' } ); }
		} );
	}
	var unsubscribe = wp.data.subscribe( function() { if ( owner && ! api.disabled ) { queue.resume(); } } );
	root.addEventListener( 'pagehide', function( event ) {
		if ( event && event.persisted ) { return; }
		api.disabled = true;
		root.clearTimeout( readinessTimer );
		if ( api.pending ) { api.pending = false; resolveReady( false ); }
		unsubscribe();
		queue.dispose();
	} );
} )( window, window.wp, window.wc );
