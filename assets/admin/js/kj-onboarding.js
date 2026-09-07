(function ($) {
	'use strict';

	var $root = $('[data-kiriof-onboarding]');
	if (!$root.length) {
		return;
	}

	var order = ['account', 'address', 'couriers', 'shipping', 'complete'];
	var current = $root.data('current-step') || 'account';
	var accountComplete = String($root.data('account-complete')) === '1';
	var courierMap = {};
	var couriers = [];
	var map;

	function parse(response) {
		if (response && response.data) {
			return response.data;
		}
		return response;
	}

	function message(step, text, success) {
		$('[data-step-message="' + step + '"]').text(text || '').toggleClass('is-success', !!success);
	}

	function show(step) {
		current = step;
		$root.toggleClass('is-complete', step === 'complete');
		$('[data-step-panel]').removeClass('is-active');
		$('[data-step-panel="' + step + '"]').addClass('is-active');
		$('[data-step-target]').removeClass('is-current');
		$('[data-step-target="' + step + '"]').addClass('is-current');
		$('[data-kiriof-back]').toggle(step !== 'account' && step !== 'complete');
		$('[data-kiriof-continue]').toggle(step !== 'complete').text(step === 'shipping' ? 'Finish setup' : 'Continue');
		if (step === 'address') {
			initMap();
		}
		updateContinueState();
	}

	function post(action, data) {
		data = data || {};
		data.nonce = kiriofOnboarding.nonce;
		return $.ajax({ url: kiriofOnboarding.ajaxUrl, method: 'POST', data: { action: action, data: data } });
	}

	function next() {
		var index = order.indexOf(current);
		show(order[Math.min(index + 1, order.length - 1)]);
	}

	function canVisit(step) {
		if (step === 'account') {
			return true;
		}
		if (!accountComplete) {
			return false;
		}
		if (step === 'shipping') {
			return isStepDone('address') && isStepDone('couriers');
		}
		return true;
	}

	function isStepDone(step) {
		return $('[data-step-target="' + step + '"]').hasClass('is-done');
	}

	function blockNavigation(step) {
		if (!accountComplete) {
			show('account');
			message('account', kiriofOnboarding.accountRequired);
			return;
		}
		if (step === 'shipping' && !isStepDone('address')) {
			show('address');
			message('address', 'Save your shipping address before continuing.');
			return;
		}
		if (step === 'shipping' && !isStepDone('couriers')) {
			show('couriers');
			message('couriers', 'Save at least one courier service before continuing.');
		}
	}

	function hasSelectedCouriers() {
		return Object.keys(courierMap).length > 0;
	}

	function updateContinueState() {
		var disabled = current === 'couriers' && !hasSelectedCouriers();
		if (current === 'shipping' && (!isStepDone('address') || !isStepDone('couriers'))) {
			disabled = true;
		}
		$('[data-kiriof-continue]').prop('disabled', disabled);
	}

	function setCheck(selector, done) {
		var $check = $(selector);
		$check.toggleClass('is-done', done).toggleClass('is-pending', !done);
		$check.find('.dashicons').removeClass('dashicons-clock dashicons-yes-alt').addClass(done ? 'dashicons-yes-alt' : 'dashicons-clock');
	}

	function saveAccount() {
		var setupKey = $('#kiriof-onboarding-setup-key').val().trim();
		if (accountComplete && !setupKey) {
			return $.Deferred().resolve().promise();
		}
		if (!setupKey) {
			message('account', 'Setup key is required.');
			return $.Deferred().reject().promise();
		}
		return post('kiriof_store_integration_data', { setup_key: setupKey }).then(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			message('account', 'Account connected.', true);
			accountComplete = true;
			$root.attr('data-account-complete', '1');
			$('[data-step-target="account"]').addClass('is-done');
		});
	}

	function saveAddress() {
		var $area = $('[name="origin_sub_district_id"]');
		var data = {
			origin_name: $('[name="origin_name"]').val(),
			origin_phone: $('[name="origin_phone"]').val(),
			origin_address: $('[name="origin_address"]').val(),
			origin_zip_code: $('[name="origin_zip_code"]').val(),
			origin_latitude: $('[name="origin_latitude"]').val(),
			origin_longitude: $('[name="origin_longitude"]').val(),
			origin_sub_district_id: $area.val(),
			origin_sub_district_name: $('[name="origin_sub_district_name"]').val()
		};
		if (Object.keys(data).some(function (key) { return !String(data[key] || '').trim(); })) {
			message('address', 'Complete all address fields and set the map pin.');
			return $.Deferred().reject().promise();
		}
		return post('kiriof_store_origin_data', data).then(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			message('address', 'Shipping address saved.', true);
			$('[data-step-target="address"]').addClass('is-done');
		});
	}

	function selectedCourierData() {
		var ids = Object.keys(courierMap);
		return { whitelist_ids: ids.join(','), whitelist_names: ids.map(function (id) { return courierMap[id]; }).join(',') };
	}

	function saveCouriers() {
		if (!Object.keys(courierMap).length) {
			message('couriers', 'Select at least one courier service.');
			return $.Deferred().reject().promise();
		}
		return post('kiriof_store_courier_whitelist', selectedCourierData()).then(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			message('couriers', 'Courier services saved.', true);
			$('[data-step-target="couriers"]').addClass('is-done');
		});
	}

	function enableShipping() {
		if (!isStepDone('address') || !isStepDone('couriers')) {
			message('shipping', 'Complete previous required steps before finishing.');
			updateContinueState();
			return $.Deferred().reject().promise();
		}
		return post('kiriof_enable_shipping_method').then(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			setCheck('[data-shipping-status]', true);
			if (!result.locations_ready) {
				message('shipping', 'Enable WooCommerce shipping locations before finishing.');
				return $.Deferred().reject(result).promise();
			}
			setCheck('[data-location-status]', true);
			$('[data-step-target="shipping"]').addClass('is-done');
		});
	}

	function continueStep() {
		if (current === 'couriers' && !hasSelectedCouriers()) {
			message('couriers', 'Select at least one courier service.');
			updateContinueState();
			return;
		}
		var $button = $('[data-kiriof-continue]').prop('disabled', true);
		var request = current === 'account' ? saveAccount() : current === 'address' ? saveAddress() : current === 'couriers' ? saveCouriers() : enableShipping();
		request.done(next).fail(function (result) {
			var text = result && result.message ? result.message : kiriofOnboarding.saveFailed;
			if (!$('[data-step-message="' + current + '"]').text()) {
				message(current, text);
			}
		}).always(function () { $button.prop('disabled', false); updateContinueState(); });
	}

	function renderCouriers() {
		var enabledCount = Object.keys(courierMap).length;
		var html = couriers.map(function (courier) {
			var checked = Object.prototype.hasOwnProperty.call(courierMap, courier.code) ? ' checked' : '';
			var toggleClass = checked ? 'woocommerce-input-toggle--enabled' : 'woocommerce-input-toggle--disabled';
			return '<div class="kiriof-onboarding__courier"><span><strong>' + $('<div>').text(courier.name).html() + '</strong><br><small>' + $('<div>').text(courier.type || '').html() + '</small></span><label class="kiriof-onboarding__switch"><input class="screen-reader-text" type="checkbox" data-courier="' + $('<div>').text(courier.code).html() + '"' + checked + '><span class="woocommerce-input-toggle ' + toggleClass + '"></span><span>Enable</span></label></div>';
		}).join('');
		$('[data-courier-list]').html(html || '<p>No courier services are available.</p>');
		$('[data-courier-count]').text(enabledCount + ' enabled');
		if (!enabledCount) {
			$('[data-step-target="couriers"]').removeClass('is-done');
		}
		updateContinueState();
	}

	function loadCouriers() {
		post('kiriof_get_courier_whitelist').done(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200 || !result.data) {
				message('couriers', result && result.message ? result.message : kiriofOnboarding.networkError);
				return;
			}
			couriers = result.data.couriers || [];
			(result.data.whitelist_ids || []).forEach(function (id) { courierMap[id] = id; });
			couriers.forEach(function (courier) { if (courierMap[courier.code]) { courierMap[courier.code] = courier.name; } });
			renderCouriers();
		}).fail(function () { message('couriers', kiriofOnboarding.networkError); });
	}

	function initMap() {
		if (map || typeof L === 'undefined' || !document.getElementById('kiriof-onboarding-map')) {
			return;
		}
		var lat = parseFloat($('[name="origin_latitude"]').val()) || -6.2088;
		var lng = parseFloat($('[name="origin_longitude"]').val()) || 106.8456;
		map = L.map('kiriof-onboarding-map').setView([lat, lng], 15);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
		var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
		function update(position) {
			$('[name="origin_latitude"]').val(position.lat.toFixed(7));
			$('[name="origin_longitude"]').val(position.lng.toFixed(7));
		}
		var locate = L.control({ position: 'topright' });
		locate.onAdd = function () {
			var button = L.DomUtil.create('button', 'kiriof-onboarding__locate');
			button.type = 'button';
			button.title = kiriofOnboarding.currentLocation;
			button.setAttribute('aria-label', kiriofOnboarding.currentLocation);
			button.innerHTML = '<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>';
			L.DomEvent.disableClickPropagation(button);
			L.DomEvent.on(button, 'click', function () {
				if (!navigator.geolocation) {
					message('address', kiriofOnboarding.currentLocationUnavailable);
					return;
				}
				navigator.geolocation.getCurrentPosition(function (position) {
					var point = L.latLng(position.coords.latitude, position.coords.longitude);
					marker.setLatLng(point);
					map.setView(point, 16);
					update(point);
				}, function () {
					message('address', kiriofOnboarding.currentLocationFailed);
				});
			});
			return button;
		};
		locate.addTo(map);
		marker.on('dragend', function () { update(marker.getLatLng()); });
		map.on('click', function (event) { marker.setLatLng(event.latlng); update(event.latlng); });
		update(marker.getLatLng());
		setTimeout(function () { map.invalidateSize(); }, 100);
	}

	var $subdistrict = $('.kiriof-onboarding-subdistrict');
	if ($subdistrict.hasClass('select2-hidden-accessible')) {
		$subdistrict.select2('destroy');
	}
	$subdistrict
		.select2({
			width: '100%',
			minimumInputLength: 3,
			placeholder: kiriofOnboarding.subdistrictPlaceholder,
			ajax: {
				url: kiriofOnboarding.ajaxUrl,
				dataType: 'json',
				type: 'POST',
				delay: 250,
				data: function (params) {
					return {
						action: 'kiriminaja_subdistrict_search',
						nonce: kiriofOnboarding.nonce,
						term: params.term || '',
						data: { term: params.term || '', search: params.term || '' }
					};
				},
				processResults: function (response) {
					return {
						results: $.map(response && response.data ? response.data : [], function (item) {
							return { id: String(item.id), text: item.text, postcode: item.postcode || '' };
						})
					};
				}
			}
		})
		.off('select2:select.kiriofOnboarding select2:clear.kiriofOnboarding')
		.on('select2:select.kiriofOnboarding', function (event) {
			var selected = event.params && event.params.data ? event.params.data : null;
			var id = selected && selected.id ? String(selected.id) : '';
			var text = selected && selected.text ? String(selected.text) : '';

			if (!id || !text) {
				return;
			}

			$(this).empty().append(new Option(text, id, true, true)).val(id);
			$('[name="origin_sub_district_name"]').val(text);
			if (selected.postcode) {
				$('[name="origin_zip_code"]').val(String(selected.postcode)).trigger('input').trigger('change');
			}
			$(this).trigger('change.select2');
		})
		.on('select2:clear.kiriofOnboarding', function () {
			$(this).empty().val(null);
			$('[name="origin_sub_district_name"]').val('');
		});

	$('[data-kiriof-continue]').on('click', continueStep);
	$('[data-kiriof-back]').on('click', function () { show(order[Math.max(order.indexOf(current) - 1, 0)]); });
	$('[data-step-target]').on('click', function () {
		var target = $(this).data('step-target');
		if (!canVisit(target)) {
			blockNavigation(target);
			return;
		}
		show(target);
	});
	$('[data-courier-list]').on('change', '[data-courier]', function () { var code = String($(this).data('courier')); var courier = couriers.find(function (item) { return String(item.code) === code; }); if (this.checked && courier) { courierMap[code] = courier.name; } else { delete courierMap[code]; } renderCouriers(); });
	$('[data-couriers-all]').on('click', function () { couriers.forEach(function (courier) { courierMap[courier.code] = courier.name; }); renderCouriers(); });
	$('[data-couriers-none]').on('click', function () { courierMap = {}; renderCouriers(); });
	$('body').on('click', '.kj-disconnect', function () {
		if (!window.confirm(kiriofOnboarding.disconnectConfirm)) {
			return;
		}
		post('kiriof_disconnect_integration').done(function (response) {
			var result = parse(response);
			if (!result || Number(result.status) !== 200) {
				window.alert(result && result.message ? result.message : kiriofOnboarding.disconnectFailed);
				return;
			}
			window.location.reload();
		}).fail(function () {
			window.alert(kiriofOnboarding.networkError);
		});
	});

	loadCouriers();
	show(current);
})(jQuery);
