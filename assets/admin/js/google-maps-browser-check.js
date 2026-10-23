( function () {
	'use strict';

	var configNode = document.getElementById( 'kiriof-google-maps-check-config' );
	if ( ! configNode ) {
		return;
	}
	var config;
	try {
		config = JSON.parse( configNode.textContent );
	} catch ( error ) {
		return;
	}
	var statusNode = document.getElementById( 'kiriof-google-maps-check-status' );
	var mapNode = document.getElementById( 'kiriof-google-maps-check-map' );
	var deadline;
	var readyTimer;
	var tileListener;
	var map;
	var script;
	var disposed = false;
	var state = '';
	var callbackName = 'kiriofGoogleMapsCheck_' + config.channel.replace( /-/g, '_' );

	function clearTimers() {
		window.clearTimeout( deadline );
		window.clearTimeout( readyTimer );
	}

	function report( status, code ) {
		if ( disposed || ( 'ready' === status && state ) || ( 'error' === status && 'error' === state ) ) {
			return;
		}
		state = status;
		clearTimers();
		statusNode.textContent = 'ready' === status ? config.i18n.ready : config.i18n.error;
		window.parent.postMessage( {
			type: 'kiriof-google-maps-check',
			channel: config.channel,
			status: status,
			code: code
		}, config.targetOrigin );
	}

	function cleanup() {
		disposed = true;
		clearTimers();
		if ( tileListener ) {
			tileListener.remove();
		}
		if ( map && window.google && window.google.maps && window.google.maps.event ) {
			window.google.maps.event.clearInstanceListeners( map );
		}
		if ( script ) {
			script.onerror = null;
			script.remove();
		}
		delete window[ callbackName ];
		delete window.gm_authFailure;
		window.removeEventListener( 'pagehide', cleanup );
	}
	window.addEventListener( 'pagehide', cleanup );

	// A redirect must not silently validate the key against a different website.
	if ( window.location.origin !== config.probeOrigin ) {
		report( 'error', 'render_failed' );
		return;
	}

	if ( ! config.apiKey ) {
		report( 'error', 'not_configured' );
		return;
	}

	// Google invokes this global on authentication failure. Never forward raw provider errors.
	window.gm_authFailure = function () {
		report( 'error', 'auth_failed' );
	};
	window[ callbackName ] = function () {
		delete window[ callbackName ];
		if ( disposed || state ) {
			return;
		}
		try {
			map = new window.google.maps.Map( mapNode, {
				center: { lat: -6.1754, lng: 106.8272 },
				zoom: 12,
				disableDefaultUI: true,
				gestureHandling: 'none',
				keyboardShortcuts: false
			} );
			tileListener = map.addListener( 'tilesloaded', function () {
				tileListener.remove();
				// Tiles, not just SDK callback, are required; allow asynchronous auth rejection first.
				if ( ! disposed && ! state ) {
					window.clearTimeout( deadline );
					readyTimer = window.setTimeout( function () {
						report( 'ready', 'tiles_loaded' );
					}, 800 );
				}
			} );
		} catch ( error ) {
			report( 'error', 'render_failed' );
		}
	};
	deadline = window.setTimeout( function () {
		report( 'error', 'timeout' );
	}, 15000 );
	script = document.createElement( 'script' );
	script.async = true;
	script.referrerPolicy = 'strict-origin-when-cross-origin';
	script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( config.apiKey ) + '&callback=' + encodeURIComponent( callbackName ) + '&v=weekly&loading=async';
	script.onerror = function () {
		report( 'error', 'network_error' );
	};
	document.head.appendChild( script );
}() );
