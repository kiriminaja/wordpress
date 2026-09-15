/* global jQuery, wp */
(function ($) {
	'use strict';

	var i18n = (window.kiriofChangeOrigin && window.kiriofChangeOrigin.i18n) || {};
	var pageScrollState = null;

	function lockPageScroll() {
		if (pageScrollState) {
			return;
		}

		pageScrollState = {
			scrollTop: window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0,
			scrollLeft: window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft || 0,
		};
	}

	function unlockPageScroll() {
		if (!pageScrollState) {
			return;
		}

		var state = pageScrollState;
		pageScrollState = null;
		window.scrollTo(state.scrollLeft, state.scrollTop);
	}

	function closeModal($modal) {
		$modal.remove();
		unlockPageScroll();
	}

	function text(key, fallback) {
		return i18n[key] || fallback;
	}

	function optionDisplayLabel(option) {
		var courier = $.trim((option && option.courier) || '');
		var service = $.trim((option && option.service) || '');
		if (!service || courier.toLowerCase().indexOf(service.toLowerCase()) !== -1) {
			return courier;
		}
		return $.trim(courier + ' ' + service);
	}

	function applyCourierSelection($modal) {
		var $selectedOption = $modal.find('input[name="courier_option"]:checked');
		var selected = $selectedOption.attr('data-option') || '';
		if (selected) {
			var base = ($modal.data('shipping-check') || {}).comparison || {};
			var option = null;
			try {
				option = JSON.parse($('<textarea>').html(selected).val() || '{}');
			} catch (error) {
				option = null;
			}
			if (option && typeof option === 'object') {
				$modal.find('.kiriof-change-origin-breakdown').html(renderOrderBreakdown(option)).show();
			}
		}
		var requiresConsent = $selectedOption.attr('data-replacement') === '1';
		$modal.find('.kiriof-replacement-consent-wrap').toggle(requiresConsent);
		$modal.find('#kiriof-change-origin-confirm').prop('disabled', !$selectedOption.length || (requiresConsent && !$modal.find('.kiriof-replacement-consent').prop('checked')));
	}

	function buildReplacementComparison(base, option) {
		var rawPrice = Math.max(0, parseFloat(option.raw_price) || 0);
		var optionDiscount = Math.max(0, parseFloat(option.discount_amount) || 0);
		var previousPaidShipping = parseFloat(base.previous_paid_shipping) || 0;
		var newDiscount = Math.min(optionDiscount, rawPrice);
		var newPaidShipping = Math.max(0, rawPrice - newDiscount);
		var next = $.extend({}, base, {
			available: true,
			label: optionDisplayLabel(option),
			new_courier: optionDisplayLabel(option),
			new_raw_shipping: rawPrice,
			new_discount: newDiscount,
			new_paid_shipping: newPaidShipping,
			new_discounted_shipping: newPaidShipping,
			new_total_shipping: rawPrice + (parseFloat(base.insurance_cost) || 0) + (parseFloat(base.cod_fee) || 0),
		});
		next.total_delta = newPaidShipping - previousPaidShipping;
		next.new_total = (parseFloat(base.previous_total) || 0) + next.total_delta;
		return next;
	}

	function renderOrderBreakdown(comparison) {
		if (!comparison || !comparison.available) {
			return '';
		}

		var previousCourier = comparison.previous_courier || '';
		var newCourier = comparison.new_courier || previousCourier;
		var previousDiscount = parseFloat(comparison.previous_discount) || 0;
		var newDiscount = parseFloat(comparison.new_discount) || 0;
		var discountRow = '';

		if (previousDiscount !== 0 || newDiscount !== 0) {
			discountRow = '<dt>' + text('shippingDiscount', 'Shipping discount') + '</dt><dd>' + changeValue(previousDiscount, newDiscount) + '</dd>';
		}

		return '<div class="kiriof-change-origin-order-breakdown">' +
			'<h2>' + text('orderBreakdown', 'Order summary') + '</h2>' +
			'<dl>' +
			'<dt>' + text('courier', 'Courier') + '</dt><dd>' + $('<span>').text(previousCourier + ' → ' + newCourier).html() + '</dd>' +
			'<dt>' + text('subTotal', 'Sub Total') + '</dt><dd>' + money(comparison.previous_subtotal) + '</dd>' +
			'<dt>' + text('shipping', 'Shipping') + '</dt><dd>' + changeValue(comparison.previous_paid_shipping, comparison.new_paid_shipping) + '</dd>' +
			discountRow +
			'<dt class="kiriof-change-origin-order-total"><strong>' + text('orderTotal', 'Order total') + '</strong></dt><dd class="kiriof-change-origin-order-total"><strong>' + changeValue(comparison.previous_total, comparison.new_total) + '</strong></dd>' +
			'</dl>' +
		'</div>';
	}

	function money(value) {
		return 'Rp' + Math.round(parseFloat(value) || 0).toLocaleString('id-ID');
	}

	function changeValue(previous, next) {
		previous = parseFloat(previous) || 0;
		next = parseFloat(next) || 0;
		var arrow = next > previous ? '↑' : (next < previous ? '↓' : '→');

		return money(previous) + ' ' + arrow + ' ' + money(next);
	}

	function initializeModal($modal, data) {
		var currentLocationId = String(parseInt(data.current_location_id, 10) || 0);
		var $originRadios = $modal.find('input[name="location_id"]');
		$modal.find('.kiriof-origin-radio-group .kiriof-radio-card[data-location-id="' + currentLocationId + '"]').remove();
		var hasAlternatives = $modal.find('.kiriof-origin-radio-group .kiriof-radio-card:not(.kiriof-radio-card-current)').length > 0;
		$modal.find('.kiriof-change-origin-empty').toggle(!hasAlternatives);
		$modal.toggleClass('kiriof-change-origin-empty-state', !hasAlternatives);

		$modal.find('#kiriof-change-origin-confirm').prop('disabled', true);
		$modal.find('.kiriof-change-origin-result').hide();
		$modal.find('.kiriof-change-origin-replacement, .kiriof-change-origin-breakdown').hide().empty();
		$modal.data('shipping-check', { comparison: null });
		$modal.off('.kiriofChangeOrigin');

		$modal.on('click.kiriofChangeOrigin', '.modal-close', function () {
			closeModal($modal);
		});
		$modal.on('click.kiriofChangeOrigin', function (event) {
			if (event.target === this) {
				closeModal($modal);
			}
		});

		function resetShippingResult() {
			$modal.find('#kiriof-change-origin-confirm').prop('disabled', true);
			$modal.find('.kiriof-change-origin-loading').hide().find('.spinner').removeClass('is-active');
			$modal.find('.kiriof-change-origin-result').hide().empty().removeClass('notice-success notice-error');
			$modal.find('.kiriof-change-origin-replacement, .kiriof-change-origin-breakdown').hide().empty();
			$modal.find('.kiriof-replacement-consent').prop('checked', false);
			$modal.data('shipping-check', { comparison: null });
		}

		function checkShipping() {
			var $loading = $modal.find('.kiriof-change-origin-loading');
			var $spinner = $loading.find('.spinner');
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($modal.find('input[name="location_id"]:checked').val(), 10) || 0;

			if (!locationId || String(locationId) === currentLocationId) {
				resetShippingResult();
				return;
			}

			$modal.find('#kiriof-change-origin-confirm').prop('disabled', true);
			$spinner.addClass('is-active');
			$loading.show();
			$modal.data('shipping-check', { comparison: null });
			$result
				.removeClass('notice-success notice-error')
				.html('<p>' + text('checkingShipping', 'Checking shipping route...') + '</p>')
				.show();

			$.post((window.kiriofChangeOrigin && window.kiriofChangeOrigin.ajaxUrl) || window.ajaxurl, {
				action: 'kiriof_change_origin_check',
				order_id: data.order_id,
				location_id: locationId,
				nonce: $('.kiriof-change-origin-button[data-ka-order-id="' + data.order_id + '"]').data('nonce'),
			})
				.done(function (response) {
					var payload = response && response.data && typeof response.data === 'object' ? response.data : null;
					var comparison = payload && payload.comparison && typeof payload.comparison === 'object' ? payload.comparison : null;
					if (response && response.success && comparison) {
						var html = '<p>' + $('<span>').text(comparison.label || payload.message || '').html() + '</p>';
						var $replacement = $modal.find('.kiriof-change-origin-replacement');
						var $breakdown = $modal.find('.kiriof-change-origin-breakdown');
						$replacement.empty().hide();
						$breakdown.empty().hide();
						var courierOptions = Array.isArray(payload.options) ? payload.options.slice() : [];
						if (comparison.available) {
							var matchedIndex = -1;
							$.each(courierOptions, function (index, option) {
								if ((option.service_code || '') === (comparison.service_code || '') && (option.service_name || '') === (comparison.service_name || '')) {
									matchedIndex = index;
								}
							});
							if (matchedIndex >= 0) {
								courierOptions.unshift(courierOptions.splice(matchedIndex, 1)[0]);
							} else {
								courierOptions.unshift(comparison);
							}
						} else {
							courierOptions = Array.isArray(payload.replacement_options) ? payload.replacement_options : [];
						}
						var courierHtml = '<div class="kiriof-courier-radio-group kiriof-radio-card-group" role="radiogroup"><p class="kiriof-courier-radio-title">' + text('courier', 'Courier') + '</p>';
						$.each(courierOptions, function (index, option) {
								if (!option || typeof option !== 'object') {
									return;
								}
							var sameCourier = comparison.available && (option.service_code || '') === (comparison.service_code || '') && (option.service_name || '') === (comparison.service_name || '');
							var optionComparison = sameCourier ? comparison : buildReplacementComparison(comparison, option);
							var checked = index === 0 ? ' checked' : '';
							var replacement = sameCourier ? '0' : '1';
							courierHtml += '<label class="kiriof-radio-card kiriof-courier-radio-card"><input type="radio" name="courier_option" value="' + $('<span>').text((option.service_code || '') + '|' + (option.service_name || '')).html() + '" data-replacement="' + replacement + '" data-option="' + $('<span>').text(JSON.stringify(optionComparison)).html() + '"' + checked + '><span class="kiriof-courier-radio-row"><span class="kiriof-courier-radio-name">' + $('<span>').text(optionDisplayLabel(option)).html() + '</span><strong class="kiriof-courier-radio-price">' + $('<span>').text(option.price || money(option.raw_price)).html() + '</strong></span></label>';
						});
						courierHtml += '</div><p class="kiriof-replacement-consent-wrap" style="display:none;"><label><input type="checkbox" class="kiriof-replacement-consent"> ' + text('replacementConsent', 'I consent to use this replacement courier.') + '</label></p>';
						$replacement.html(courierHtml).show();
						setResult($result, true, html);
						$modal.data('shipping-check', payload);
						applyCourierSelection($modal);
					} else {
						setResult($result, false, '<p>' + ((payload && payload.message) || text('checkFailed', 'Shipping check failed.')) + '</p>');
					}
				})
				.fail(function () {
					setResult($result, false, '<p>' + text('checkFailed', 'Shipping check failed.') + '</p>');
				})
				.always(function () {
					$spinner.removeClass('is-active');
					$loading.hide();
				});
		}

		$originRadios.on('change.kiriofChangeOrigin', checkShipping);
		$modal.on('change.kiriofChangeOrigin', 'input[name="courier_option"], .kiriof-replacement-consent', function () {
			applyCourierSelection($modal);
		});

		$modal.on('click.kiriofChangeOrigin', '#kiriof-change-origin-confirm', function () {
			var $confirm = $(this);
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($modal.find('input[name="location_id"]:checked').val(), 10) || 0;
			var shippingCheck = $modal.data('shipping-check') || {};
			var $selectedCourier = $modal.find('input[name="courier_option"]:checked');
			var serviceParts = String($selectedCourier.val() || '').split('|');
			var selectedOption = {};
			try { selectedOption = JSON.parse($('<textarea>').html($selectedCourier.attr('data-option') || '').val() || '{}'); } catch (error) { selectedOption = {}; }
			var selectedPrice = parseFloat(selectedOption.new_raw_shipping || selectedOption.raw_price) || 0;
			var selectedDiscount = parseFloat(selectedOption.new_discount || selectedOption.discount_amount) || 0;
			var isReplacement = $selectedCourier.attr('data-replacement') === '1';

			if (!locationId || $confirm.prop('disabled')) {
				return;
			}

			$confirm.prop('disabled', true);
			$.post((window.kiriofChangeOrigin && window.kiriofChangeOrigin.ajaxUrl) || window.ajaxurl, {
				action: 'kiriof_change_origin',
				order_id: data.order_id,
				location_id: locationId,
				courier_service: serviceParts[0],
				courier_service_name: serviceParts[1],
				courier_price: selectedPrice,
				courier_discount: selectedDiscount,
				courier_consent: isReplacement && $modal.find('.kiriof-replacement-consent').prop('checked') ? 1 : 0,
				nonce: $('.kiriof-change-origin-button[data-ka-order-id="' + data.order_id + '"]').data('nonce'),
			})
				.done(function (response) {
					if (response && response.success) {
						window.location.reload();
					} else {
						setResult($result, false, '<p>' + ((response && response.data && response.data.message) || text('updateFailed', 'Failed to update the shipment origin.')) + '</p>');
						$confirm.prop('disabled', false);
					}
				})
				.fail(function () {
					setResult($result, false, '<p>' + text('updateFailed', 'Failed to update the shipment origin.') + '</p>');
					$confirm.prop('disabled', false);
				});
		});
	}

	function setResult($result, ok, html) {
		$result
			.removeClass('notice-success notice-error')
			.addClass(ok ? 'notice-success' : 'notice-error')
			.html(html)
			.show();
	}

	function openModal(button) {
		var $button = $(button);
		var orderId = $button.data('ka-order-id') || '';
		var currentOrigin = $button.data('current-origin') || '';
		var currentOriginAddress = $button.data('current-origin-address') || '';
		var currentLocationId = parseInt($button.data('current-location-id'), 10) || 0;

		var data = { order_id: orderId, current_origin: currentOrigin, current_origin_address: currentOriginAddress, current_location_id: currentLocationId };
		var template = $('#tmpl-kiriof-modal-change-origin').html() || '';
		if (!template) {
			return;
		}

		var $existingModal = $('.kiriof-change-origin-modal');
		if ($existingModal.length) {
			closeModal($existingModal);
		}
		template = template.replace(/\{\{ data\.order_id \}\}/g, $('<span>').text(orderId).html());
		template = template.replace(/\{\{ data\.current_origin \}\}/g, $('<span>').text(currentOrigin).html());
		template = template.replace(/\{\{ data\.current_origin_address \}\}/g, $('<span>').text(currentOriginAddress).html());
		template = template.replace(/\{\{ data\.current_location_id \}\}/g, String(currentLocationId));
		var $modal = $(template).appendTo('body');
		lockPageScroll();
		initializeModal($modal, data);
		$modal.attr('aria-hidden', 'false');
	}

	$(document).on('click', '.kiriof-change-origin-button', function (event) {
		event.preventDefault();
		openModal(this);
	});

	$(document).on('click', '.order-preview:not(.disabled)', function (event) {
		event.preventDefault();

		var $button = $(this);
		var orderId = $button.data('orderId');
		var config = window.kiriofChangeOrigin || {};

		if (!orderId || !config.ajaxUrl || !config.previewNonce) {
			return false;
		}

		$button.addClass('disabled');
		$.ajax({
			url: config.ajaxUrl,
			data: {
				action: 'woocommerce_get_order_details',
				order_id: orderId,
				security: config.previewNonce,
			},
			type: 'GET',
		})
			.done(function (response) {
				if (!response || !response.success) {
					window.alert(response && response.data && response.data.message ? response.data.message : 'Unable to load order preview.');
					return;
				}

				if (typeof $button.WCBackboneModal !== 'function') {
					window.alert('WooCommerce order preview is unavailable on this page.');
					return;
				}

				$(document.body).WCBackboneModal({
					template: 'wc-modal-view-order',
					variable: response.data,
				});
			})
			.always(function () {
				$button.removeClass('disabled');
			});

		return false;
	});


	window.kjShowChangeOriginModal = openModal;
})(jQuery);
