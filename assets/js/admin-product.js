(function( $ ) {
	'use strict';
    window.kiriminajaAdminProduct = {
    	data: {
    	},
        el: {
            window: $(window),
            document: $(document),
            product_type: $('#product-type'),
            weight_field: $('#_weight'),
            length_field: $('#product_length'),
            width_field: $('#product_width'),
            height_field: $('#product_height'),
            publish_button: $('#publish')
        },
        fn: {
        	isEmpty: function( field ) {
        		let val = $.trim( field.val() );
        		if ( '' == val || 0 == val || '0' == val ) {
        			return true;
        		}
        		return false;
        	}
        },

        run: function () {
        	let el = kiriminajaAdminProduct.el;
        	let fn = kiriminajaAdminProduct.fn;

            el.document.ready(function () {
            	el.weight_field.prop('required',true);
            	el.length_field.prop('required',true);
            	el.width_field.prop('required',true);
            	el.height_field.prop('required',true);
            });

        	el.publish_button.on( 'click', function() {
        		let product_type = el.product_type.val();
        		if ( 'simple' === product_type || 'variable' === product_type ) {
					if ( fn.isEmpty( el.weight_field ) ) {
						alert( kiriminaja_translations.weight_must_set );
						$( '.shipping_tab > a' ).click();  // Click on 'Shipping' tab.
						return false;
					}
					if ( fn.isEmpty( el.length_field ) || fn.isEmpty( el.width_field ) || fn.isEmpty( el.height_field ) ) {
						alert( kiriminaja_translations.dimensions_must_set );
						$( '.shipping_tab > a' ).click();  // Click on 'Shipping' tab.
						return false;
					}
				}
			});
        }
    };
    kiriminajaAdminProduct.run();
})( jQuery );