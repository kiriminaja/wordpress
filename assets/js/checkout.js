(function( $ ) {
	'use strict';
    window.kiriminajaCheckout = {
    	data: kiriminaja_checkout_data,
        el: {
            window: $(window),
            document: $(document),
            body: $('body'),
            field: {
            	billing: {
            		country: $('#billing_country'),
            		state: $('#billing_state'),
            		city: $('#billing_city'),
            		district: $('#billing_district'),
            		district_text: $('#billing_district_text'),
            		country_wrapper: $('#billing_country_field'),
            		state_wrapper: $('#billing_state_field'),
            		city_wrapper: $('#billing_city_field'),
            		district_wrapper: $('#billing_district_field')
            	},
            	shipping: {
            		country: $('#shipping_country'),
            		state: $('#shipping_state'),
            		city: $('#shipping_city'),
            		district: $('#shipping_district'),
            		district_text: $('#shipping_district_text'),
            		country_wrapper: $('#shipping_country_field'),
            		state_wrapper: $('#shipping_state_field'),
            		city_wrapper: $('#shipping_city_field'),
            		district_wrapper: $('#shipping_district_field')
            	}
            }
        },

        fn: {

        	loadAddress: function(context, el) {
        		var field = kiriminajaCheckout.el.field[context];
    			var selected = el.select2('data');
    			if ( selected.length ) {
    				field.state.val(selected[0].state);
    				field.city.val(selected[0].city);
    				field.district_text.val(selected[0].district);
    			}
        	}

        },

        run: function () {
        	var el = kiriminajaCheckout.el;
        	var billing = el.field.billing;
        	var shipping = el.field.shipping;
			var local = kiriminajaCheckout.data;
			var fn = kiriminajaCheckout.fn;

			$( el.body ).on( 'country_to_state_changing', function( event, country, wrapper ) {
				if ( 'ID' === country ) {
					wrapper.find('#billing_state_field').hide();
					wrapper.find('#shipping_state_field').hide();
				} else {
					wrapper.find('#billing_state_field').show();
					wrapper.find('#shipping_state_field').show();
				}
			});

			billing.district.on('change', function() {
				fn.loadAddress('billing', $(this));
			});
			shipping.district.on('change', function() {
				fn.loadAddress('shipping', $(this));
			});

			$( '.init-select2 select' ).select2();

			$( '.select2-ajax select' ).each(function() {
				var action 	= $(this).data('action');
				var phrase	= $(this).val();
				var nonce 	= $(this).data('nonce');
				$(this).select2({
					ajax: {
						url: local.ajaxurl,
						dataType: 'json',
						delay: 500,
						data: function( params ) {
							return {
								kiriminaja_action: nonce,
								action: action,
								q: params.term
							}
						},
						processResults: function (data, params) {
							return {
								results: data
							};
						},
		    			cache: true
					},
					minimumInputLength: 3,
					placeholder: $(this).attr('placeholder')
				});
			});

			$( 'form.checkout' ).on( 'change', 'input[name^="payment_method"]', function() { 
        		$('body').trigger('update_checkout');
            });
        }
    };
    kiriminajaCheckout.run();
})( jQuery );