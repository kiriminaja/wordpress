(function( $ ) {
	'use strict';
    window.kiriminajaAdminSetting = {
        el: {
            window: $(window),
            document: $(document),
        },
        fn: {
            loadCityList: function( province_id ) {
				$('[name="kiriminaja_setting[store_city]"], [name="kiriminaja_setting[store_district]"]').prop('disabled',true);
		  		var arrCity  = '<option value="">' + kiriminaja_translations.select_city + '</option>';
		  		var arrDistrict  = '<option value="*">' + kiriminaja_translations.select_district + '</option>';
				if ( '' !== province_id ) {
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action : 'kiriminaja_get_list_city',
							province_id : province_id,
							kiriminaja_action : kiriminaja_nonces.get_list_city
						},
						dataType:'json',
						cache: false,
						success: function(arr){
						  	var selectList = '';
						  	$('[name="kiriminaja_setting[store_city]"]').val('').empty().append(arrCity);
							$('[name="kiriminaja_setting[store_district]"]').val('').empty().append(arrDistrict); 
						  	$.each(arr, function(key,value) {
								var data = {};
								arrCity += '<option value='+ key + '>'+ value +'</option>';
							});
							$('[name="kiriminaja_setting[store_city]"], [name="kiriminaja_setting[store_district]"]').prop('disabled',false);
							$('[name="kiriminaja_setting[store_city]"]').html(arrCity).trigger('setvalue').trigger('change').trigger('options_loaded', arr);
						},
						error: function(err) {
							console.log(err);
						}
					});
				} else {
					$('[name="kiriminaja_setting[store_city]"]').prop('disabled',false).html(arrCity).trigger('change');
					$('[name="kiriminaja_setting[store_district]"]').prop('disabled',false).html(arrDistrict);
				}
			},

			loadDistrictList: function( city_id ) {
				$('[name="kiriminaja_setting[store_district]"]').prop('disabled',true);
				var arrDistrict = '<option value="">' + kiriminaja_translations.select_district + '</option>';
				if ( '' !== city_id ) {
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action : 'kiriminaja_get_list_district',
							city_id : city_id,
							kiriminaja_action : kiriminaja_nonces.get_list_district
						},
						dataType:'json',
						cache: false,
						success: function(arr){
						  	var selectList = '';
						  	$('[name="kiriminaja_setting[store_district]"]').val('').empty().append(arrDistrict); 
						  	$.each(arr, function(key,value) {
								var data = {};
								arrDistrict += '<option value='+ key + '>'+ value +'</option>';
							});
							$('[name="kiriminaja_setting[store_district]"]').html(arrDistrict).trigger('setvalue').prop('disabled',false).trigger('options_loaded', arr);
					  	}
					});
				} else {
					$('[name="kiriminaja_setting[store_district]"]').html(arrDistrict).prop('disabled',false);
				}
			},

			loadReturningUserData: function() {
				$('[name="kiriminaja_setting[store_province]"]').val(kiriminaja_settings.store_province).trigger('change');
				if (parseInt(kiriminaja_settings.store_city)) {
					$('[name="kiriminaja_setting[store_city]"]').on('options_loaded', function(e, city_list) {
						if (city_list[kiriminaja_settings.store_city]) {
							$('[name="kiriminaja_setting[store_city]"]').val(kiriminaja_settings.store_city).trigger('change');
						}
					});
					if (parseInt(kiriminaja_settings.store_district)) {
						$('[name="kiriminaja_setting[store_district]"]').on('options_loaded', function(e, district_list) {
							if (district_list[kiriminaja_settings.store_district]) {
								$('[name="kiriminaja_setting[store_district]"]').val(kiriminaja_settings.store_district).trigger('change');
							}
						});
					}
				}
			}
        },
        run: function () {
            kiriminajaAdminSetting.el.document.ready(function () {
                $('.kiriminaja-setting').on( 'change', '[name="kiriminaja_setting[store_province]"]', function() {
					var province_id = $(this).val();
					kiriminajaAdminSetting.fn.loadCityList( province_id );
				} );

				$('.kiriminaja-setting').on( 'change', '[name="kiriminaja_setting[store_city]"]', function() {
					var city_id = $(this).val();
					kiriminajaAdminSetting.fn.loadDistrictList( city_id );
				});

				kiriminajaAdminSetting.fn.loadReturningUserData();

				$('#kiriminaja-check-key').on('click', function() {
					var token = $('[name="kiriminaja_setting[token]"]').val();
					var button = $(this);
					button.attr('disabled', true);
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action : 'kiriminaja_set_token',
							token : token,
							kiriminaja_action : kiriminaja_nonces.set_token
						},
						dataType:'json',
						cache: false,
						success: function(res){
							console.log(res);
						  	if ( res.status ) {
						  		window.onbeforeunload = '';
						  		location.reload();
						  	} else {
						  		alert( res.data );
						  		button.attr('disabled', false);
						  	}
						},
						error: function(err) {
							console.log(err);
							button.attr('disabled', false);
						}
					});
				});

				$('#kiriminaja-delete-key').on('click', function() {
					var button = $(this);
					button.attr('disabled', true);
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action : 'kiriminaja_delete_token',
							kiriminaja_action : kiriminaja_nonces.set_token
						},
						dataType:'json',
						cache: false,
						success: function(res){
					  		window.onbeforeunload = '';
						  	location.reload();
						},
						error: function(err) {
							console.log(err);
							button.attr('disabled', false);
						}
					});
				});
            });
        }
    };
    kiriminajaAdminSetting.run();
})( jQuery );