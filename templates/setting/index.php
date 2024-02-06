<?php
global $wpdb;
$approvedSetupKey = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_settings WHERE `key`  = 'setup_key'");
?>

<div class="wrap">
    <h1>Konfigurasi KiriminAja</h1>
    <nav style="margin-top: 1rem;margin-bottom: 1.5rem" class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="#" onclick="toggleThis(this,'menu_1')" class="nav-tab menu_1 nav-tab-active">Integrasi KiriminAja</a>
        <?php
        if (@$approvedSetupKey->value||true){
            echo '<a href="#" onclick="toggleThis(this,`menu_2`)" class="nav-tab menu_2">Atur Lokasi</a>';
            echo '<a href="#" onclick="toggleThis(this,`menu_3`)" class="nav-tab menu_3">Pengaturan Callback</a>';
        }
        ?>
    </nav>
    <div>
        <div class="kj-menu-content menu_1">
            <?php include 'part-integration.php' ?>
        </div>

        <div class="kj-menu-content menu_2 kj-hidden">
            <?php include 'part-origin-setup.php' ?>
        </div>

        <div class="kj-menu-content menu_3 kj-hidden">
            <?php include 'part-callback-setup.php' ?>
        </div>
    </div>
<!--    <div style="position: relative;overflow: hidden;display: none">-->
<!--        <div class="mt">Map Example</div>-->
<!--        <div id="map"></div>-->
<!--        <div class="mt">Map Example</div>-->
<!--    </div>-->


</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        // initmap();
        jQuery('.nav-tab.menu_1').click()

    });
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
        jQuery('.nav-tab').removeClass("nav-tab-active")
        jQuery(elem).addClass("nav-tab-active");

        jQuery('.kj-menu-content').addClass('kj-hidden')
        jQuery(`.${menu}`).removeClass('kj-hidden')

        if (menu === 'menu_1'){
            fetchIntegrationData()
        }else if (menu === 'menu_2'){
            fetchStoreOriginData()
        }else if (menu === 'menu_3'){
            fetchCallbackData()
        }
        formAlertToggler(menu,false)
    }
    function menuContentLoaderInit(menuClass,loaderStatus){
        if (loaderStatus){
            jQuery(`.${menuClass} .kj-form`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-loader`).removeClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-err`).addClass('kj-hidden')
        } else {
            jQuery(`.${menuClass} .kj-form`).removeClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-loader`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-err`).addClass('kj-hidden')
        }
    }
    function menuContentErr(menuClass,errStatus){
        if (errStatus){
            jQuery(`.${menuClass} .kj-form`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-loader`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-err`).removeClass('kj-hidden')
        } else {
            jQuery(`.${menuClass} .kj-form`).removeClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-loader`).addClass('kj-hidden')
            jQuery(`.${menuClass} .kj-form-err`).addClass('kj-hidden')
        }
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
        
        jQuery(`.${menuClass} .kj-form .kj-alert .title`).text(title)
        jQuery(`.${menuClass} .kj-form .kj-alert .sub-title`).text(subTitle)
        
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
    /*** Fetch DATA*/
    function fetchIntegrationData(){
        menuContentLoaderInit('menu_1',true)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_integration_data",  // the action to fire in the server
                data: {},         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText)
                if (resp.data.status!==200) {
                    menuContentLoaderInit('menu_1',false)
                    menuContentErr('menu_1',true)
                    return
                }
                const responseData = resp.data.data;
                if (responseData?.setup_key){
                    jQuery('.menu_1 [name="setup_key"]').val(responseData?.setup_key)
                    jQuery('.menu_1 [name="prefix"]').val(responseData?.oid_prefix)                    
                }
                menuContentLoaderInit('menu_1',false)
            },
        });
    }
    /*** Submit DATA*/
    jQuery('body').on('click', '.menu_1 .kj-submit-btn', function(e) {
        menuFormLoaderInit('menu_1',true)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_store_integration_data",  // the action to fire in the server
                data: {
                    setup_key:jQuery('.menu_1 [name="setup_key"]').val()
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                menuFormLoaderInit('menu_1',false)
                if (resp?.status === 200){
                    formAlertToggler('menu_1',true,'Success',resp.message,'success')
                    window.location.reload()
                    return
                }
                formAlertToggler('menu_1',true,'Error',resp.message,'')
                

            },
        });
    });
    
</script>
<!--Origin Setup-->
<script type="text/javascript">
    /** Origin AJAX*/
    /*** Fetch DATA*/
    function fetchStoreOriginData(){
        jQuery('.menu_2 [name="origin_sub_district_id"]').select2()
        menuContentLoaderInit('menu_2',true)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_origin_data",  // the action to fire in the server
                data: {},         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText)
                if (resp.data.status!==200) {
                    menuContentLoaderInit('menu_2',false)
                    menuContentErr('menu_2',true)
                    return
                }
                const responseData = resp.data.data;
                if (responseData?.origin_name){
                    jQuery('.menu_2 [name="origin_name"]').val(responseData?.origin_name)
                    jQuery('.menu_2 [name="origin_phone"]').val(responseData?.origin_phone)
                    jQuery('.menu_2 [name="origin_address"]').val(responseData?.origin_address)
                    jQuery('.menu_2 [name="origin_sub_district_id"]').select2('destroy')
                    jQuery('.menu_2 [name="origin_sub_district_id"]').empty()
                    jQuery('.menu_2 [name="origin_sub_district_id"]').append(`<option selected value="${responseData?.origin_sub_district_id}">${responseData?.origin_sub_district_name}</option>`)
                    jQuery('.menu_2 [name="origin_sub_district_id"]').select2()                    
                }
                
                /** Geolocation ongoing*/

                menuContentLoaderInit('menu_2',false)
            },
        });
    }
    /*** Origin Ajax*/
    const elemSelectName = 'origin_sub_district_id';
    let subdistrictAjaxTimeout = null
    jQuery('body').on('keyup', `.select2-search__field`, function (e) {
        const thisElem = jQuery(this);
        if (!jQuery(this).attr('aria-controls').includes(`${elemSelectName}`)){return}

        const searchInputVal = jQuery(this).val()
        if (subdistrictAjaxTimeout){ clearTimeout(subdistrictAjaxTimeout) }
        subdistrictAjaxTimeout = setTimeout(function (){
            jQuery(`[name="${elemSelectName}"]`).empty()
            jQuery(`[name="${elemSelectName}"]`).append("<option value='' disabled>Loading...</option>");
            jQuery(`[name="${elemSelectName}"]`).trigger('change');
            jQuery(`[name="${elemSelectName}"]`).select2('close');
            jQuery(`[name="${elemSelectName}"]`).select2('open');
            thisElem.val(searchInputVal);
            jQuery.ajax({
                type: "post",
                url: ajaxRouteGenerator(),
                data: {
                    action: "kiriminaja_subdistrict_search",  // the action to fire in the server
                    data: {
                        search:searchInputVal
                    },
                },
                complete: function (response) {
                    const options = JSON.parse(response.responseText).data
                    jQuery(`[name="${elemSelectName}"]`).empty()
                    options.forEach(function (arr){
                        jQuery(`[name="${elemSelectName}"]`).append("<option value='"+arr.id+"'>"+arr.text+"</option>");
                    })
                    jQuery(`[name="${elemSelectName}"]`).trigger('change');
                    jQuery(`[name="${elemSelectName}"]`).select2('close');
                    jQuery(`[name="${elemSelectName}"]`).select2('open');
                    thisElem.val(searchInputVal);
                },
            });
        },1000)
    })
    /*** Submit DATA*/
    jQuery('body').on('click', '.menu_2 .kj-submit-btn', function(e) {
        menuFormLoaderInit('menu_2', true)

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_store_origin_data",  // the action to fire in the server
                data: {
                    origin_name:jQuery('.menu_2 [name="origin_name"]').val(),
                    origin_phone:jQuery('.menu_2 [name="origin_phone"]').val(),
                    origin_address:jQuery('.menu_2 [name="origin_address"]').val(),
                    origin_sub_district_id:jQuery('.menu_2 [name="origin_sub_district_id"] option:selected').val(),
                    origin_sub_district_name:jQuery('.menu_2 [name="origin_sub_district_id"] option:selected').text(),
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                
                menuFormLoaderInit('menu_2',false)
                if (resp?.status === 200){
                    formAlertToggler('menu_2',true,'Success',resp.message,'success')
                    fetchStoreOriginData()
                    return
                }
                formAlertToggler('menu_2',true,'Error',resp.message,'')
                

            },
        });
    })
    
</script>
<!--Callback Setup-->
<script type="text/javascript">
    /** Callback AJAX*/
    /*** Fetch DATA*/
    function fetchCallbackData(){
        menuContentLoaderInit('menu_3',true)
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_call_back_data",  // the action to fire in the server
                data: {},         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText)
                if (resp.data.status!==200) {
                    menuContentLoaderInit('menu_3',false)
                    menuContentErr('menu_3',true)
                    return
                }
                const responseData = resp.data.data;
                if (responseData?.link_callback){
                    jQuery('.menu_3 [name="link_callback"]').val(responseData?.link_callback)
                }
                menuContentLoaderInit('menu_3',false)
            },
        });
    }

    /*** Submit DATA*/
    jQuery('body').on('click', '.menu_3 .kj-submit-btn', function(e) {
        menuFormLoaderInit('menu_3', true)

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_store_call_back_data",  // the action to fire in the server
                data: {
                    link_callback:jQuery('.menu_3 [name="link_callback"]').val(),
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;

                menuFormLoaderInit('menu_3',false)
                if (resp?.status === 200){
                    formAlertToggler('menu_3',true,'Success',resp.message,'success')
                    fetchCallbackData()
                    return
                }
                formAlertToggler('menu_3',true,'Error',resp.message,'')

            },
        });
    })
</script>