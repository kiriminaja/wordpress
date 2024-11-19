jQuery(document).ready(function(){

    var $ = jQuery;
    var orderItemLineClass = $('#order_shipping_line_items .shipping');
    var orderIDdataItem;
    
    const orderID = woocommerce_admin_meta_boxes.post_id;

    let orderIDdataItemShipping;
    let orderItemElement = document.querySelector('#order_shipping_line_items tr.shipping');
    if( orderItemElement){
        orderIDdataItemShipping = document.querySelector('#order_shipping_line_items tr.shipping').getAttribute('data-order_item_id');
    }
    
    /** 
     * Set readonly  
     * Shipping Cost
     * Shipping Method Title
    */
    if(orderItemElement) isReadOnly(`[name="shipping_cost[${orderIDdataItemShipping}]"]`);
    if(orderItemElement) isReadOnly(`[name="shipping_method_title[${orderIDdataItemShipping}]"]`);

    /**
     * Set Display None
     * shipping method item
     */
    if(orderItemElement) isDisplayNone('.wc-order-data-row-toggle > .add-order-shipping');
    
    if( getUrlParameter('post_type') == 'shop_order' ){

        getSearchAreaKelurahan();
        getSelectedSubdistrictAdminOrder();
    }else{
        jQuery(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings.data && settings.data.indexOf('action=kiriminaja_expedition_by_pricing') !== -1) {
                autoLoadExpeditionSelected();
            }
        });

    }
    
        
    jQuery(document).ajaxSuccess(function(event, xhr, settings) {
        orderIDdataItem = $('#order_shipping_line_items tr.shipping ').attr('data-order_item_id');
        let shippingMethodElement = $(`[name="shipping_method[${orderIDdataItem}]"]`);

        if(
            settings.data && settings.data.indexOf('action=woocommerce_add_order_shipping') !== -1 || //add shipping order item
            settings.data && settings.data.indexOf('action=woocommerce_add_order_item') !== -1 || // add product order item
            settings.data && settings.data.indexOf('action=woocommerce_remove_order_item') !== -1 || //remove product order item
            settings.data && settings.data.indexOf('action=woocommerce_save_order_items') !== -1 || //save order item
            settings.data && settings.data.indexOf('action=woocommerce_calc_line_taxes') !== -1 || //recalculate order item   
            settings.data && settings.data.indexOf('action=woocommerce_load_order_items') !== -1 ||//load Order Items  
            settings.data && settings.data.indexOf('action=woocommerce_add_order_fee') !== -1 //Add Order fee  
             
        ){         
            if( shippingMethodElement.val() == 'kiriminaja' ){
                shippingMethodElement.val('kiriminaja').trigger('change');
                
                $(`[name="shipping_cost[${orderIDdataItem}]"`).attr('readonly', true);

                setTimeout(() => {
                    let idExpedition = $('[name="kj_expedition"] option').filter(function() {
                        return $(this).text() === jQuery(`[name="shipping_method_title[${orderIDdataItem}]"]`).val();
                    }).val();

                    
                    if( idExpedition !== undefined ){
                        $('[name="kj_expedition"]').val(idExpedition).trigger("change"); 
                    }
                    

                }, 3000);
            } 

            cekShippingMethodOrder();
            editShippingOrder();
            ajaxCheckProductItemOrder();
        }
        
    });

    $( document ).one( "ajaxComplete", function(event,xhr,setting) {     
    
        if(
            getUrlParameter('post') == false || getUrlParameter('post') == true
        ) {       
                   
            getShippingMethod();
            cekShippingMethodOrder();           
        }    
        


    });

    function autoLoadExpeditionSelected(){
        orderIDdataItem = $('#order_shipping_line_items tr.shipping ').attr('data-order_item_id');
        
            let idExpedition = $('[name="kj_expedition"] option').filter(function() {
                return $(this).text() === jQuery(`[name="shipping_method_title[${orderIDdataItem}]"]`).val();
            }).val();
            
            $('[name="kj_expedition"]').val(idExpedition).trigger("change"); 
            
        

    }

    function getShippingMethod(){
        $(document).on('change','.shipping_method',function(){ 
            if( $(this).val() == 'kiriminaja' ){

                $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select name="kj_subdistrict" class="selet2" style="width:100%;"></select>`);            
                $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select name="kj_expedition" class="selet2" style="width:100%;"><option value="">Select Expeditions</option></select>`);               
                $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="select2" name="kj_subdistrict_name"/>`);                    
                
                /** COD AND INSURANCE */
                $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="codfeehidden" name="kj_codfee_hidden"/>`);                    
                $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="insurancefeehidden" name="kj_insurancefee_hidden"/>`);                    
                
                
                getSearchAreaKelurahan();

                getExpeditionByPricing();

                getSelectedExpedition($('tr.shipping'));
                
                

            }else{
                $('[name="kj_expedition"]').remove();
                $('[name="kj_subdistrict"]').remove();
                $('[name="kj_subdistrict_name"]').remove();
                $('span.select2.select2-container').remove(); // Destroy the Select2 instance

            }
                       
        });
    }

    function explodeJquery(encodedStringUrl){

        // Decode the URL-encoded string
        var decodedString = decodeURIComponent(encodedStringUrl);

        // Split the string into key-value pairs
        var keyValuePairs = decodedString.split('&');

        // Create an object to store the key-value pairs
        var data = {};
        // Iterate over the key-value pairs and populate the object
        keyValuePairs.forEach(function(pair) {
            var [key, value] = pair.split('=');
            data[key] = value;
        });

        return data;

    }

    cekShippingMethodOrder();
    function cekShippingMethodOrder(){        
        
        $.each(orderItemLineClass,function(key,item){
            let root = $(this);            
            let shipping_method = root.find('select.shipping_method optgroup option:selected').val();
                        
            if(shipping_method.length > 0){
                if(shipping_method == 'kiriminaja'){  
                  
                    kj_addFieldShippingOrder(root);                    
                }
            }
        });

    }   

    function kj_addFieldShippingOrder(elementRoot){
        if( getUrlParameter('post') != false && getUrlParameter('action') == 'edit' ){
        
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select style="display:none;" name="kj_subdistrict" class="selet2" style="width:100%;"></select>`);            
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select name="kj_expedition" class="selet2" style="width:100%;"><option value="">Select Expeditions</option></select>`);               
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" name="kj_subdistrict_name"/>`);               
            
            /** COD AND INSURANCE */
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="codfeehidden" name="kj_codfee_hidden"/>`);                    
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="insurancefeehidden" name="kj_insurancefee_hidden"/>`);                    
            
            
            getSearchAreaKelurahan();
            getExpeditionByPricing();   
            getSelectedExpedition(elementRoot);
        }
    }


    function getSearchAreaKelurahan(){           
        $('[name="kj_subdistrict"],[name="_billing_kj_destination_area"],[name="_shipping_kj_destination_area"]').select2({
            minimumInputLength: 3,
            placeholder: 'Select Subdistrict',
            allowClear: true,
            ajax: {
                url: kj.ajaxurl,
                dataType: 'json',
                type: "POST",
                delay: 250,
                data: function (search) {
                    return {
                        data:search,
                        action: 'kiriminaja_subdistrict_search'
                    };
                },
                processResults: function (response) {
                    return {
                        results: jQuery.map(response.data, function (item) {
                            return {
                                text: item.text,
                                id: item.id
                            }
                        })
                    };
                },
                cache: true
            },
        }); 

        /** hide select2 contaniner subdistrict  */
        setTimeout(() => {   
            if(document.querySelector('[name="kj_subdistrict"]') ){
                let select2Container = document.querySelector('[name="kj_subdistrict"]').nextElementSibling;
                if (select2Container && select2Container.classList.contains('select2')) {
                    select2Container.style.display = 'none';
                }
            }             
        }, 500);
    }

    function getSelectedSubdistrictAdminOrder(){
        $(document).on('change','select[name=_billing_kj_destination_area],select[name=_shipping_kj_destination_area]', function() {
            let destination_id = $(this).find("option:selected").val();
            let destination_name = $(this).find("option:selected").text();

            if( $(this).attr('name') == '_billing_kj_destination_area' ){
                $('[name="_billing_kj_destination_name"]').val(destination_name);
            }else if($(this).attr('name') == '_shipping_kj_destination_area'){
                $('[name="_shipping_kj_destination_name"]').val(destination_name);
            }

            //set subdistrict hidden
            $('[name="kj_subdistrict_id_hidden"]').val(destination_id);
            $('[name="kj_subdistrict_name_hidden"]').val(destination_name);
                        
        });

        $(document).on('change','[name="_payment_method"]',function(){
            $('[name="kj_payment_method_hidden"]').val($(this).val());
        });
    }

    function getExpeditionByPricing(){
        
        let destination = getIdDestinationAndNameDestination();
        let id_destination = destination.id_destination;
        let name_destination = destination.name_destination;
        
        if( id_destination !== undefined ){
            setTimeout(function(){
                $('[name="kj_subdistrict"]').val( Number(id_destination) ).trigger("change"); 
                $('[name="kj_subdistrict"]').html(`<option selected value="${id_destination}">${name_destination}</option>`);
            },300);   
        }


        $('select[name=kj_subdistrict]').on('change', function() {            
            var destination_id = $(this).find("option:selected").val();
            var destination_name = $(this).find("option:selected").text();

            if( $(this).find("option:selected").length == 0 ){
                if( $('[name="_shipping_kj_destination_area"] option:selected').val() != '' ){
                    destination_id = $('[name="_shipping_kj_destination_area"] option:selected').val();
                    destination_name = $('[name="_shipping_kj_destination_area"] option:selected').text();
                }else{
                    destination_id = $('[name="_billing_kj_destination_area"] option:selected').val();
                    destination_name = $('[name="_billing_kj_destination_area"] option:selected').text();
                }
            }

            let expeditionName = $('select[name=kj_expedition]');
            

            let data = {
                'action': 'kiriminaja_expedition_by_pricing',
                'destination_id': Number(destination_id),
                'order_id': ( getUrlParameter('post') == false ) ? woocommerce_admin_meta_boxes.post_id : getUrlParameter('post'),
                'nonce':kj.nonce
            };
            
            $.ajax({
                url: kj.ajaxurl,
                type: 'POST',
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    expeditionName.html('<option>Please Wait ...</option>');
                    expeditionName.prop('disabled',true);

                    $('.codfee').find('.total').html('Waiting ...');
                    $('.insurancefee').find('.total').html('Waiting ...');
                },
                success: function(response) {                    

                    let expedition_options = '<option value="">Select Expedition</option>';
                    
                    if(response.status = true){
                        let expeditions = response.data;  

                        $.each(expeditions, function(index, item) {
                            expedition_options  += `<option value="${item.key}" data-cost="${item.cost}">${item.value}</option>`;
                        });
                        
                        expeditionName.html(expedition_options);
                        expeditionName.prop('disabled',false);
                        
                        $('[name="kj_subdistrict_name"]').val(destination_name);
                        
                        $('[name="kj_expedition"]').val(response.service).trigger("change"); 

                    }else{
                        expeditionName.html(expedition_options);
                        expeditionName.prop('disabled',false);
                    }

                    expeditionName.select2();
                    
                },
                error: function(xhr, status, error) {
                    return false;
                }
            });
        });
        
    }

    

    function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;
    
        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');
    
            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }
        return false;
    }

    function getSelectedExpedition(elementRoot){
        $('select[name=kj_expedition]').on('change', function() {
            let root = $(this);
            let expedition_cost = $(this).find("option:selected").attr('data-cost');
            let expedition_name = $(this).find("option:selected").text();
            let order_id_item = root.closest('tr.shipping').attr('data-order_item_id');  
            

            let html = `
                <input name="kj_expedition_cost" type="hidden" value="${expedition_cost}">
                <input name="kj_expedition_name" type="hidden" value="${expedition_name}">
            `;
            
            $(`[name="shipping_cost[${order_id_item}]"]`).val(expedition_cost);
            $(`[name="shipping_method_title[${order_id_item}]"]`).val(expedition_name);

            if( $('[name=kj_expedition_cost]').length > 0 ){
                $('[name=kj_expedition_cost]').val(expedition_cost);
                $('[name=kj_expedition_name]').val(expedition_name);
            }else{
                elementRoot.find('.shipping_method').closest('.edit').append(html);            
            }

            let destination = getIdDestinationAndNameDestination();
            let id_destination = destination.id_destination;
            let name_destination = destination.name_destination;

            let data = {
                'action':'kj_calculation_CodFeeAndInsuranceFee',
                'order_id': woocommerce_admin_meta_boxes.post_id,
                'kj_subdistrict': Number(id_destination),
                'kj_subdistrict_name':name_destination,
                'kj_expedition':$(this).find("option:selected").val(),
                'kj_expedition_name':expedition_name,
                'kj_expedition_cost': parseFloat(expedition_cost),
            };

            $.ajax({
                url:kj.ajaxurl,
                type: 'POST',
                data:data,
                dataType: 'json',
                beforeSend: function() {
                    $('.codfee').find('.total').html('Waiting ...');
                    $('.insurancefee').find('.total').html('Waiting ...');
                },
                success: function(response) {
                    
                    $('.insurancefee').find('.total').html(response.data.insurance_fee);
                    $('[name="kj_insurancefee_hidden"]').val(response.data.insurance_fee_number);
                    
                    
                    if( $('[name="_shipping_kj_insurance"]:checked').length > 0 ){
                        $('.insurancefee').show();
                        // set insurance
                        $('.insurancefee').find('.total').html(response.data.insurance_fee);
                        $('[name="kj_insurancefee_hidden"]').val(response.data.insurance_fee_number);
                    }else if( $('[name="_billing_kj_insurance"]:checked').length > 0 ){
                        $('.insurancefee').show();
                        $('.insurancefee').find('.total').html(response.data.insurance_fee);
                        $('[name="kj_insurancefee_hidden"]').val(response.data.insurance_fee_number);
                    }else{
                        $('.insurancefee').hide();
                    }
                                        

                    if( $('[name="_payment_method"]').val() == 'cod' ) {
                        $('.codfee').show();
                    }else{
                        $('.codfee').hide();
                    }
                    
                    if( response?.data?.cod_fee != '0' ) $('.codfee').find('.total').html(response.data.cod_fee);
                    if( response?.data?.cod_fee_number != '0' ) $('[name="kj_codfee_hidden"]').val(response.data.cod_fee_number);
                    

                    get_OnChangeCodAndInsurance();

                },
                error: function(xhr, status, error) {
                    return false;
                }
            });

        });
    }

    get_OnChangeCodAndInsurance();
    function get_OnChangeCodAndInsurance(){
        $(document).on('change','[name="_payment_method"],[name="_billing_kj_insurance"],[name="_shipping_kj_insurance"]',function(){

            let root = $(this);
            let getNameElement = root.attr('name'); 
                        
            if( getNameElement == '_payment_method' ){
                
                if( root.val() == 'cod' ){
                    $('.codfee').show();
                }else{
                    $('.codfee').hide();
                }
            }else{
                if( root.is(':checked') ){
                    $('.insurancefee').show();
                } else{
                    $('.insurancefee').hide();
                }
            }
        });
    }

    ajaxCheckProductItemOrder();
    function ajaxCheckProductItemOrder(){
        $.ajax({
            url: kj.ajaxurl,
            type: 'POST',
            data: {
                'action': 'check_product_item_order',
                'order_id': woocommerce_admin_meta_boxes.post_id,
                'nonce': kj.nonce
            },
            dataType: 'json',
            success: function(response) { 
                                  
               if( response.data.success == false ){
                    $('.add-order-fee,.add-order-shipping,.add-order-tax').prop('disabled',true);
               }else{
                    $('.add-order-fee,.add-order-shipping,.add-order-tax').prop('disabled',false);
               }

               if(response.data.shipping == true ){
                $('.add-order-shipping').prop('disabled',true);
               }
            },
            error: function(xhr, status, error) {
                return false;
            }
        });
    }

    function editShippingOrder(){
        
        $('#order_shipping_line_items .edit-order-item').unbind().on('click', function(e){
            
            orderIDdataItem = $('#order_shipping_line_items tr.shipping ').attr('data-order_item_id');

            let destination = getIdDestinationAndNameDestination();
            let id_destination = destination.id_destination;        
            
            if( id_destination === undefined){
                let alert_msg = alert_message_error(`Please fill in the shipping Billing first`);
                
                $( alert_msg ).insertBefore( '#order_data' );

                setTimeout(() => {
                    $('.kj-alert').remove();
                }, 3000);

                $('html,body').animate({ scrollTop: 0 }, 'slow');   

                return false;
            }

            let val_shipping = $(`[name="shipping_method[${orderIDdataItem}]"]`).val();
            
            if( val_shipping.length == 0 ){
                $(`[name="shipping_method[${orderIDdataItem}]"]`).val('kiriminaja').trigger('change');
            }
            
        });

    }

    function alert_message_error(message){
        let html = `<div class="kj-alert">
                      <span class="kj-closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
                        ${message}
                    </div>`;
        return html;
    }

    function getIdDestinationAndNameDestination(){
        let id_destination;
        let name_destination;

        if($('[name="_shipping_kj_destination_area"] option:selected').val() == '' ){
            if($('[name="_billing_kj_destination_area"] option:selected').val() != '') {                
                id_destination = $('[name="_billing_kj_destination_area"] option:selected').val();
                name_destination = $('[name="_billing_kj_destination_area"] option:selected').text();
            }else{
                id_destination = $('[name="kj_subdistrict"] option:selected').val();
                name_destination = $('[name="kj_subdistrict"] option:selected').text();
            }
        }else{
            id_destination = $('[name="_shipping_kj_destination_area"] option:selected').val();
            name_destination = $('[name="_shipping_kj_destination_area"] option:selected').text();   
        }

        return {id_destination: id_destination, name_destination: name_destination};
    }

    function isReadOnly(element){
        document.querySelector(element).readOnly = true;
    }

    function isDisplayNone(element){
        document.querySelector(element).style.display = 'none';
    }

});