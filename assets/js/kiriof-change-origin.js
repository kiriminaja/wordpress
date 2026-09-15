/* global jQuery, wp */
(function ($) {
	'use strict';

	var i18n = (window.kiriofChangeOrigin && window.kiriofChangeOrigin.i18n) || {};
	var pageScrollState = null;

	function lockPageScroll() {
		if (pageScrollState) {
			return;
		}

		var body = document.body;
		var html = document.documentElement;
		var scrollTop = window.pageYOffset || html.scrollTop || body.scrollTop || 0;
		var scrollLeft = window.pageXOffset || html.scrollLeft || body.scrollLeft || 0;

		pageScrollState = {
			scrollTop: scrollTop,
			scrollLeft: scrollLeft,
			htmlOverflow: html.style.overflow,
			bodyOverflow: body.style.overflow,
		};

		$('html, body').addClass('kiriof-change-origin-scroll-locked');
		html.style.overflow = 'hidden';
		body.style.overflow = 'hidden';
	}

	function unlockPageScroll() {
		if (!pageScrollState) {
			return;
		}

		var body = document.body;
		var html = document.documentElement;
		var state = pageScrollState;
		pageScrollState = null;

		html.style.overflow = state.htmlOverflow;
		body.style.overflow = state.bodyOverflow;
		$('html, body').removeClass('kiriof-change-origin-scroll-locked');
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

	function getSelectedReplacement($replacement) {
		var select = $replacement.get(0);
		var selectedIndex = select ? select.selectedIndex : -1;
		if (!select || selectedIndex < 1) {
			return null;
		}

		return $(select.options[selectedIndex]);
	}

	function applyReplacementSelection($modal, selectedData) {
		var $replacement = $modal.find('.kiriof-replacement-courier');
		if (selectedData && selectedData.id !== undefined) {
			$replacement.val(String(selectedData.id));
		}
		var $selectedOption = getSelectedReplacement($replacement);
		var selected = $selectedOption ? $selectedOption.attr('data-option') : '';
		if (selected) {
			var base = ($modal.data('shipping-check') || {}).comparison || {};
			var option = null;
			try {
				option = JSON.parse($('<textarea>').html(selected).val() || '{}');
			} catch (error) {
				option = null;
			}
			if (option && typeof option === 'object') {
				$modal.find('.kiriof-change-origin-breakdown').html(renderOrderBreakdown(buildReplacementComparison(base, option))).show();
			}
		}
		$modal.find('#kiriof-change-origin-confirm').prop('disabled', !$selectedOption || !$modal.find('.kiriof-replacement-consent').prop('checked'));
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
		var $select = $modal.find('select[name="location_id"]');
		function removeCurrentOriginOption() {
			var currentLocationId = String(parseInt(data.current_location_id, 10) || 0);
			if (currentLocationId !== '0') {
				$select.find('option').filter(function () {
					return String($(this).val()) === currentLocationId;
				}).remove();
			}
		}

		removeCurrentOriginOption();
		var hasAlternatives = $select.find('option[value!=""]').length > 0;
		$select.toggle(hasAlternatives);
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

		function checkShipping() {
			var $loading = $modal.find('.kiriof-change-origin-loading');
			var $spinner = $loading.find('.spinner');
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($select.val(), 10) || 0;

			if (!locationId) {
				$modal.find('#kiriof-change-origin-confirm').prop('disabled', true);
				$result.hide().empty();
				$loading.hide();
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
						if (comparison.available) {
							$breakdown.html(renderOrderBreakdown(comparison)).show();
							html += '<input type="hidden" class="kiriof-selected-service-code" value="' + $('<span>').text(comparison.service_code || '').html() + '">';
							html += '<input type="hidden" class="kiriof-selected-service-name" value="' + $('<span>').text(comparison.service_name || '').html() + '">';
							html += '<input type="hidden" class="kiriof-selected-price" value="' + (parseFloat(comparison.raw_price) || 0) + '">';
							html += '<input type="hidden" class="kiriof-selected-discount" value="' + (parseFloat(comparison.new_discount) || 0) + '">';
						} else {
							var replacementHtml = '<p><label>' + text('replacementCourier', 'Select a replacement courier with your consent.') + '</label><select class="kiriof-replacement-courier" style="width:100%;"><option value="">' + text('selectCourier', 'Select courier') + '</option>';
							$.each(Array.isArray(payload.replacement_options) ? payload.replacement_options : [], function (index, option) {
								if (!option || typeof option !== 'object') {
									return;
								}
								replacementHtml += '<option value="' + $('<span>').text(option.service_code + '|' + option.service_name).html() + '" data-price="' + (parseFloat(option.raw_price) || 0) + '" data-option="' + $('<span>').text(JSON.stringify(option)).html() + '">' + $('<span>').text(optionDisplayLabel(option) + ' — ' + option.price).html() + '</option>';
							});
							replacementHtml += '</select></p><p><label><input type="checkbox" class="kiriof-replacement-consent"> ' + text('replacementConsent', 'I consent to use this replacement courier.') + '</label></p>';
							$replacement.html(replacementHtml).show();
						}
						setResult($result, true, html);
						$modal.find('#kiriof-change-origin-confirm').prop('disabled', !comparison.available);
						$modal.data('shipping-check', payload);
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

		$select.on('change.kiriofChangeOrigin', checkShipping);
		$modal.on('change.kiriofChangeOrigin', '.kiriof-replacement-courier, .kiriof-replacement-consent', function () {
			applyReplacementSelection($modal, null);
		});

		$modal.on('click.kiriofChangeOrigin', '#kiriof-change-origin-confirm', function () {
			var $confirm = $(this);
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($select.val(), 10) || 0;
			var shippingCheck = $modal.data('shipping-check') || {};
			var comparison = shippingCheck.comparison || {};
			var $replacement = $modal.find('.kiriof-replacement-courier');
			var $selectedReplacement = getSelectedReplacement($replacement);
			var serviceParts = $selectedReplacement ? String($selectedReplacement.val() || '').split('|') : [ comparison.service_code || '', comparison.service_name || '' ];
			var selectedPrice = $selectedReplacement ? parseFloat($selectedReplacement.attr('data-price')) || 0 : parseFloat($modal.find('.kiriof-selected-price').val()) || 0;
			var selectedOption = null;
			var selectedDiscount = parseFloat($modal.find('.kiriof-selected-discount').val()) || 0;
			if ($selectedReplacement) {
				try {
					selectedOption = JSON.parse($('<textarea>').html($selectedReplacement.attr('data-option') || '').val() || '{}');
				} catch (error) {
					selectedOption = null;
				}
				selectedDiscount = selectedOption && typeof selectedOption === 'object' ? parseFloat(selectedOption.discount_amount) || 0 : 0;
			}

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
				courier_consent: $selectedReplacement && $modal.find('.kiriof-replacement-consent').prop('checked') ? 1 : 0,
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
