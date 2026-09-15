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
		var courierKey = $selectedOption.val() || '';
		var optionMap = $modal.data('courier-comparisons') || {};
		var option = optionMap[courierKey] || null;
		if (option && typeof option === 'object') {
			$modal.find('.kiriof-change-origin-breakdown').html(renderOrderBreakdown(option)).show();
			var impact = option.label || '';
			var selectedName = option.new_courier || option.label || '';
			var selectedPrice = money(option.new_paid_shipping);
			$modal.find('.kiriof-courier-selection-summary').html(
				'<span class="kiriof-compact-selection-copy"><strong>' + $('<span>').text(selectedName).html() + '</strong><small>' + $('<span>').text(selectedPrice).html() + '</small>' +
				(impact ? '<em class="kiriof-pricing-impact">' + $('<span>').text(impact).html() + '</em>' : '') + '</span>' +
				'<button type="button" class="button button-small kiriof-courier-toggle">' + text('change', 'Change') + '</button>'
			).closest('.kiriof-courier-selection-section').show();
		} else {
			$modal.find('.kiriof-change-origin-breakdown').hide().empty();
			$modal.find('.kiriof-courier-selection-section').hide();
		}
		var requiresConsent = $selectedOption.attr('data-replacement') === '1';
		var isBlocked = !!(option && option.is_total_blocked);
		if (!requiresConsent) {
			$modal.find('.kiriof-replacement-consent').prop('checked', true);
		} else if ($modal.data('courier-consent-granted')) {
			$modal.find('.kiriof-replacement-consent').prop('checked', true);
		}
		$modal.find('.kiriof-replacement-consent-wrap').toggle(requiresConsent);
		$modal.find('#kiriof-change-origin-confirm').prop('disabled', isBlocked || !$selectedOption.length || (requiresConsent && !$modal.find('.kiriof-replacement-consent').prop('checked')));
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
		var previousTotal = parseFloat(base.previous_total) || 0;
		var nonShippingTotal = parseFloat(base.previous_non_shipping_total);
		if (!Number.isFinite(nonShippingTotal)) {
			nonShippingTotal = Math.max(0, previousTotal - (parseFloat(base.previous_order_shipping) || previousPaidShipping));
		}
		var adjustedTotal = previousTotal + (newPaidShipping - previousPaidShipping);
		var rawNewTotal = nonShippingTotal + newPaidShipping;
		next.total_was_clamped = adjustedTotal < 0;
		next.is_total_blocked = adjustedTotal < 0;
		next.required_refund = Math.max(0, previousPaidShipping - newPaidShipping);
		next.new_total = Math.max(0, rawNewTotal);
		next.total_delta = next.new_total - previousTotal;
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
		var totalWarning = comparison.is_total_blocked
			? '<p class="kiriof-total-anomaly"><strong>' + text('changeBlocked', 'Change cannot be processed.') + '</strong><br>' + text('refundRequired', 'The adjusted order total would be below Rp0. Reconcile or refund the buyer {amount} before making this change.').replace('{amount}', money(comparison.required_refund)) + '</p>'
			: '';
		var totalValue = comparison.is_total_blocked
			? '<span class="kiriof-change-value kiriof-change-value-blocked">' + money(comparison.previous_total) + ' <strong>→ ' + text('blocked', 'Blocked') + '</strong></span>'
			: changeValue(comparison.previous_total, comparison.new_total);

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
			'<dt class="kiriof-change-origin-order-total"><strong>' + text('orderTotal', 'Order total') + '</strong></dt><dd class="kiriof-change-origin-order-total"><strong>' + totalValue + '</strong></dd>' +
			'</dl>' +
			totalWarning +
		'</div>';
	}

	function money(value) {
		return 'Rp' + Math.round(Math.max(0, parseFloat(value) || 0)).toLocaleString('id-ID');
	}

	function changeValue(previous, next) {
		previous = parseFloat(previous) || 0;
		next = parseFloat(next) || 0;
		var arrow = next > previous ? '↑' : (next < previous ? '↓' : '→');

		var tone = next > previous ? 'increase' : (next < previous ? 'decrease' : 'neutral');
		return '<span class="kiriof-change-value kiriof-change-value-' + tone + '">' + money(previous) + ' <strong>' + arrow + ' ' + money(next) + '</strong></span>';
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
			$modal.find('.kiriof-courier-selection-section').hide();
			$modal.find('.kiriof-replacement-consent').prop('checked', !!$modal.data('courier-consent-granted'));
			$modal.data('shipping-check', { comparison: null });
			$modal.data('courier-comparisons', {});
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
			$result.removeClass('notice-success notice-error').hide().empty();

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
						var $replacement = $modal.find('.kiriof-change-origin-replacement');
						var $breakdown = $modal.find('.kiriof-change-origin-breakdown');
						$replacement.empty().hide();
						$breakdown.empty().hide();
						var courierOptions = Array.isArray(payload.options) ? payload.options.slice() : [];
						var courierComparisons = {};
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
						var courierHtml = '<div class="kiriof-courier-radio-group kiriof-radio-card-group" role="radiogroup">';
						$.each(courierOptions, function (index, option) {
								if (!option || typeof option !== 'object') {
									return;
								}
							var sameCourier = comparison.available && (option.service_code || '') === (comparison.service_code || '') && (option.service_name || '') === (comparison.service_name || '');
							var optionComparison = sameCourier ? comparison : buildReplacementComparison(comparison, option);
							var checked = comparison.available && index === 0 ? ' checked' : '';
							var replacement = sameCourier ? '0' : '1';
							var courierKey = String(option.service_code || '') + '|' + String(option.service_name || '');
							courierComparisons[courierKey] = optionComparison;
							courierHtml += '<label class="kiriof-radio-card kiriof-courier-radio-card"><input type="radio" name="courier_option" value="' + $('<span>').text(courierKey).html() + '" data-replacement="' + replacement + '"' + checked + '><span class="kiriof-courier-radio-row"><span class="kiriof-courier-radio-name">' + $('<span>').text(optionDisplayLabel(option)).html() + '</span><strong class="kiriof-courier-radio-price">' + $('<span>').text(option.price || money(option.raw_price)).html() + '</strong></span></label>';
						});
						courierHtml += '</div><button type="button" class="button button-small kiriof-courier-collapse">' + text('collapse', 'Collapse') + '</button>';
						$replacement.html(courierHtml).show();
						$modal.data('courier-comparisons', courierComparisons);
						$result.hide().empty().removeClass('notice-success notice-error');
						$modal.data('shipping-check', payload);
						applyCourierSelection($modal);
						$replacement.toggle(!comparison.available);
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
		$modal.on('click.kiriofChangeOrigin', '.kiriof-origin-toggle', function () {
			$modal.find('.kiriof-origin-choice-panel').slideDown(120);
		});
		$modal.on('click.kiriofChangeOrigin', '.kiriof-origin-collapse', function () {
			$modal.find('input[name="location_id"][data-current="1"]').prop('checked', true);
			$modal.find('.kiriof-origin-selection-name').text(data.current_origin);
			$modal.find('.kiriof-origin-selection-address').text(data.current_origin_address);
			resetShippingResult();
			$modal.find('.kiriof-origin-choice-panel').slideUp(120);
		});
		$modal.on('click.kiriofChangeOrigin', '.kiriof-courier-toggle', function () {
			$modal.find('.kiriof-change-origin-replacement').slideDown(120);
		});
		$modal.on('click.kiriofChangeOrigin', '.kiriof-courier-collapse', function () {
			if (!$modal.find('input[name="courier_option"]:checked').length) {
				return;
			}
			$modal.find('.kiriof-change-origin-replacement').slideUp(120);
		});
		$modal.on('change.kiriofChangeOrigin', 'input[name="courier_option"], .kiriof-replacement-consent', function () {
			if ($(this).hasClass('kiriof-replacement-consent') && $(this).prop('checked')) {
				$modal.data('courier-consent-granted', true);
			}
			applyCourierSelection($modal);
		});
		$modal.on('change.kiriofChangeOrigin', 'input[name="location_id"]', function () {
			var $card = $(this).closest('.kiriof-radio-card');
			$modal.find('.kiriof-origin-selection-name').text($card.find('strong').first().text());
			$modal.find('.kiriof-origin-selection-address').text($card.find('small').first().text());
			$modal.find('.kiriof-origin-choice-panel').slideUp(120);
		});

		$modal.on('click.kiriofChangeOrigin', '#kiriof-change-origin-confirm', function () {
			var $confirm = $(this);
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($modal.find('input[name="location_id"]:checked').val(), 10) || 0;
			var shippingCheck = $modal.data('shipping-check') || {};
			var $selectedCourier = $modal.find('input[name="courier_option"]:checked');
			var serviceParts = String($selectedCourier.val() || '').split('|');
			var selectedOption = ($modal.data('courier-comparisons') || {})[$selectedCourier.val() || ''] || {};
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
