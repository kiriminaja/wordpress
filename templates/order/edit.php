<script>
    var $ = jQuery;
    var orderIDdataItem;
    var orderIDdataItemShipping = document.querySelector('#order_shipping_line_items tr.shipping').getAttribute('data-order_item_id');
    var orderItemLineClass = $('#order_shipping_line_items .shipping');

    jQuery(document).ready(function (){  
        /**
         * Get Tracking By Order Number (Order ID) from Woocommerce
         **/      
        getKiriminAjaTransactionData();
        
        /** 
         * First Load billing && Shipping Address Subdistrict
         * [+] Search Subdistrict
         * [+] Selected Destination Subdistrict
         */
        getSearchAreaKelurahan();
        getSelectedSubdistrictAdminOrder();

        /**
         * Auto Selected Expedition
         */
        autoLoadExpeditionSelected();

        /**
         * Adjust Shipping Kiriminaja
         */
        cekShippingMethodOrder();

        /**
         * COD Insurance Shipping
         */
        get_OnChangeCodAndInsurance();

        /**
         * Validate Edit Shipping 
         */
        editShippingOrder();

        /**
         * Get Shipping Method Kiriminaja
         */
        getShippingMethod();

        /**
         * Check Product Item Order 
         * if Item Product Exist Button Shipping Disabled else Button Shipping Un-Disabled
         */
        ajaxCheckProductItemOrder();

        /** 
         * Set readonly  
         * Shipping Cost
         * Shipping Method Title
        */
        isReadOnly(`[name="shipping_cost[${orderIDdataItemShipping}]"]`);
        isReadOnly(`[name="shipping_method_title[${orderIDdataItemShipping}]"]`);

        /**
         * Set Display None
         * shipping method item
         * button meta shipping
         */
        isDisplayNone('.wc-order-data-row-toggle > .add-order-shipping');
        isDisplayNone('#order_shipping_line_items .add_order_item_meta.button');
    });

    function isReadOnly(element){
        document.querySelector(element).readOnly = true;
    }

    function isDisplayNone(element){
        document.querySelector(element).style.display = 'none';
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
        // setTimeout(() => {                
        //     let subdistrictElement = document.querySelector('[name="kj_subdistrict"]');
        //     let select2Container = document.querySelector('[name="kj_subdistrict"]').nextElementSibling;
            
        //     if (!subdistrictElement && select2Container && select2Container.classList.contains('select2')) {
        //         select2Container.style.display = 'none';
        //     }

        // }, 500);
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

    function autoLoadExpeditionSelected(){
        orderIDdataItem = $('#order_shipping_line_items tr.shipping ').attr('data-order_item_id');
        
        setTimeout(() => {
            let idExpedition = $('[name="kj_expedition"] option').filter(function() {
                return $(this).text() === jQuery(`[name="shipping_method_title[${orderIDdataItem}]"]`).val();
            }).val();
          
            $('[name="kj_expedition"]').val(idExpedition).trigger("change"); 
            
        }, 6000);
    }

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
        // if( getUrlParameter('post') != false && getUrlParameter('action') == 'edit' ){
        
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select style="display:none;" name="kj_subdistrict" class="selet2" style="width:100%;"></select>`);            
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<select name="kj_expedition" class="selet2" style="width:100%;"><option value="">Select Expeditions</option></select>`);               
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" name="kj_subdistrict_name"/>`);               
            
            /** COD AND INSURANCE */
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="codfeehidden" name="kj_codfee_hidden"/>`);                    
            $('#order_shipping_line_items').find('.shipping_method').closest('.edit').append(`<input type="hidden" class="insurancefeehidden" name="kj_insurancefee_hidden"/>`);                    
            
            
            getSearchAreaKelurahan();
            getExpeditionByPricing();   
            getSelectedExpedition(elementRoot);
        // }
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
                'order_id': woocommerce_admin_meta_boxes.post_id,
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

                    }else{
                        expeditionName.html(expedition_options);
                        expeditionName.prop('disabled',false);
                    }

                    expeditionName.select2();
                    
                },
                error: function(xhr, status, error) {
                    alert('Something Wrong This Site Get Expedition Field Error Code : '+xhr.status);
                    return false;
                }
            });
        });
        
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
                    
                    $('.codfee').find('.total').html(response.data.cod_fee);
                    $('[name="kj_codfee_hidden"]').val(response.data.cod_fee_number);
                    

                    get_OnChangeCodAndInsurance();

                },
                error: function(xhr, status, error) {
                    alert('Something Wrong This Site Get Expedition Field Error Code : '+xhr.status);
                    return false;
                }
            });

        });
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
                alert('Something Wrong This Site Get Product Item Order Error Code : '+xhr.status);
                return false;
            }
        });
    }

    function getKiriminAjaTransactionData(){
        let orderId = `{$orderId}`
        let trackingUrl = `{$trackingUrl}`
        let kjOrderData = `{$kjOrderData}`
        let kjOrderDataParsed = JSON.parse(kjOrderData)
        
        console.log(`orderId : ${orderId}`)
        let tBodycontent = ''
        
        jQuery('#side-sortables').append(`
<div id="woocommerce-customer-history" class="postbox ">
   <div class="postbox-header">
      <h2 class="hndle ui-sortable-handle">Shipping</h2>
      <div class="handle-actions hide-if-no-js"><button type="button" class="handle-order-higher" aria-disabled="false" aria-describedby="woocommerce-customer-history-handle-order-higher-description"><span class="screen-reader-text">Move up</span><span class="order-higher-indicator" aria-hidden="true"></span></button><span class="hidden" id="woocommerce-customer-history-handle-order-higher-description">Move Customer history box up</span><button type="button" class="handle-order-lower" aria-disabled="false" aria-describedby="woocommerce-customer-history-handle-order-lower-description"><span class="screen-reader-text">Move down</span><span class="order-lower-indicator" aria-hidden="true"></span></button><span class="hidden" id="woocommerce-customer-history-handle-order-lower-description">Move Customer history box down</span><button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel: Customer history</span><span class="toggle-indicator" aria-hidden="true"></span></button></div>
   </div>

   <div class="inside">
    <!--Content-->
    <table id="kj-order-edit-shipping" style="width: 100%">
        <tbody>
            <tr>
                <th>Order ID <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.order_id}</td>
            </tr>
            <tr>
                <th>Pickup ID <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.pickup_id}</td>
            </tr>
            <tr>
                <th>Payment <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.payment_type}</td>
            </tr>
            <tr>
                <th>Service <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.service}</td>
            </tr>
            <tr>
                <th>AWB <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.awb}</td>
            </tr>
            <tr>
                <th>Status <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.status}</td>
            </tr>
            <tr>
                <th>Shipping Cost <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.shipping_cost}</td>
            </tr>
            `
            +
            /** COD ROW */
            (
            kjOrderDataParsed?.cod_fee !=='-'
            ?    
             `
            <tr>
                <th>COD Fee <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.cod_fee}</td>
            </tr>
            `   
             :
            ``
            )
            +
            /** Insurance ROW */
            (
            kjOrderDataParsed?.insurance_fee !=='-'
                ?
                `
            <tr>
                <th>Insurance Fee <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.insurance_fee}</td>
            </tr>
            `
                :
                ``
            )
            +
            `
            <tr>
                <th>Order Total<span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.transaction_value}</td>
            </tr>
            <tr>
                <th>Total <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.total}</td>
            </tr>
        </tbody>
    </table>
   </div>
    <!--BTN-->

    <div class="add_note" style="padding: 10px">
        <div style="display: relative">
            <button type="button" style="width: 100%" class="button save_order button-primary" name="save" value="Update">Shipment Tracker</button>        
            <a href="${trackingUrl}" target="_blank" style="position: absolute; top: 0;left: 0;bottom: 0;right: 0"></a>
        </div>
    </div>
</div>
        `)
        
    }
</script>