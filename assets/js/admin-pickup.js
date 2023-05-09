(function( $ ) {
	'use strict';
    window.kiriminajaAdminPickup = {
    	data: {
    		selectedSchedule: '',
    		order_ids: [],
    		pickup_id: ''
    	},
        el: {
            window: $(window),
            document: $(document),
            pickup: {
            	id: '',
            	number: '',
            	amount: '',
            	qr_content: ''
            }
        },
        fn: {

        	checkSchedule: function(pickup_id,pickup_number) {
        		kiriminajaAdminPickup.data.selectedSchedule = '';
        		kiriminajaAdminPickup.data.order_ids = [];
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
						action: 'kiriminaja_check_schedule',
						pickup_id: pickup_id,
						kiriminaja_action: kiriminaja_nonces.check_schedule
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if ( res.is_passed ) {
							kiriminajaAdminPickup.data.order_ids = res.order_ids;
							kiriminajaAdminPickup.data.pickup_id = pickup_id;
							kiriminajaAdminPickup.fn.loadSchedules();
							kiriminajaAdminPickup.fn.showReschedulePopup();
						} else {
							kiriminajaAdminPickup.fn.loadPayment(res.pickup_number);
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

            // show the popup.
            showReschedulePopup: function(orders) {
            	kiriminajaAdminPickup.fn.checkSelected();
            	tb_show( kiriminaja_translations.reschedule_pickup, "#TB_inline?width=300&inlineId=kiriminaja-reschedule-pickup" );
            },

            // only enable Send Request button if schedule is already selected.
            checkSelected: function() {
            	if ( kiriminajaAdminPickup.data.selectedSchedule ) {
            		$('#kiriminaja-send-reschedule-pickup').attr('disabled',false);
            	} else {
            		$('#kiriminaja-send-reschedule-pickup').attr('disabled',true);
            	}
            },

            // load available pickup schedules.
            loadSchedules: function() {
            	kiriminajaAdminPickup.data.selectedSchedule = '';
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

            sendReschedule: function() {
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
						action: 'kiriminaja_send_pickup_reschedule',
						order_ids: kiriminajaAdminPickup.data.order_ids,
						schedule: kiriminajaAdminPickup.data.selectedSchedule,
						pickup_id: kiriminajaAdminPickup.data.pickup_id,
						kiriminaja_action: kiriminaja_nonces.pickup_request
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if (res.status) {
							tb_remove();
							kiriminajaAdminPickup.fn.loadPayment(res.pickup_number);
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

            loadDetail: function(pickup_id) {
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
						action: 'kiriminaja_load_pickup_detail',
						pickup_id: pickup_id,
						kiriminaja_action: kiriminaja_nonces.load_detail
					},
					dataType:'json',
					cache: false,
					success: function(res){
						$('.kiriminaja-request-pickup-detail .orders-list').html(res.orders);
						$('.kiriminaja-request-pickup-detail .detail-number').html(res.pickup_number);
						$('.kiriminaja-request-pickup-detail .detail-status').html(res.status);
						$('.kiriminaja-request-pickup-detail .detail-schedule').html(res.schedule);
						$('.kiriminaja-request-pickup-detail .detail-requested').html(res.requested);
						// $('.kiriminaja-request-pickup-detail .ka-detail-cancel').attr('data-id', pickup_id);
						if ( res.need_payment ) {
							kiriminajaAdminPickup.el.pickup.id = pickup_id;
							kiriminajaAdminPickup.el.pickup.number = res.pickup_number;
							kiriminajaAdminPickup.el.pickup.amount = res.payment.amount;
							kiriminajaAdminPickup.el.pickup.qr_content = res.payment.payment.qr_content;
							$('.kiriminaja-payment .pickup-number span').html(res.pickup_number);
							$('.kiriminaja-payment .amount').html(res.amount);
							if ( res.need_reschedule ) {
								$('.kiriminaja-request-pickup-detail .ka-detail-pay').html('Reschedule & Pay ('+res.payment.amount+')');
							} else {
								$('.kiriminaja-request-pickup-detail .ka-detail-pay').html('Pay ('+res.payment.amount+')');
							}
							$('.kiriminaja-request-pickup-detail .ka-detail-pay').show();
						} else {
							$('.kiriminaja-request-pickup-detail .ka-detail-pay').hide();
						}
						// if ( res.is_picked ) {
						// 	$('.kiriminaja-request-pickup-detail .ka-detail-cancel').hide();
						// } else {
						// 	$('.kiriminaja-request-pickup-detail .ka-detail-cancel').show();
						// }
						tb_show( kiriminaja_translations.details, "#TB_inline?width=600&inlineId=kiriminaja-pickup-details" );
						$('.wrap').unblock();
					},
					error: function(err) {
						console.log(err);
						// button.attr('disabled', false);
						$('.wrap').unblock();
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
							kiriminajaAdminPickup.el.pickup.number = pickup_number;
							kiriminajaAdminPickup.el.pickup.amount = res.amount;
							kiriminajaAdminPickup.el.pickup.qr_content = res.payment.qr_content;
							kiriminajaAdminPickup.fn.showPaymentPopup();
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

            showPaymentPopup: function() {
            	$('.kiriminaja-payment .pickup-number span').html(kiriminajaAdminPickup.el.pickup.number);
				$('.kiriminaja-payment .amount').html(kiriminajaAdminPickup.el.pickup.amount);
				new QRCode(document.getElementById("qrcode"), kiriminajaAdminPickup.el.pickup.qr_content);
				tb_show( kiriminaja_translations.payment, "#TB_inline?width=300&inlineId=kiriminaja-payment" );
            },

            cancelPickup: function(pickup_id) {
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
						action: 'kiriminaja_cancel_pickup',
						pickup_id: pickup_id,
						kiriminaja_action: kiriminaja_nonces.cancel_pickup
					},
					dataType:'json',
					cache: false,
					success: function(res){
						if (res.status) {
							window.location.reload();
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
            }
        },

        run: function () {
            let { data, el, fn } = kiriminajaAdminPickup;

            el.document.ready(function () {

            	$('.ka-detail').on('click', function() {
            		var id = $(this).data('id');
            		fn.loadDetail(id);
            	});

            	$('.ka-detail-pay').on('click', function() {
            		tb_remove();
            		fn.checkSchedule(kiriminajaAdminPickup.el.pickup.id,kiriminajaAdminPickup.el.pickup.number);
            	});

            	$('.ka-pay').on('click', function() {
            		var id = $(this).data('id');
            		var number = $(this).data('number');
            		fn.checkSchedule(id,number);
            	});

            	$('.kiriminaja-request-pickup').on('change', '[name=pickup_schedule]', function(){
            		data.selectedSchedule = $(this).val();
            		fn.checkSelected();
            	});

            	$('#kiriminaja-send-reschedule-pickup').on('click', function() {
            		fn.sendReschedule();
            	});

            	// deprecated.
            	// $('.ka-detail-cancel').on('click', function() {
            	// 	var id = $(this).data('id');
            	// 	if ( confirm( kiriminaja_translations.confirm_cancel ) ) {
            	// 		tb_remove();
            	// 		fn.cancelPickup(id);
            	// 	}
            	// });

            	$('#kiriminaja-close-payment').on('click', function() {
            		tb_remove();
            	});

            	$('#kiriminaja-reload').on('click', function() {
            		window.location.reload();
            	});
            });

            $('body').on('thickbox:removed', function() {
				$('.kiriminaja-payment .pickup-number span').html('');
				$('.kiriminaja-payment .amount').html('');
				$('.kiriminaja-payment #qrcode').html('');
            });
        }
    };
    kiriminajaAdminPickup.run();
})( jQuery );