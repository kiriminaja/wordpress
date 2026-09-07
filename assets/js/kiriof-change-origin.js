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
		var scrollbarWidth = Math.max(0, window.innerWidth - html.clientWidth);
		var computedPaddingRight = parseFloat(window.getComputedStyle(body).paddingRight) || 0;

		pageScrollState = {
			scrollTop: scrollTop,
			scrollLeft: scrollLeft,
			htmlOverflow: html.style.overflow,
			bodyPosition: body.style.position,
			bodyTop: body.style.top,
			bodyLeft: body.style.left,
			bodyRight: body.style.right,
			bodyWidth: body.style.width,
			bodyPaddingRight: body.style.paddingRight,
		};

		$('html, body').addClass('kiriof-change-origin-scroll-locked');
		html.style.overflow = 'hidden';
		body.style.position = 'fixed';
		body.style.top = '-' + scrollTop + 'px';
		body.style.left = '-' + scrollLeft + 'px';
		body.style.right = '0';
		body.style.width = '100%';
		if (scrollbarWidth) {
			body.style.paddingRight = (computedPaddingRight + scrollbarWidth) + 'px';
		}
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
		body.style.position = state.bodyPosition;
		body.style.top = state.bodyTop;
		body.style.left = state.bodyLeft;
		body.style.right = state.bodyRight;
		body.style.width = state.bodyWidth;
		body.style.paddingRight = state.bodyPaddingRight;
		$('html, body').removeClass('kiriof-change-origin-scroll-locked');
		window.scrollTo(state.scrollLeft, state.scrollTop);
	}

	function closeModal($modal) {
		if ($.fn.select2) {
			$modal.find('.select2-hidden-accessible').each(function () {
				if ($(this).data('select2')) {
					$(this).select2('destroy');
				}
			});
		}
		$modal.remove();
		unlockPageScroll();
	}

	function text(key, fallback) {
		return i18n[key] || fallback;
	}

	function buildReplacementComparison(base, option) {
		var rawPrice = parseFloat(option.raw_price) || 0;
		var previousDiscount = parseFloat(base.previous_discount) || 0;
		var previousPaidShipping = parseFloat(base.previous_paid_shipping) || 0;
		var newDiscount = Math.min(previousDiscount, rawPrice);
		var newPaidShipping = Math.max(0, rawPrice - newDiscount);
		var next = $.extend({}, base, {
			available: true,
			label: option.courier + ' ' + option.service,
			new_courier: option.courier + ' ' + option.service,
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

		return '<div class="kiriof-change-origin-order-breakdown">' +
			'<h2>' + text('orderBreakdown', 'Order summary') + '</h2>' +
			'<dl>' +
			'<dt>' + text('subTotal', 'Sub Total') + '</dt><dd>' + money(comparison.previous_subtotal) + '</dd>' +
			'<dt>' + text('totalShipping', 'Total Shipping') + '</dt><dd>' + money(comparison.new_total_shipping) + '</dd>' +
			'<dt>' + text('shipping', 'Shipping') + '</dt><dd>' + money(comparison.new_raw_shipping) + '</dd>' +
			'<dt>' + text('shippingDiscountFromKiriminAja', 'Shipping Discount (from KiriminAja)') + '</dt><dd>-' + money(comparison.new_discount) + '</dd>' +
			'<dt>' + text('discountedShipping', 'Discounted Shipping') + '</dt><dd>' + money(comparison.new_discounted_shipping) + '</dd>' +
			'<dt class="kiriof-change-origin-order-total"><strong>' + text('orderTotal', 'Total') + '</strong></dt><dd class="kiriof-change-origin-order-total"><strong>' + money(comparison.new_total) + '</strong></dd>' +
			'</dl>' +
			renderImpactDetails(comparison) +
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

	function renderImpactDetails(comparison) {
		if (!comparison || !comparison.available) {
			return '';
		}

		var delta = parseFloat(comparison.total_delta) || 0;
		var direction = delta > 0 ? text('priceIncrease', 'increases') : (delta < 0 ? text('priceDecrease', 'decreases') : text('noChange', 'does not change'));
		var deltaText = delta === 0 ? text('noChange', 'No change') : money(Math.abs(delta));
		var discountDelta = (parseFloat(comparison.new_discount) || 0) - (parseFloat(comparison.previous_discount) || 0);

		return '<div class="kiriof-change-origin-impact-content">' +
			'<h2>' + text('priceImpact', 'Order price impact') + '</h2>' +
			'<dl>' +
			'<dt>' + text('previousCourier', 'Previous courier') + '</dt><dd>' + $('<span>').text(comparison.previous_courier || '').html() + '</dd>' +
			'<dt>' + text('newCourier', 'New courier') + '</dt><dd>' + $('<span>').text(comparison.new_courier || comparison.previous_courier || '').html() + '</dd>' +
			'<dt>' + text('shipping', 'Shipping') + '</dt><dd>' + changeValue(comparison.previous_paid_shipping, comparison.new_paid_shipping) + '</dd>' +
			'<dt>' + text('shippingDiscount', 'Shipping discount') + '</dt><dd>' + changeValue(comparison.previous_discount, comparison.new_discount) + (discountDelta ? ' (' + (discountDelta > 0 ? '+' : '-') + money(Math.abs(discountDelta)) + ')' : '') + '</dd>' +
			'<dt>' + text('orderTotal', 'Order total') + '</dt><dd>' + changeValue(comparison.previous_total, comparison.new_total) + '</dd>' +
			'</dl><p class="kiriof-change-origin-impact-delta">' + deltaText + ' — ' + direction + '</p>' +
		'</div>';
	}

	function initializeModal($modal, data) {
		var $select = $modal.find('select[name="location_id"]');
		function removeCurrentOriginOption() {
			$select.find('option[value="' + data.current_location_id + '"]').remove();
			$select.find('option').filter(function () {
			var optionName = $.trim($(this).text()).toLowerCase();
			var currentName = $.trim(data.current_origin).toLowerCase();
			return optionName && currentName && (optionName === currentName || optionName.indexOf(currentName) === 0 || currentName.indexOf(optionName) === 0);
			}).remove();
		}

		if ($.fn.select2 && $select.length && $select.data('select2')) {
			$select.select2('destroy');
		}
		removeCurrentOriginOption();
		var hasAlternatives = $select.find('option[value!=""]').length > 0;
		$select.toggle(hasAlternatives);
		$modal.find('.kiriof-change-origin-empty').toggle(!hasAlternatives);
		$modal.toggleClass('kiriof-change-origin-empty-state', !hasAlternatives);

		if ($.fn.select2 && $select.length && hasAlternatives) {
			$select.select2({
				width: '100%',
				placeholder: $select.data('placeholder') || '',
			});
		}

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

			$.post(window.ajaxurl, {
				action: 'kiriof_change_origin_check',
				order_id: data.order_id,
				location_id: locationId,
				nonce: $('.kiriof-change-origin-button[data-ka-order-id="' + data.order_id + '"]').data('nonce'),
			})
				.done(function (response) {
					if (response && response.success) {
						var comparison = response.data.comparison || {};
						var html = '<p>' + $('<span>').text(comparison.label || response.data.message).html() + '</p>';
						var $replacement = $modal.find('.kiriof-change-origin-replacement');
						var $breakdown = $modal.find('.kiriof-change-origin-breakdown');
						$replacement.empty().hide();
						$breakdown.empty().hide();
						if (comparison.available) {
							$breakdown.html(renderOrderBreakdown(comparison)).show();
							html += '<input type="hidden" class="kiriof-selected-service-code" value="' + $('<span>').text(comparison.service_code || '').html() + '">';
							html += '<input type="hidden" class="kiriof-selected-service-name" value="' + $('<span>').text(comparison.service_name || '').html() + '">';
							html += '<input type="hidden" class="kiriof-selected-price" value="' + (parseFloat(comparison.raw_price) || 0) + '">';
						} else {
							var replacementHtml = '<p><label>' + text('replacementCourier', 'Select a replacement courier with your consent.') + '</label><select class="kiriof-replacement-courier" style="width:100%;"><option value="">' + text('selectCourier', 'Select courier') + '</option>';
							$.each(response.data.replacement_options || [], function (index, option) {
								replacementHtml += '<option value="' + $('<span>').text(option.service_code + '|' + option.service_name).html() + '" data-price="' + (parseFloat(option.raw_price) || 0) + '" data-option="' + $('<span>').text(JSON.stringify(option)).html() + '">' + $('<span>').text(option.courier + ' ' + option.service + ' — ' + option.price).html() + '</option>';
							});
							replacementHtml += '</select></p><p><label><input type="checkbox" class="kiriof-replacement-consent"> ' + text('replacementConsent', 'I consent to use this replacement courier.') + '</label></p>';
							$replacement.html(replacementHtml).show();
						}
						setResult($result, true, html);
						$modal.find('#kiriof-change-origin-confirm').prop('disabled', !comparison.available);
						$modal.data('shipping-check', response.data);
							if ($.fn.select2) {
								$modal.find('.kiriof-replacement-courier').select2({ width: '100%' });
							}
					} else {
						setResult($result, false, '<p>' + ((response && response.data && response.data.message) || text('checkFailed', 'Shipping check failed.')) + '</p>');
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
			var $replacement = $modal.find('.kiriof-replacement-courier');
			var selected = $replacement.find(':selected').attr('data-option');
			if (selected) {
				var base = ($modal.data('shipping-check') || {}).comparison || {};
				var option = JSON.parse($('<textarea>').html(selected).val() || '{}');
								$modal.find('.kiriof-change-origin-breakdown').html(renderOrderBreakdown(buildReplacementComparison(base, option))).show();
			}
			$modal.find('#kiriof-change-origin-confirm').prop('disabled', !$replacement.val() || !$modal.find('.kiriof-replacement-consent').prop('checked'));
		});

		$modal.on('click.kiriofChangeOrigin', '#kiriof-change-origin-confirm', function () {
			var $confirm = $(this);
			var $result = $modal.find('.kiriof-change-origin-result');
			var locationId = parseInt($select.val(), 10) || 0;
			var shippingCheck = $modal.data('shipping-check') || {};
			var comparison = shippingCheck.comparison || {};
			var $replacement = $modal.find('.kiriof-replacement-courier');
			var serviceParts = $replacement.length && $replacement.val() ? $replacement.val().split('|') : [ comparison.service_code || '', comparison.service_name || '' ];
			var selectedPrice = $replacement.length && $replacement.val() ? parseFloat($replacement.find(':selected').data('price')) || 0 : parseFloat($modal.find('.kiriof-selected-price').val()) || 0;

			if (!locationId || $confirm.prop('disabled')) {
				return;
			}

			$confirm.prop('disabled', true);
			$.post(window.ajaxurl, {
				action: 'kiriof_change_origin',
				order_id: data.order_id,
				location_id: locationId,
				courier_service: serviceParts[0],
				courier_service_name: serviceParts[1],
				courier_price: selectedPrice,
				courier_consent: $replacement.length ? 1 : 0,
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
