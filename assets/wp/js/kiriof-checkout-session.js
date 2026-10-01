( function( root ) {
	'use strict';

	function text( value ) {
		return null === value || undefined === value ? '' : String( value ).trim().replace( /\s+/g, ' ' );
	}

	function normalizeDestination( destination ) {
		var input = destination || {};
		var district = text( input.district_id );
		var validDistrict = /^\d+$/.test( district ) && Number.isSafeInteger( Number( district ) ) && Number( district ) > 0;

		return Object.freeze( {
			version: 1,
			district_id: validDistrict ? String( Number( district ) ) : '',
			district_label: validDistrict ? text( input.district_label ) : '',
			postcode: text( input.postcode ).replace( /\s+/g, '' ).toUpperCase(),
			country: text( input.country ).toUpperCase(),
			address_type: text( input.address_type ).toLowerCase()
		} );
	}

	function freeze( value ) {
		if ( value && 'object' === typeof value ) {
			Object.keys( value ).forEach( function( key ) {
				freeze( value[ key ] );
			} );
			Object.freeze( value );
		}
		return value;
	}

	function clone( value ) {
		return freeze( JSON.parse( JSON.stringify( value ) ) );
	}

	function fingerprint( value ) {
		if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
			return '{' + Object.keys( value ).sort().map( function( key ) {
				return JSON.stringify( key ) + ':' + fingerprint( value[ key ] );
			} ).join( ',' ) + '}';
		}
		if ( Array.isArray( value ) ) {
			return '[' + value.map( fingerprint ).join( ',' ) + ']';
		}
		return JSON.stringify( value );
	}

	/**
	 * Serialized, latest-pending queue for JSON-compatible snapshots.
	 * Promises never reject for transport failures: results have status 'success',
	 * 'error', 'superseded', or 'disposed', plus the caller's immutable snapshot.
	 * Replacing an unsent snapshot settles its callers as 'superseded'. A failed
	 * latest snapshot remains pending until retry() (or a different update()).
	 * send() must reject on application/HTTP failure, not merely resolve an error
	 * response. Server mutations are never aborted, including on dispose().
	 */
	function createQueue( options ) {
		var send = options.send;
		var onChange = options.onChange || function() {};
		var isBlocked = options.isBlocked || function() { return false; };
		var schedule = options.schedule || function( callback ) { return setTimeout( callback, 0 ); };
		var cancel = options.cancel || function( handle ) { clearTimeout( handle ); };
		var pending = null;
		var inFlight = null;
		var acknowledged = null;
		var acknowledgedKey = null;
		var error = null;
		var blocked = false;
		var disposed = false;
		var scheduled = false;
		var scheduledHandle;

		function getState() {
			return Object.freeze( {
				pending: pending ? pending.snapshot : null,
				inFlight: inFlight ? inFlight.snapshot : null,
				acknowledged: acknowledged,
				error: error,
				blocked: blocked,
				disposed: disposed
			} );
		}

		function notify() {
			if ( ! disposed ) {
				onChange( getState() );
			}
		}

		function settle( entry, status, failure ) {
			entry.waiters.splice( 0 ).forEach( function( resolve ) {
				resolve( { status: status, snapshot: entry.snapshot, error: failure || null } );
			} );
		}

		function wait( entry ) {
			return new Promise( function( resolve ) { entry.waiters.push( resolve ); } );
		}

		function enqueueSend() {
			if ( disposed || scheduled || inFlight || ! pending || error ) {
				return;
			}
			scheduled = true;
			scheduledHandle = schedule( function() {
				scheduled = false;
				pump();
			} );
		}

		function complete( entry, failure, failed ) {
			if ( disposed ) {
				return;
			}
			inFlight = null;
			if ( failed ) {
				settle( entry, 'error', failure );
				if ( ! pending ) {
					pending = entry;
					error = failure;
				}
			} else {
				acknowledged = entry.snapshot;
				acknowledgedKey = entry.key;
				settle( entry, 'success' );
			}
			notify();
			enqueueSend();
		}

		function pump() {
			var entry;
			var result;
			if ( disposed || inFlight || ! pending || error ) {
				return;
			}
			blocked = Boolean( isBlocked() );
			if ( blocked ) {
				notify();
				return;
			}
			entry = pending;
			pending = null;
			inFlight = entry;
			notify();
			// A listener may dispose the queue before the transport starts.
			if ( disposed ) {
				return;
			}
			try {
				result = send( entry.snapshot );
			} catch ( failure ) {
				complete( entry, failure || new Error( 'Checkout update failed' ), true );
				return;
			}
			Promise.resolve( result ).then( function() {
				complete( entry, null, false );
			}, function( failure ) {
				complete( entry, failure || new Error( 'Checkout update failed' ), true );
			} );
		}

		function update( snapshot ) {
			var entry = { snapshot: clone( snapshot ), waiters: [] };
			var promise;
			entry.key = fingerprint( entry.snapshot );
			if ( disposed ) {
				return Promise.resolve( { status: 'disposed', snapshot: entry.snapshot, error: null } );
			}
			if ( pending && pending.key === entry.key ) {
				if ( error ) {
					return Promise.resolve( { status: 'error', snapshot: pending.snapshot, error: error } );
				}
				return wait( pending );
			}
			if ( ! pending && inFlight && inFlight.key === entry.key ) {
				return wait( inFlight );
			}
			if ( ! pending && ! inFlight && acknowledgedKey === entry.key ) {
				return Promise.resolve( { status: 'success', snapshot: entry.snapshot, error: null } );
			}
			if ( pending ) {
				settle( pending, 'superseded' );
			}
			pending = entry;
			error = null;
			promise = wait( entry );
			notify();
			enqueueSend();
			return promise;
		}

		function retry() {
			var promise;
			if ( disposed ) {
				return Promise.resolve( { status: 'disposed', snapshot: null, error: null } );
			}
			if ( pending ) {
				promise = wait( pending );
				error = null;
				notify();
				enqueueSend();
				return promise;
			}
			if ( inFlight ) {
				return wait( inFlight );
			}
			return Promise.resolve( { status: 'success', snapshot: acknowledged, error: null } );
		}

		function resume() {
			enqueueSend();
		}

		function dispose() {
			if ( disposed ) {
				return;
			}
			disposed = true;
			if ( scheduled ) {
				cancel( scheduledHandle );
				scheduled = false;
			}
			if ( pending ) {
				settle( pending, 'disposed' );
			}
			if ( inFlight ) {
				settle( inFlight, 'disposed' );
			}
			pending = null;
			inFlight = null;
		}

		return { update: update, retry: retry, resume: resume, dispose: dispose, getState: getState };
	}

	root.kiriofBuyerCheckoutSession = { createQueue: createQueue, normalizeDestination: normalizeDestination };
} )( 'undefined' !== typeof window ? window : globalThis );
