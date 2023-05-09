(function( $ ) {
	'use strict';
	window.kiriminajaAdminOrder = {
		data: {
			order_ids: [],
			selectedSchedule: '',
			paymentIsShown: false
		},
		el: {
			window: $(window),
			document: $(document),
			order_status: $('#order_status')
		},
		fn: {

			// load selected orders details.
			loadOrders: function(order_ids, type) {
				kiriminajaAdminOrder.data.order_ids = [];
				$('.wrap').block({
					message: null,
					overlayCSS: {
						background: '#f0f0f1',
						opacity: 0.6
					}
				});
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_load_orders',
						order_ids: order_ids,
						kiriminaja_action: kiriminaja_nonces.get_list_order
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if ( res.order_ids.length ) {
							kiriminajaAdminOrder.data.order_ids = res.order_ids;
							$('.kiriminaja-request-pickup .orders-list').html( res.html );
							kiriminajaAdminOrder.fn.loadSchedules();
							kiriminajaAdminOrder.fn.showPopup();
						} else {
							if ( 'bulk' === type ) {
								alert(kiriminaja_translations.no_orders_to_pickup);
							} else {
								alert(kiriminaja_translations.order_not_pickup);
							}
						}
						$('.wrap').unblock();
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.wrap').unblock();
					}
				});
			},

			// load available pickup schedules.
			loadSchedules: function() {
				kiriminajaAdminOrder.data.selectedSchedule = '';
				$('.kiriminaja-request-pickup').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_load_schedules',
						kiriminaja_action: kiriminaja_nonces.get_schedules
					},
					dataType:'json',
					cache: false,
					success: function(res){
						$('.kiriminaja-request-pickup .pickup-schedules').html( res.html );
						$('.kiriminaja-request-pickup').unblock();
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.kiriminaja-request-pickup').unblock();
					}
				});
			},

			// show the popup.
			showPopup: function(orders) {
				kiriminajaAdminOrder.fn.checkSelected();
				tb_show( kiriminaja_translations.pickup_request, "#TB_inline?width=600&inlineId=kiriminaja-request-pickup" );
			},

			// only enable Send Request button if schedule is already selected.
			checkSelected: function() {
				if ( kiriminajaAdminOrder.data.order_ids.length && kiriminajaAdminOrder.data.selectedSchedule ) {
					$('#kiriminaja-send-request-pickup').attr('disabled',false);
				} else {
					$('#kiriminaja-send-request-pickup').attr('disabled',true);
				}
			},

			sendRequest: function() {
				$('.kiriminaja-request-pickup').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_send_pickup_request',
						order_ids: kiriminajaAdminOrder.data.order_ids,
						schedule: kiriminajaAdminOrder.data.selectedSchedule,
						kiriminaja_action: kiriminaja_nonces.pickup_request
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if (res.status) {
							tb_remove();
							if ( 'pending' === res.status ) {
								kiriminajaAdminOrder.fn.loadPayment(res.pickup_number);
							} else {
								window.location.reload();
							}
						} else {
							alert(res.message);
							$('.kiriminaja-request-pickup').unblock();
						}
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.kiriminaja-request-pickup').unblock();
					}
				});
			},

			loadPayment: function(pickup_number) {
				$('.wrap').block({
					message: null,
					overlayCSS: {
						background: '#f0f0f1',
						opacity: 0.6
					}
				});
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_load_payment',
						pickup_number: pickup_number,
						kiriminaja_action: kiriminaja_nonces.load_payment
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if (res.status) {
							$('.kiriminaja-payment .pickup-number span').html(pickup_number);
							$('.kiriminaja-payment .amount').html(res.amount);
							new QRCode(document.getElementById("qrcode"), res.payment.qr_content);
							tb_show( kiriminaja_translations.payment, "#TB_inline?width=300&inlineId=kiriminaja-payment" );
							kiriminajaAdminOrder.data.paymentIsShown = true;
						} else {
							alert(res.message);
						}
						$('.wrap').unblock();
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.wrap').unblock();
					}
				});
			},

			getShippingStatus: function(order_id, row) {
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_get_shipping_status',
						order_id: order_id,
						kiriminaja_action: kiriminaja_nonces.get_shipping_status
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if (res) {
							row.find('.shipping_address.column-shipping_address .description').after('<span class="description ka-last-status">'+res+'</span>');
						}
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.wrap').unblock();
					}
				});
			},

			cancelShipment: function(order_id,reason) {
				$('.wrap').block({
					message: null,
					overlayCSS: {
						background: '#f0f0f1',
						opacity: 0.6
					}
				});
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: 'kiriminaja_cancel_shipment',
						order_id: order_id,
						reason: reason,
						kiriminaja_action: kiriminaja_nonces.cancel_shipment
					},
					dataType:'json',
					cache: false,
					success: function(res){
						alert(res.message);
						$('.wrap').unblock();
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.wrap').unblock();
					}
				});
			}
		},

		run: function () {
			kiriminajaAdminOrder.el.document.ready(function () {
				let fn = kiriminajaAdminOrder.fn;
				let el = kiriminajaAdminOrder.el

				// handle bulk action.
				$('#doaction').on('click', function(e) {
					var action = $('#bulk-action-selector-top').val();
					if ( 'request_pickup' === action ) {
						e.preventDefault();
						var order_ids = [];
						$('input[name="post[]"]:checked').each(function() {
						   order_ids.push(this.value);
						});
						if ( order_ids.length ) {
							fn.loadOrders(order_ids, 'bulk');
						} else {
							alert(kiriminaja_translations.orders_ids_empty);
						}
					}
				});

				// handle single order action.
				$('.order_actions #actions .button').on('click', function(e) {
					var action = $('[name=wc_order_action]').val();
					if ( 'request_pickup' === action ) {
						e.preventDefault();
						var order_id = $('#post_ID').val();
						if ( order_id ) {
							fn.loadOrders([order_id], 'single');
						} else {
							alert(kiriminaja_translations.orders_ids_empty);
						}
					} else if ( 'cancel_shipment' === action ) {
						e.preventDefault();
						if ( confirm( kiriminaja_translations.confirm_cancel ) ) {
							var order_id = $('#post_ID').val();
							var reason = prompt( kiriminaja_translations.enter_reason, "-" );
							if ( order_id ) {
								fn.cancelShipment(order_id,reason);
							} else {
								alert(kiriminaja_translations.orders_ids_empty);
							}
						}
					}
				});

				$('.kiriminaja-request-pickup').on('change', '[name=pickup_schedule]', function(){
					kiriminajaAdminOrder.data.selectedSchedule = $(this).val();
					fn.checkSelected();
				});

				$('#kiriminaja-send-request-pickup').on('click', function() {
					fn.sendRequest();
				});

				$('#kiriminaja-close-payment').on('click', function() {
					window.location.reload();
				});

				$('#the-list .type-shop_order').each(function() {
					var order_id = $(this).find('input[type=checkbox]').val();
					fn.getShippingStatus(order_id, $(this));
				});

				$('.toggle-ka-shipping-histories').on('click', function(e){
					e.preventDefault();
					$('.ka-shipping-history').toggle();
				});

				if ( el.order_status.length ) {
					el.order_status.find( 'option' ).each( function() {
						let val = $(this).val();
						if ( $.inArray( val, kiriminaja_order_statuses ) === -1 ) {
							$(this).prop('disabled',true);
						}
					} );
				}
			});

			$('body').on('thickbox:removed', function() {
				kiriminajaAdminOrder.data.order_ids = [];
				kiriminajaAdminOrder.data.selectedSchedule = '';
				$('.kiriminaja-request-pickup .orders-list').html('');
				$('.kiriminaja-request-pickup .pickup-schedules').html('');
				$('.kiriminaja-payment .pickup-number span').html('');
				$('.kiriminaja-payment .amount').html('');
				if ( kiriminajaAdminOrder.data.paymentIsShown ) {
					window.location.reload();
				}
			});
		}
	};
	kiriminajaAdminOrder.run();
})( jQuery );