<div class="wrap kj-wrap">
    <div id="root">
        <div class="woocommerce-layout">
            <div class="woocommerce-layout__header is-scrolled">
                <div class="woocommerce-layout__header-wrapper">
                    <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00"><?php echo esc_html( kjHelper()->tlThis('KiriminAja Configuration',$locale) ); ?></h1>
                </div>
            </div>
            <div class="woocommerce-layout__primary" id="woocommerce-layout__primary">
                <div id="woocommerce-layout__notice-list" class="woocommerce-layout__notice-list"></div>
                <div class="woocommerce-layout__main">

                    <div class="woocommerce-homescreen">
                        <div class="woocommerce-homescreen-column" style="position: static;width: 100%">

                            <div>
                                <!-- check jika sudah install woocommerce-->
                                <?php
                                if (!KJ_CHECK_WOOCOMMERCE() || kjHelper()->devForceTrue()){
                                    echo '
                                    <div style="padding-left: 5px; background-color: #7d3eb9; margin-bottom: .5rem">
                                    <div style="padding: 12px; border: 1px solid #c3c4c7; background-color: white">
                                        <div style="display:flex;">
                                            <div>
                                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M1 10C1.41 10.29 1.96 10.43 2.5 10.43C3.05 10.43 3.59 10.29 4 10C4.62 9.54 5 8.83 5 8C5 8.83 5.37 9.54 6 10C6.41 10.29 6.96 10.43 7.5 10.43C8.05 10.43 8.59 10.29 9 10C9.62 9.54 10 8.83 10 8C10 8.83 10.37 9.54 11 10C11.41 10.29 11.96 10.43 12.51 10.43C13.05 10.43 13.59 10.29 14 10C14.62 9.54 15 8.83 15 8C15 8.83 15.37 9.54 16 10C16.41 10.29 16.96 10.43 17.5 10.43C18.05 10.43 18.59 10.29 19 10C19.63 9.54 20 8.83 20 8V7L17 0H4L0 7V8C0 8.83 0.37 9.54 1 10ZM3 18.99H8V13.99H12V18.99H17V11.99C16.63 11.94 16.28 11.77 16 11.56C15.37 11.11 15 10.83 15 10C15 10.83 14.62 11.11 14 11.56C13.59 11.86 13.05 11.99 12.51 12C11.96 12 11.41 11.86 11 11.56C10.37 11.11 10 10.83 10 10C10 10.83 9.62 11.11 9 11.56C8.59 11.86 8.05 11.99 7.5 12C6.96 12 6.41 11.86 6 11.56C5.37 11.11 5 10.83 5 9.99C5 10.83 4.62 11.11 4 11.56C3.71 11.77 3.37 11.94 3 12V18.99Z" fill="black"/>
                                                </svg>
                                            </div>
                                            <div style="margin-left: 8px">
                                                <div style="font-weight: 600; font-size: 16px;">
                                                    '.esc_html( kjHelper()->tlThis('WooCommerce is not yet installed or activated',$locale)).'                                       
                                                </div>
                                                <div class="row-divider" style="margin-top: .5rem"></div>
                                                <div style="font-weight: 500;">
                                                    '.esc_html( kjHelper()->tlThis('This plugin only support WooCommerce features. Please install and activate Woocommerce to fully use this plugin features',$locale)).'
                                                </div>
                                            </div>
                                        </div>
                                    </div>                                    
                                    </div>
                                    ';
                                }
                                ?>
                                
                                <!--Check jika sudah setting origin data-->
                                <?php 
                                if (@$isOriginShippingDataReady || kjHelper()->devForceTrue()){
                                    echo '
                                    <div style="padding-left: 5px; background-color: #00a32a">
                                        <div style="padding: 12px; border: 1px solid #c3c4c7; background-color: white">
                                        <div style="display:flex;">
                                            <div>
                                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M1 10C1.41 10.29 1.96 10.43 2.5 10.43C3.05 10.43 3.59 10.29 4 10C4.62 9.54 5 8.83 5 8C5 8.83 5.37 9.54 6 10C6.41 10.29 6.96 10.43 7.5 10.43C8.05 10.43 8.59 10.29 9 10C9.62 9.54 10 8.83 10 8C10 8.83 10.37 9.54 11 10C11.41 10.29 11.96 10.43 12.51 10.43C13.05 10.43 13.59 10.29 14 10C14.62 9.54 15 8.83 15 8C15 8.83 15.37 9.54 16 10C16.41 10.29 16.96 10.43 17.5 10.43C18.05 10.43 18.59 10.29 19 10C19.63 9.54 20 8.83 20 8V7L17 0H4L0 7V8C0 8.83 0.37 9.54 1 10ZM3 18.99H8V13.99H12V18.99H17V11.99C16.63 11.94 16.28 11.77 16 11.56C15.37 11.11 15 10.83 15 10C15 10.83 14.62 11.11 14 11.56C13.59 11.86 13.05 11.99 12.51 12C11.96 12 11.41 11.86 11 11.56C10.37 11.11 10 10.83 10 10C10 10.83 9.62 11.11 9 11.56C8.59 11.86 8.05 11.99 7.5 12C6.96 12 6.41 11.86 6 11.56C5.37 11.11 5 10.83 5 9.99C5 10.83 4.62 11.11 4 11.56C3.71 11.77 3.37 11.94 3 12V18.99Z" fill="black"/>
                                                </svg>
                                            </div>
                                            <div style="margin-left: 8px">
                                                <div style="font-weight: 600; font-size: 16px;">
                                                    '.esc_html( kjHelper()->tlThis('All Setup',$locale) ).'!
                                                </div>
                                                <div class="row-divider" style="margin-top: .5rem"></div>
                                                <div style="font-weight: 500;">
                                                    '.esc_html( kjHelper()->tlThis('Now you’re connected with KiriminAja',$locale) ).'
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    ';
                                }else{
                                    echo '
                                    <div style="padding-left: 5px; background-color: #7d3eb9">
                                    <div style="padding: 12px; border: 1px solid #c3c4c7; background-color: white">
                                        <div style="display:flex;">
                                            <div>
                                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M1 10C1.41 10.29 1.96 10.43 2.5 10.43C3.05 10.43 3.59 10.29 4 10C4.62 9.54 5 8.83 5 8C5 8.83 5.37 9.54 6 10C6.41 10.29 6.96 10.43 7.5 10.43C8.05 10.43 8.59 10.29 9 10C9.62 9.54 10 8.83 10 8C10 8.83 10.37 9.54 11 10C11.41 10.29 11.96 10.43 12.51 10.43C13.05 10.43 13.59 10.29 14 10C14.62 9.54 15 8.83 15 8C15 8.83 15.37 9.54 16 10C16.41 10.29 16.96 10.43 17.5 10.43C18.05 10.43 18.59 10.29 19 10C19.63 9.54 20 8.83 20 8V7L17 0H4L0 7V8C0 8.83 0.37 9.54 1 10ZM3 18.99H8V13.99H12V18.99H17V11.99C16.63 11.94 16.28 11.77 16 11.56C15.37 11.11 15 10.83 15 10C15 10.83 14.62 11.11 14 11.56C13.59 11.86 13.05 11.99 12.51 12C11.96 12 11.41 11.86 11 11.56C10.37 11.11 10 10.83 10 10C10 10.83 9.62 11.11 9 11.56C8.59 11.86 8.05 11.99 7.5 12C6.96 12 6.41 11.86 6 11.56C5.37 11.11 5 10.83 5 9.99C5 10.83 4.62 11.11 4 11.56C3.71 11.77 3.37 11.94 3 12V18.99Z" fill="black"/>
                                                </svg>
                                            </div>
                                            <div style="margin-left: 8px">
                                                <div style="font-weight: 600; font-size: 16px;">
                                                    '.esc_html( kjHelper()->tlThis('Fill The Shipment Address',$locale) ).'
                                                </div>
                                                <div class="row-divider" style="margin-top: .5rem"></div>
                                                <div style="font-weight: 500;">
                                                    '.esc_html( kjHelper()->tlThis('Complete shipping information to enable pricing API',$locale) ).'
                                                </div>
                                                <div class="row-divider" style="margin-top: .5rem"></div>
                                                <div>
                                                    <button onclick="toggleThis(this,`tab-shipping`)" style="width: auto !important;padding: 6px 12px !important;" class="button-primary woocommerce-save-button" type="button">
                                                        <div style="display: flex">
                                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                <svg style="position: relative; top: 2px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_7_1708)">
                                                                        <path d="M0.8 8C1.128 8.232 1.568 8.344 2 8.344C2.44 8.344 2.872 8.232 3.2 8C3.696 7.632 4 7.064 4 6.4C4 7.064 4.296 7.632 4.8 8C5.128 8.232 5.568 8.344 6 8.344C6.44 8.344 6.872 8.232 7.2 8C7.696 7.632 8 7.064 8 6.4C8 7.064 8.296 7.632 8.8 8C9.128 8.232 9.568 8.344 10.008 8.344C10.44 8.344 10.872 8.232 11.2 8C11.696 7.632 12 7.064 12 6.4C12 7.064 12.296 7.632 12.8 8C13.128 8.232 13.568 8.344 14 8.344C14.44 8.344 14.872 8.232 15.2 8C15.704 7.632 16 7.064 16 6.4V5.6L13.6 0H3.2L0 5.6V6.4C0 7.064 0.296 7.632 0.8 8ZM2.4 15.192H6.4V11.192H9.6V15.192H13.6V9.592C13.304 9.552 13.024 9.416 12.8 9.248C12.296 8.888 12 8.664 12 8C12 8.664 11.696 8.888 11.2 9.248C10.872 9.488 10.44 9.592 10.008 9.6C9.568 9.6 9.128 9.488 8.8 9.248C8.296 8.888 8 8.664 8 8C8 8.664 7.696 8.888 7.2 9.248C6.872 9.488 6.44 9.592 6 9.6C5.568 9.6 5.128 9.488 4.8 9.248C4.296 8.888 4 8.664 4 7.992C4 8.664 3.696 8.888 3.2 9.248C2.968 9.416 2.696 9.552 2.4 9.6V15.192Z" fill="white"/>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_7_1708">
                                                                            <rect width="16" height="16" fill="white"/>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>

                                                                <span style="margin-left: 6px">'.esc_html( kjHelper()->tlThis('Set Address',$locale) ).'</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>                                    
                                    </div>
                                    ';
                                }
                                ?>

                                <div class="row-divider"></div>
                                <!--NAVBAR-->
                                <nav style="margin-top: 1rem;margin-bottom: 1.5rem" class="nav-tab-wrapper woo-nav-tab-wrapper">
                                    <a href="#" onclick="toggleThis(this,'tab-integration')" class="nav-tab tab-integration nav-tab-active"><?php echo esc_html( kjHelper()->tlThis('Integration',$locale) ); ?></a>
                                    <?php
                                    if (@$approvedSetupKey->value){
                                        echo '<a href="#" onclick="toggleThis(this,`tab-shipping`)" class="nav-tab tab-shipping">'.esc_html( kjHelper()->tlThis('Shipping',$locale) ).'</a>';
                                        echo '<a href="#" onclick="toggleThis(this,`tab-advanced`)" class="nav-tab tab-advanced">'.esc_html( kjHelper()->tlThis('Advanced',$locale) ).'</a>';
                                    }
                                    ?>
                                </nav>
                                
                                <!--SWITCHABLE CONTENT-->
                                <div>
                                    <div class="kj-menu-content tab-integration kj-hidden">
                                        <?php include 'part-integration.php' ?>
                                    </div>

                                    <div class="kj-menu-content tab-shipping kj-hidden">
                                        <?php include 'part-origin-setup.php' ?>
                                    </div>

                                    <div class="kj-menu-content tab-advanced kj-hidden">
                                        <?php include 'part-callback-setup.php' ?>
                                    </div>
                                </div>
                                <div class="row-divider"></div>
                                <p style="font-weight: 500">KiriminAja Plugin v.<?php echo wp_kses_post( KJ_VERSION_PLUGIN ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="woocommerce-layout__footer">
                    <div class="components-snackbar-list woocommerce-transient-notices components-notices__snackbar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
    


<script type="text/javascript">
    var map;
    var ajaxRequest;
    var plotlist;
    var plotlayers=[];

    // Init Open Street Maps
    function initmap() {
        
        // set up the map
        map = new L.Map('map');
        var osmUrl='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        var osmAttrib='Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors';
        var osm = new L.TileLayer(osmUrl, {minZoom: 2, maxZoom: 19, attribution: osmAttrib});
        map.setView(new L.LatLng(48.420296, 9.964151),15);
        map.addLayer(osm);

        var marker = L.marker([48.420296, 9.964151]).addTo(map);
        marker.bindPopup("<b>Botanischer Garten</b><br>is here").openPopup();
    }


    /** Page Content Util*/
    function toggleThis(elem,menu){
        window.location.href = '<?php echo esc_url(home_url()).'/wp-admin/admin.php?page=kiriminaja-konfigurasi&tab='?>'+menu
    }
    
    function getTabData(){
        var menu = '<?php echo esc_html($activeTab);?>'
        
        jQuery('.nav-tab').removeClass("nav-tab-active")
        jQuery(`.nav-tab.${menu}`).addClass("nav-tab-active");
        jQuery('.kj-menu-content').addClass('kj-hidden')
        jQuery(`.${menu}`).removeClass('kj-hidden')
        formAlertToggler(menu,false)        
    }
    
    function menuFormLoaderInit(menuClass,loaderStatus){
        if (loaderStatus){
            jQuery(`.${menuClass} .kj-btn-container`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-btn-loader-container`).removeClass('kj-hidden')
        } else {
            jQuery(`.${menuClass} .kj-btn-container`).removeClass('kj-hidden')
            jQuery(`.${menuClass} .kj-btn-loader-container`).addClass('kj-hidden')
        }
    }
    function formAlertToggler(menuClass,showAlert=false,title='Alert',subTitle='',alertStatus=''){

        jQuery(`.${menuClass} .kj-form .kj-alert .msg`).text(subTitle)

        jQuery(`.${menuClass} .kj-form .kj-alert`).removeClass('success')
        if (alertStatus === 'success'){
            jQuery(`.${menuClass} .kj-form .kj-alert`).addClass('success')
        }
        if(!showAlert){
            jQuery(`.${menuClass} .kj-form .kj-alert`).addClass('kj-hidden')
            return
        }
        jQuery(`.${menuClass} .kj-form .kj-alert`).removeClass('kj-hidden')
    }


</script>
<!--Integration-->
<script type="text/javascript">

    /** Integration AJAX*/
    /*** Submit DATA*/
    jQuery('body').on('click', '.kj-disconnect', function(e) {
        menuFormLoaderInit('tab-integration',true)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_disconnect_integration",  // the action to fire in the server
                data: {},         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status === 200){
                    window.location.reload()
                    return
                }
                menuFormLoaderInit('tab-integration',false)
                menuFormLoaderInit('tab-advanced',false)
                formAlertToggler('tab-integration',true,'Error',resp.message,'')
                formAlertToggler('tab-advanced',true,'Error',resp.message,'')
            },
        });
    });

</script>
<!--Origin Setup-->
<script type="text/javascript">
    /** Origin AJAX*/
    /*** Origin Ajax*/
    const areaSelectName = 'origin_sub_district_id';
    const areaSelectElem = jQuery(`.tab-shipping [name="${areaSelectName}"]`);
    const areaSelectElemSearchFieldId = 'origin_sub_district_search_field';


    jQuery(document).ready(function($) {
        // initmap();

        getSearchAreaKelurahan();
        searchExpedition();

        getTabData()
    });
    
    let subdistrictAjaxTimeout = null
    
    function getSearchAreaKelurahan(){
       
            areaSelectElem.select2({
                minimumInputLength: 3,
                placeholder: "<?php echo  esc_html( kjHelper()->tlThis('Select Option',$locale) ); ?>",
                allowClear: true,
                ajax: {
                    url: ajaxRouteGenerator(),
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
                }
            }); 
    }

    function searchExpedition(){
       
       jQuery('.tab-shipping [name="origin_whitelist_expedition[]"]').select2({
           placeholder: "<?php echo  esc_html( kjHelper()->tlThis('Select Option',$locale) ); ?>",
           allowClear: true,
           ajax: {
               url: ajaxRouteGenerator(),
               dataType: 'json',
               type: "POST",
               delay: 250,
               data: function (search) {
                   return {
                       data:search,
                       action: 'kiriminaja_search_expedition',
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
           }
       }); 
    }

    /*** Submit DATA*/
    jQuery('body').on('click', '.tab-shipping .kj-submit-btn', function(e) {
        menuFormLoaderInit('tab-shipping', true)
        formAlertToggler('tab-shipping',false)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_store_origin_data",  // the action to fire in the server
                data: {
                    origin_name:jQuery('.tab-shipping [name="origin_name"]').val(),
                    origin_phone:jQuery('.tab-shipping [name="origin_phone"]').val(),
                    origin_address:jQuery('.tab-shipping [name="origin_address"]').val(),
                    origin_latitude:jQuery('.tab-shipping [name="origin_latitude"]').val(),
                    origin_longitude:jQuery('.tab-shipping [name="origin_longitude"]').val(),
                    origin_zip_code:jQuery('.tab-shipping [name="origin_zip_code"]').val(),
                    origin_sub_district_id:jQuery('.tab-shipping [name="origin_sub_district_id"] option:selected').val(),
                    origin_sub_district_name:jQuery('.tab-shipping [name="origin_sub_district_id"] option:selected').text(),
                    origin_whitelist_expedition_id:jQuery('.tab-shipping .origin_whitelist_expedition').val(),
                    origin_whitelist_expedition_name: jQuery('.tab-shipping .origin_whitelist_expedition').select2('data').map(function(elem){ return elem.text }),
                },         // any JS object
            },
            complete: function (response) {
                
                const resp = JSON.parse(response.responseText).data;
                
                if (resp?.status === 200){
                    window.location.reload()
                    return
                }

                menuFormLoaderInit('tab-shipping',false)
                formAlertToggler('tab-shipping',true,'Error',resp.message,'')


            },
        });
    })

</script>
<!--Callback Setup-->
<script type="text/javascript">
    /*** Submit DATA*/
    jQuery('body').on('click', '.tab-advanced .kj-submit-btn', function(e) {
        menuFormLoaderInit('tab-advanced', true)

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_store_call_back_data",  // the action to fire in the server
                data: {
                    callback_url:jQuery('.tab-advanced [name="callback_url"]').val(),
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;

                menuFormLoaderInit('tab-advanced',false)
                if (resp?.status === 200){
                    formAlertToggler('tab-advanced',true,'Success',resp.message,'success')
                    fetchCallbackData()
                    return
                }
                formAlertToggler('tab-advanced',true,'Error',resp.message,'')

            },
        });
    })
</script>