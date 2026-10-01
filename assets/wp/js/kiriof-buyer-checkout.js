( function( root, wp, wc ) {
	'use strict';

	var config = root.kiriofBuyerCheckoutConfig || {};
	var blocks = wc && wc.blocksCheckout;
	var session = root.kiriofBuyerCheckoutSession;
	var namespace = 'kiriminaja-official';
	var errorId = 'kiriof-buyer-destination';
	var fieldId = namespace + '/kiriof_destination_area';
	var destinationSlot = blocks && ( blocks.OrderMeta || blocks.ExperimentalOrderMeta );
	var checkoutDispatch;
	var validationDispatch;

	if ( ! document.querySelector( '.wp-block-woocommerce-checkout, .wc-block-checkout, .wp-block-woocommerce-cart, .wc-block-cart' ) || ! config.enabled || ! session || ! destinationSlot || ! wp || ! wp.element || ! wp.components || ! wp.components.ComboboxControl || ! wp.plugins || ! wp.data || ! wp.data.useSelect || ! blocks.extensionCartUpdate ) {
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
	// OrderMeta has multiple mobile fills: mirror one model, elect one effect owner.
	var controllers = new Set();
	var owner = null;
	var state = { destination: null, queue: null, selection: null, results: { key: '', options: [], loading: false, error: false }, retryLookup: 0, retryUpdate: 0, lookupKey: '' };
	var resolveReady;
	var api = root.kiriofBuyerCheckout = {
		active: false, pending: true, disabled: false,
		ready: new Promise( function( resolve ) { resolveReady = resolve; } ),
		getDestination: function() { return state.destination; }
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

	function destinationForAddress( selection, address ) {
		return session.normalizeDestination( {
			district_id: selection ? selection.id : '',
			district_label: selection ? selection.label : '',
			postcode: String( address.postcode || '' ).replace( /\s+/g, '' ).toUpperCase(),
			country: address.country || 'ID',
			address_type: 'shipping'
		} );
	}

	function publish( destination ) {
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

	function DistrictControl() {
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
		var isOwner = ! owner || owner === token;
		var selection = state.selection;
		var results = state.results;
		var updateState = state.queue;
		var retryLookup = state.retryLookup;
		var filterState = useState( '' );
		var filter = filterState[ 0 ];
		var setFilter = filterState[ 1 ];
		var currentSelection = selection && selection.key === addressKey ? selection : null;
		var destination = destinationForAddress( currentSelection, address );
		var destinationKey = JSON.stringify( destination );
		var lookupGeneration = useRef( 0 );
		var destinationRef = useRef( destination );
		destinationRef.current = destination;

		function ownsEffects() { return ! api.disabled && owner === token; }

		useEffect( function() {
			if ( api.disabled ) { return; }
			controllers.add( token );
			listeners.add( revision[ 1 ] );
			if ( ! owner ) { owner = token; }
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
				if ( owner !== token ) { return; }
				owner = controllers.values().next().value || null;
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
			if ( ownsEffects() ) { publish( destinationRef.current ); }
		}, [ destinationKey, isOwner ] );

		useEffect( function() {
			if ( ownsEffects() ) { queue.resume(); }
		}, [ data.busy, isOwner ] );

		useEffect( function() {
			if ( ! ownsEffects() || ! cart.needsShipping || data.collection ) {
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
		}, [ destinationKey, data.payment, cart.needsShipping, data.collection, required, results.key, results.loading, results.error, isOwner ] );

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

		if ( api.disabled || ! required ) { return null; }
		var status = postcode.length < 3 ? strings.postcodeRequired : ( results.loading ? strings.loading : ( results.error ? strings.lookupFailed : ( results.options.length ? message : strings.empty ) ) );
		return h( 'div', { className: 'kiriof-buyer-district' },
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

	wp.plugins.registerPlugin( 'kiriminaja-official-buyer-destination', {
		scope: 'woocommerce-checkout',
		render: function() { return h( destinationSlot, null, h( DistrictControl ) ); }
	} );
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
