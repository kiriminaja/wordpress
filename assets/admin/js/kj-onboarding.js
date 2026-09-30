(function ($) {
	'use strict';

	var $root = $('[data-kiriof-onboarding]');
	if (!$root.length) {
		return;
	}

	var order = ['account', 'address', 'couriers', 'shipping', 'complete'];
	var current = $root.data('current-step') || 'account';
	var accountComplete = String($root.data('account-complete')) === '1';
	var courierPicker;
	var courierSaving = false;
	var stepSaving = false;
	var couriersLoaded = false;
	var couriersLoading = false;
	var courierLoadRequest;
	var courierLoadGeneration = 0;
	var map;

	function parse(response) {
		if (response && response.success === false) {
			return { status: 0, message: response.data && response.data.message || response.message };
		}
		if (response && response.success === true && response.data) {
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
		if (step === 'couriers') {
			loadCouriers();
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
		if (step === 'complete' && !isStepDone('shipping')) {
			return false;
		}
		if (step === 'shipping' || step === 'complete') {
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
		if ((step === 'shipping' || step === 'complete') && !isStepDone('address')) {
			show('address');
			message('address', 'Save your shipping address before continuing.');
			return;
		}
		if ((step === 'shipping' || step === 'complete') && !isStepDone('couriers')) {
			show('couriers');
			message('couriers', 'Save at least one courier service before continuing.');
			return;
		}
		if (step === 'complete' && !isStepDone('shipping')) {
			show('shipping');
			message('shipping', 'Enable shipping before finishing.');
		}
	}

	function hasSelectedCouriers() {
		return !!courierPicker && courierPicker.hasSelection();
	}

	function updateContinueState() {
		var disabled = stepSaving || courierSaving || (current === 'couriers' && (couriersLoading || !couriersLoaded || !hasSelectedCouriers()));
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
			if (!result || result.success === false || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			message('account', 'Account connected.', true);
			accountComplete = true;
			// Invalidate callbacks before abort: jQuery may run fail/always synchronously.
			courierLoadGeneration++;
			if (courierLoadRequest) { courierLoadRequest.abort(); }
			courierLoadRequest = null;
			couriersLoading = false;
			couriersLoaded = false;
			courierPicker = null;
			$('[data-courier-list]').text('');
			$('[data-step-target="couriers"]').removeClass('is-done');
			$root.attr('data-account-complete', '1');
			$('[data-step-target="account"]').addClass('is-done');
		});
	}

	function saveAddress() {
		var area = $('[name="origin_sub_district_id"] option:selected');
		var data = {
			origin_name: $('[name="origin_name"]').val(),
			origin_phone: $('[name="origin_phone"]').val(),
			origin_address: $('[name="origin_address"]').val(),
			origin_zip_code: $('[name="origin_zip_code"]').val(),
			origin_latitude: $('[name="origin_latitude"]').val(),
			origin_longitude: $('[name="origin_longitude"]').val(),
			origin_sub_district_id: area.val(),
			origin_sub_district_name: area.text()
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
		return courierPicker.getPayload();
	}

	function saveCouriers() {
		if (!hasSelectedCouriers()) {
			message('couriers', kiriofCourierServicesI18n.selectService);
			return $.Deferred().reject().promise();
		}
		courierSaving = true;
		courierPicker.setDisabled(true);
		$('[data-couriers-all], [data-couriers-none], [data-step-target], [data-kiriof-back]').prop('disabled', true);
		message('couriers', '');
		return post('kiriof_store_courier_whitelist', selectedCourierData()).then(function (response) {
			var result = parse(response);
			if (!result || result.success === false || Number(result.status) !== 200) {
				return $.Deferred().reject(result).promise();
			}
			message('couriers', kiriofCourierServicesI18n.saved, true);
			$('[data-step-target="couriers"]').addClass('is-done');
		}).always(function () {
			courierSaving = false;
			courierPicker.setDisabled(false);
			$('[data-couriers-all], [data-couriers-none], [data-step-target], [data-kiriof-back]').prop('disabled', false);
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

	function preventDefault(event) {
		if (event && event.preventDefault) { event.preventDefault(); }
	}

	function continueStep(event) {
		preventDefault(event);
		if (stepSaving || current === 'complete') { return; }
		if (current === 'couriers' && (!couriersLoaded || couriersLoading)) { return; }
		if (current === 'couriers' && !hasSelectedCouriers()) {
			message('couriers', kiriofCourierServicesI18n.selectService);
			updateContinueState();
			return;
		}
		var savingStep = current;
		stepSaving = true;
		var $button = $('[data-kiriof-continue]').prop('disabled', true);
		$('[data-step-target], [data-kiriof-back]').prop('disabled', true);
		message(savingStep, '');
		var request = current === 'account' ? saveAccount() : current === 'address' ? saveAddress() : current === 'couriers' ? saveCouriers() : enableShipping();
		request.done(next).fail(function (result) {
			var error = result && result.responseJSON ? parse(result.responseJSON) : result;
			var text = error && error.message ? error.message : kiriofOnboarding.saveFailed;
			if (!$('[data-step-message="' + savingStep + '"]').text()) {
				message(savingStep, text);
			}
		}).always(function () {
			stepSaving = false;
			$('[data-step-target], [data-kiriof-back]').prop('disabled', false);
			$button.prop('disabled', false);
			updateContinueState();
		});
	}

	function courierChanged() {
		$('[data-step-target="couriers"]').removeClass('is-done');
		message('couriers', '');
		updateContinueState();
	}

	function loadCouriers() {
		if (!accountComplete || couriersLoading || couriersLoaded) {
			return;
		}

		couriersLoading = true;
		updateContinueState();
		var generation = ++courierLoadGeneration;
		$('[data-courier-list]').text(kiriofCourierServicesI18n.loading);
		$('[data-couriers-all], [data-couriers-none]').prop('disabled', true);
		message('couriers', '');

		courierLoadRequest = post('kiriof_get_courier_whitelist').done(function (response) {
			if (generation !== courierLoadGeneration) { return; }
			var result = parse(response);
			if (!result || result.success === false || Number(result.status) !== 200 || !result.data) {
				var errorMessage = result && result.message ? result.message : kiriofOnboarding.networkError;
				$('[data-courier-list]').html('<p>' + $('<div>').text(errorMessage).html() + '</p>');
				message('couriers', errorMessage);
				return;
			}
			couriersLoaded = true;
			courierPicker = window.kiriofCourierServices.create($('[data-courier-list]')[0], {
				data: result.data,
				countElement: $('[data-courier-count]')[0],
				onChange: courierChanged
			});
			$('[data-couriers-all], [data-couriers-none]').prop('disabled', false);
			if (!hasSelectedCouriers()) { $('[data-step-target="couriers"]').removeClass('is-done'); }
			updateContinueState();
		}).fail(function () {
			if (generation !== courierLoadGeneration) { return; }
			$('[data-courier-list]').html('<p>' + $('<div>').text(kiriofOnboarding.networkError).html() + '</p>');
			message('couriers', kiriofOnboarding.networkError);
		}).always(function () {
			if (generation !== courierLoadGeneration) { return; }
			courierLoadRequest = null;
			couriersLoading = false;
			updateContinueState();
		});
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

	var $subdistrict = $root.find('.kiriof-onboarding-subdistrict');
	if ($subdistrict.length && typeof window.kiriofChoices === 'function' && typeof kiriofOnboarding !== 'undefined') {
		var searchTimer;
		var searchController;
		var subdistrictChoices = new window.kiriofChoices($subdistrict[0], {
			allowHTML: false,
			shouldSort: false,
			searchEnabled: true,
			searchChoices: false,
			searchFloor: 3,
			searchResultLimit: -1,
			placeholder: true,
			placeholderValue: kiriofOnboarding.subdistrictPlaceholder,
			searchPlaceholderValue: kiriofOnboarding.subdistrictPlaceholder,
			loadingText: kiriofOnboarding.subdistrictLoading,
			noResultsText: kiriofOnboarding.subdistrictNoResults,
			noChoicesText: kiriofOnboarding.subdistrictTypeMore,
			itemSelectText: ''
		});

		$subdistrict[0].addEventListener('search', function (event) {
			var term = event.detail && event.detail.value ? event.detail.value.trim() : '';
			window.clearTimeout(searchTimer);
			if (searchController) {
				searchController.abort();
			}
			if (term.length < 3) {
				subdistrictChoices.clearChoices();
				return;
			}

			searchTimer = window.setTimeout(function () {
				var controller = new window.AbortController();
				searchController = controller;
				var body = new window.URLSearchParams();
				body.set('action', 'kiriminaja_subdistrict_search');
				body.set('nonce', kiriofOnboarding.nonce);
				body.set('term', term);
				body.set('data[term]', term);
				body.set('data[search]', term);

				subdistrictChoices.setChoices(function () {
					return window.fetch(kiriofOnboarding.ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString(),
						credentials: 'same-origin',
						signal: controller.signal
					}).then(function (response) {
						return response.json().then(function (payload) {
							if (!response.ok || !payload || payload.success === false) {
								throw new Error(payload && payload.data && payload.data.message ? payload.data.message : kiriofOnboarding.subdistrictSearchFailed);
							}
							return (payload.data || []).map(function (item) {
								return { value: String(item.id), label: String(item.text) };
							});
						});
					});
				}, 'value', 'label', true).then(function () {
					subdistrictChoices.showDropdown();
				}).catch(function (error) {
					if (error.name === 'AbortError') {
						return;
					}
					subdistrictChoices.clearChoices();
					message('address', error.message || kiriofOnboarding.subdistrictSearchFailed);
					subdistrictChoices.showDropdown();
				});
			}, 250);
		});
	}

	$('[data-kiriof-continue]').on('click', continueStep);
	$('[data-kiriof-back]').on('click', function (event) {
		preventDefault(event);
		if (!stepSaving) { show(order[Math.max(order.indexOf(current) - 1, 0)]); }
	});
	$('[data-step-target]').on('click', function (event) {
		preventDefault(event);
		if (stepSaving) { return; }
		var target = $(this).data('step-target');
		if (!canVisit(target)) {
			blockNavigation(target);
			return;
		}
		show(target);
	});
	$('[data-couriers-all]').on('click', function (event) {
		preventDefault(event);
		if (!stepSaving && courierPicker) { courierPicker.setAll(true); }
	});
	$('[data-couriers-none]').on('click', function (event) {
		preventDefault(event);
		if (!stepSaving && courierPicker) { courierPicker.setAll(false); }
	});
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

	show(current);
})(jQuery);
