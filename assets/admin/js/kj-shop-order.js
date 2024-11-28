jQuery(document).ready(function(){

    //initialize Jquery
    var $ = jQuery;

    var orderItemLineClass = $('#order_shipping_line_items .shipping');
    var orderIDdataItem;

    //global variable select option
    let selectSubdistrictName,
        selectExpeditionName,
        select2Name,
        selectNameBilling,
        selectNameShipping;
    
    // orderID / PostID
    const orderID = woocommerce_admin_meta_boxes.post_id;

    // check element order shipping item
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
    
    // check add new order or Edit Order
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

    /**
     * Add Product
     * Validation Product Items
     */
    validationAddChangeProductItems();
    
    //check ajaxSuccess after Action   
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
            settings.data && settings.data.indexOf('action=woocommerce_add_order_fee') !== -1 ||//Add Order fee  
            settings.data && settings.data.indexOf('action=woocommerce_add_coupon_discount') !== -1 ||//Add coupon  
            settings.data && settings.data.indexOf('action=woocommerce_remove_order_coupon') !== -1 //remove coupon  
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

    // first load ajaxCommplete
    $( document ).one( "ajaxComplete", function(event,xhr,setting) {     
    
        if(
            getUrlParameter('post') == false || getUrlParameter('post') == true
        ) {             
            getShippingMethod();
            cekShippingMethodOrder();           
        }    
    });

    //autoload triger Expedition Selected
    function autoLoadExpeditionSelected(){
        orderIDdataItem = $('#order_shipping_line_items tr.shipping ').attr('data-order_item_id');
        
        let idExpedition = $('[name="kj_expedition"] option').filter(function() {
            return $(this).text() === jQuery(`[name="shipping_method_title[${orderIDdataItem}]"]`).val();
        }).val();
            
        $('[name="kj_expedition"]').val(idExpedition).trigger("change"); 
    }

    //autoload open edit item order shipping when edit order
    function openEditShippingItem(){
        orderIDdataItem = $('#order_shipping_line_items');
        
        if( orderIDdataItem ){
            orderIDdataItem.find('.edit-order-item').trigger('click');
        }
    }

    // change order shipping method
    function getShippingMethod(){
        $(document).on('change','.shipping_method',function(){ 
            
            selectSubdistrictName = $('select[name="kj_subdistrict"]');
            selectExpeditionName = $('select[name="kj_expedition"]');
            inputSubdistrictName = $('[name="kj_subdistrict_name"]');

            if( $(this).val() == 'kiriminaja' ){

                AppendHtmlShippingKiriminAja($('tr.shipping'));

            }else{
                selectExpeditionName.remove();
                selectSubdistrictName.remove();
                inputSubdistrictName.remove();
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

    // load check Shipping Method Order
    cekShippingMethodOrder();
    function cekShippingMethodOrder(){        
        
        $.each(orderItemLineClass,function(key,item){
            let root = $(this);            
            let shipping_method = root.find('select.shipping_method optgroup option:selected').val();
                        
            if(shipping_method.length > 0){
                if(shipping_method == 'kiriminaja') kj_addFieldShippingOrder(root);                    
            }
        });

    }   

    // add new field shipping order
    function kj_addFieldShippingOrder(elementRoot){
        if( getUrlParameter('post') != false && getUrlParameter('action') == 'edit' ){
            AppendHtmlShippingKiriminAja(elementRoot);
            openEditShippingItem();
        } 
    }

    function AppendHtmlShippingKiriminAja(element){
        orderIDdataItem = $('#order_shipping_line_items').find('.shipping_method').closest('.edit');

        orderIDdataItem.append(`
            <select style="display:none;" name="kj_subdistrict" class="selet2" style="width:100%;"></select>
            <select name="kj_expedition" class="selet2" style="width:100%;"><option value="">Select Expeditions</option></select>
            <input type="hidden" name="kj_subdistrict_name"/>
            <input type="hidden" class="codfeehidden" name="kj_codfee_hidden"/>
            <input type="hidden" class="insurancefeehidden" name="kj_insurancefee_hidden"/>
            <input type="hidden" class="insuranceChecklist" name="insuranceChecklist"/>
        `);        

        getSearchAreaKelurahan();
        getExpeditionByPricing();   
        getSelectedExpedition(element);
    }

    // get search Subdistrict API KA
    function getSearchAreaKelurahan(){           
        select2Name = $('[name="kj_subdistrict"],[name="_billing_kj_destination_area"],[name="_shipping_kj_destination_area"]');
        
        select2Name.select2({
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

    // get subdistrict on billing and shipping order
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

    // get expedition by pricing API KA
    function getExpeditionByPricing(){
        
        let destination = getIdDestinationAndNameDestination();
        let id_destination = destination.id_destination;
        let name_destination = destination.name_destination;
        
        selectNameBilling  = $('[name="_billing_kj_destination_area"] option:selected');
        selectNameShipping = $('[name="_shipping_kj_destination_area"] option:selected');

        selectSubdistrictName = $('select[name=kj_subdistrict]');
        selectExpeditionName = $('select[name=kj_expedition]');
        
        if( id_destination !== undefined ){
            setTimeout(function(){
                selectSubdistrictName.val( Number(id_destination) ).trigger("change"); 
                selectSubdistrictName.html(`<option selected value="${id_destination}">${name_destination}</option>`);
            },300);   
        }        

        selectSubdistrictName.on('change', function() {            
            var destination_id = $(this).find("option:selected").val();
            var destination_name = $(this).find("option:selected").text();

            if( $(this).find("option:selected").length == 0 ){
                if( selectNameShipping.text() != '' ){
                    destination_id = selectNameShipping.val();
                    destination_name = selectNameShipping.text();
                }else{
                    destination_id = selectNameBilling.val();
                    destination_name = selectNameBilling.text();
                }
            }  
                        
            let data = {
                'action': 'kiriminaja_expedition_by_pricing',
                'destination_id': Number(destination_id),
                'order_id': ( getUrlParameter('post') == false ) ? orderID : getUrlParameter('post'),
                'nonce':kj.nonce
            };
            
            $.ajax({
                url: kj.ajaxurl,
                type: 'POST',
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    selectExpeditionName.html('<option>Please Wait ...</option>');
                    selectExpeditionName.prop('disabled',true);

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
                        
                        selectExpeditionName.html(expedition_options);
                        selectExpeditionName.prop('disabled',false);
                        
                        $('[name="kj_subdistrict_name"]').val(destination_name);
                        
                        selectExpeditionName.val(response.service).trigger("change"); 

                    }else{
                        selectExpeditionName.html(expedition_options);
                        selectExpeditionName.prop('disabled',false);
                    }

                    selectExpeditionName.select2();
                    
                },
                error: function(xhr, status, error) {
                    return false;
                }
            });
        });
        
    }    

    // get param url
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

    // get selected expedition
    function getSelectedExpedition(elementRoot){
        let inputNameExpeditionCost = $('[name=kj_expedition_cost]');
        let insuranceFeeClass = $('.insurancefee');
        let codFeeClass = $('.codfee');
        let insuranceHiddenName = $('[name="kj_insurancefee_hidden"]');
        let codfeeHiddenName = $('[name="kj_codfee_hidden"]');
        let debounceTimeout;

        selectExpeditionName = $('select[name=kj_expedition]');

        selectExpeditionName.on('change', function() {

            clearTimeout(debounceTimeout);

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

            if( inputNameExpeditionCost.length > 0 ){
                inputNameExpeditionCost.val(expedition_cost);
                $('[name=kj_expedition_name]').val(expedition_name);
            }else{
                elementRoot.find('.shipping_method').closest('.edit').append(html);            
            }

            let destination = getIdDestinationAndNameDestination();
            let id_destination = destination.id_destination;
            let name_destination = destination.name_destination;

            let data = {
                'action':'kj_calculation_CodFeeAndInsuranceFee',
                'order_id': orderID,
                'kj_subdistrict': Number(id_destination),
                'kj_subdistrict_name':name_destination,
                'kj_expedition':$(this).find("option:selected").val(),
                'kj_expedition_name':expedition_name,
                'kj_expedition_cost': parseFloat(expedition_cost),

            debounceTimeout = setTimeout(() => {                
                $.ajax({
                    url:kj.ajaxurl,
                    type: 'POST',
                    data:data,
                    dataType: 'json',
                    beforeSend: function() {
                        codFeeClass.find('.total').html('Waiting ...');
                        insuranceFeeClass.find('.total').html('Waiting ...');
                    },
                    success: function(response) {
                        
                        insuranceFeeClass.find('.total').html(response.data.insurance_fee);
                        insuranceHiddenName.val(response.data.insurance_fee_number);
                        
                        if( $('[name="_shipping_kj_insurance"]:checked').length > 0 ){
                            insuranceFeeClass.show();
                            // set insurance
                            insuranceFeeClass.find('.total').html(response.data.insurance_fee);
                            insuranceHiddenName.val(response.data.insurance_fee_number);
                        }else if( $('[name="_billing_kj_insurance"]:checked').length > 0 ){
                            insuranceFeeClass.show();
                            insuranceFeeClass.find('.total').html(response.data.insurance_fee);
                            insuranceHiddenName.val(response.data.insurance_fee_number);
                        }else{
                            insuranceFeeClass.hide();
                        }
                                            
    
                        if( $('[name="_payment_method"]').val() == 'cod' ) {
                            codFeeClass.show();
                        }else{
                            codFeeClass.hide();
                        }
                        
                        if( response?.data?.cod_fee != '0' ) codFeeClass.find('.total').html(response.data.cod_fee);
                        if( response?.data?.cod_fee_number != '0' ) codfeeHiddenName.val(response.data.cod_fee_number);
                        
    
                        get_OnChangeCodAndInsurance();
    
                    },
                    error: function(xhr, status, error) {
                        return false;
                    }
                });
            },200);

        });
    }

    // display information codfee and insurancefee
    get_OnChangeCodAndInsurance();
    function get_OnChangeCodAndInsurance(){
        let insuranceClass = $('.insurancefee');
        let codfeeClass = $('.codfee');

        $(document).on('change','[name="_payment_method"],[name="_billing_kj_insurance"],[name="_shipping_kj_insurance"]',function(){

            let root = $(this);
            let getNameElement = root.attr('name'); 
                        
            if( getNameElement == '_payment_method' ){
                
                if( root.val() == 'cod' ){
                    codfeeClass.show();
                }else{
                    codfeeClass.hide();
                }
            }else{
                if( root.is(':checked') ){
                    insuranceClass.show();
                } else{
                    insuranceClass.hide();
                }
            }
        });
    }

    // chceking is product item order in detail order
    ajaxCheckProductItemOrder();
    function ajaxCheckProductItemOrder(){
        let elementClassAddOrder = $('.add-order-fee,.add-order-shipping,.add-order-tax');
        
        $.ajax({
            url: kj.ajaxurl,
            type: 'POST',
            data: {
                'action': 'check_product_item_order',
                'order_id': orderID,
                'nonce': kj.nonce
            },
            dataType: 'json',
            success: function(response) { 
                                  
               if( response.data.success == false ){
                    elementClassAddOrder.prop('disabled',true);
               }else{
                    elementClassAddOrder.prop('disabled',false);
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

    // check edit shipping item order 
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

    // alert message error
    function alert_message_error(message){
        let html = `<div class="kj-alert">
                      <span class="kj-closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
                        ${message}
                    </div>`;
        return html;
    }

    // get destination ID and name destination
    function getIdDestinationAndNameDestination(){
        let id_destination;
        let name_destination;

        selectNameBilling  = $('[name="_billing_kj_destination_area"] option:selected');
        selectNameShipping = $('[name="_shipping_kj_destination_area"] option:selected');
        
        selectSubdistrictName = $('select[name="kj_subdistrict"] option:selected');


        if(selectNameShipping.text() == '' ){
            if(selectNameBilling.val() != '') {                
                id_destination = selectNameBilling.val();
                name_destination = selectNameBilling.text();
            }else{
                id_destination = selectSubdistrictName.val();
                name_destination = selectSubdistrictName.text();
            }
        }else{
            id_destination = selectNameShipping.val();
            name_destination = selectNameShipping.text();   
        }
        
        return {id_destination: id_destination, name_destination: name_destination};
    }

    // set Read Only property
    function isReadOnly(element){
        document.querySelector(element).readOnly = true;
    }

    // set Hidden property
    function isDisplayNone(element){
        document.querySelector(element).style.display = 'none';
    }

    function validationAddChangeProductItems(){
        let classModalcontent = $('.wc-backbone-modal-content');
        let classSelectProductItems = classModalcontent.find('select.wc-product-search');
        let postData,productID;
        let kjWrapping;
        let root;

        $(document).on('change','select.wc-product-search', function(){
            root = $(this);
            productID = $(this).val();

            if (productID && productID.length > 0) {
                postData = {
                    action:'kj_validation_add_product_items',
                    productID: productID,
                    nonce: kj.nonce
                };
                
                $(this).closest('td').find('.select2.select2-container').after('<div class="kj-wrapping">');

                kjWrapping = $('.kj-wrapping');

                kjWrapping.css({
                    'margin': '10px 0 0 0',
                    'background': '#e5e5e5',
                    'padding': '4px 5px',
                    'border-radius': '5px'
                });

                kjWrapping.html('<p>Mohon Tunggu Sebentar ...</p>');
                
                $.post(kj.ajaxurl, postData, function (response) { 

                    kjWrapping.html(`<p>${response?.data?.message}</p>`);   

                    if(response?.success == false){
                        kjWrapping.css('background','#ff0000');
                        kjWrapping.find('p').css('color','white');
                        root.val(null).trigger('change');
                    }else{
                        kjWrapping.css('background','#27a700');
                        kjWrapping.find('p').css('color','white');
                    }

                    setTimeout(() => {
                        kjWrapping.remove(); 
                    }, 1000);  
                    
                    
                });
            }
            
        });
    }

});
