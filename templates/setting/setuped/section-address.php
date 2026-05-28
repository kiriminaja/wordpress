<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Store Address (detail page)
 *
 * @var string $locale
 * @var array $inputValueArr
 * @var string $kiriof_base_url
 */
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <h1 class="wp-heading-inline" style="font-size:14px;font-weight:400;display:flex;align-items:center;gap:6px;">
        <a href="<?php echo esc_url( $kiriof_base_url ); ?>" style="color:#2271b1;text-decoration:none;"><?php echo esc_html( kiriof_helper()->tlThis('Settings',$locale) ); ?></a>
        <span style="color:#8c8f94;">›</span>
        <span style="font-weight:500;"><?php echo esc_html( kiriof_helper()->tlThis('Manage Locations',$locale) ); ?></span>
    </h1>
    <hr class="wp-header-end">

    <div class="kj-detail">

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div class="kj-form">
                <table class="form-table">
                    <tbody>
                    <tr><th><label><?php echo esc_html( kiriof_helper()->tlThis('Sender Name',$locale) ); ?></label></th><td><input style="width:100%;max-width:25rem" name="origin_name" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_name'] ?? '');?>"></td></tr>
                    <tr><th><label><?php echo esc_html(kiriof_helper()->tlThis('Sender Phone',$locale)); ?></label></th><td><input style="width:100%;max-width:25rem" name="origin_phone" type="text" class="input-text regular-input kiriof_int_input" value="<?php echo esc_attr($inputValueArr['origin_phone'] ?? '');?>"></td></tr>
                    <tr><th><label><?php echo esc_html(kiriof_helper()->tlThis('Address',$locale)); ?></label></th><td><input style="width:100%;max-width:25rem" name="origin_address" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_address'] ?? '');?>"></td></tr>
                    <input type="hidden" name="origin_latitude" value="<?php echo esc_attr($inputValueArr['origin_latitude'] ?? '');?>">
                    <input type="hidden" name="origin_longitude" value="<?php echo esc_attr($inputValueArr['origin_longitude'] ?? '');?>">
                    <tr><th><label><?php echo esc_html(kiriof_helper()->tlThis('Pin Location',$locale)); ?></label></th>
                    <td>
                        <div style="position:relative;width:100%;max-width:25rem">
                            <div id="kiriof-origin-map" style="width:100%;height:280px;border:1px solid #ddd;border-radius:4px;z-index:0"></div>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);z-index:401;pointer-events:none">
                                <svg width="30" height="40" viewBox="0 0 30 40"><path d="M15 0C6.716 0 0 6.716 0 15c0 10.969 13.5 24.138 14.094 24.72a1.25 1.25 0 0 0 1.812 0C16.5 39.138 30 25.969 30 15 30 6.716 23.284 0 15 0zm0 22.5a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" fill="#E74C3C"/><circle cx="15" cy="15" r="4" fill="white"/></svg>
                            </div>
                            <button type="button" id="kiriof-use-my-location" style="position:absolute;top:8px;right:8px;z-index:401;background:#fff;border:1px solid #8c8f94;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:12px;font-weight:600;color:#1d2327;box-shadow:0 1px 4px rgba(0,0,0,.2)"><?php echo esc_html(kiriof_helper()->tlThis('My Location',$locale)); ?></button>
                        </div>
                        <p class="description" style="margin-top:4px"><span id="kiriof-map-coords" style="font-family:monospace"></span></p>
                        <p class="description" id="kiriof-map-error" style="margin-top:4px;color:#d63638;display:none"></p>
                    </td></tr>
                    <tr><th><label><?php echo esc_html( kiriof_helper()->tlThis('Zipcode',$locale) ); ?></label></th><td><input style="width:100%;max-width:25rem" name="origin_zip_code" type="text" class="input-text regular-input kiriof_int_input" value="<?php echo esc_attr($inputValueArr['origin_zip_code'] ?? '');?>"></td></tr>
                    <tr><th><label><?php echo esc_html( kiriof_helper()->tlThis('Area',$locale) ); ?></label></th><td><select name="origin_sub_district_id" class="select-2"><?php if ( ! empty( $inputValueArr['origin_sub_district_id'] ) && ! empty( $inputValueArr['origin_sub_district_name'] ) ) echo '<option selected value="'.esc_attr($inputValueArr['origin_sub_district_id']).'">'.esc_html($inputValueArr['origin_sub_district_name']).'</option>'; ?></select></td></tr>
                    </tbody>
                </table>
                <p class="submit" style="margin-top:1rem">
                    <button class="button button-primary kj-submit-btn" type="button"><?php echo esc_html(kiriof_helper()->tlThis('Save Changes',$locale)); ?></button>
                </p>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
    jQuery(document).ready(function($){jQuery('[name="origin_sub_district_id"]').select2({width:'100%',minimumInputLength:3,placeholder:"<?php echo esc_js(kiriof_helper()->tlThis('Select Option',$locale)); ?>",allowClear:true,ajax:{url:kiriofAjaxRoute(),dataType:'json',type:"POST",delay:250,data:function(s){return{data:s,nonce:kiriofAjax.nonce,action:'kiriminaja_subdistrict_search'}},processResults:function(r){return{results:jQuery.map(r.data,function(i){return{text:i.text,id:i.id}})}},cache:true}});});
    jQuery('body').on('click','.kj-detail .kj-submit-btn',function(e){var $b=jQuery(this);$b.prop('disabled',true);jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_store_origin_data',data:{nonce:kiriofAjax.nonce,origin_name:jQuery('[name="origin_name"]').val(),origin_phone:jQuery('[name="origin_phone"]').val(),origin_address:jQuery('[name="origin_address"]').val(),origin_latitude:jQuery('[name="origin_latitude"]').val(),origin_longitude:jQuery('[name="origin_longitude"]').val(),origin_zip_code:jQuery('[name="origin_zip_code"]').val(),origin_sub_district_id:jQuery('[name="origin_sub_district_id"] option:selected').val(),origin_sub_district_name:jQuery('[name="origin_sub_district_id"] option:selected').text()}},error:function(){$b.prop('disabled',false);alert('Network error')},complete:function(r){var p=kiriofParseAjaxResponse(r);if(p&&p.status===200){window.location.reload();return}$b.prop('disabled',false);alert((p&&p.message)?p.message:'Save failed')}});});
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>

<!-- Map -->
<?php ob_start(); ?>
    jQuery(function($){if(typeof L==='undefined'||!document.getElementById('kiriof-origin-map'))return;var init=false;function initMap(){if(init)return;var $c=$('#kiriof-origin-map');if(!$c.is(':visible')||$c.width()===0)return;init=true;var $lat=$('[name="origin_latitude"]'),$lng=$('[name="origin_longitude"]'),$coords=$('#kiriof-map-coords'),$err=$('#kiriof-map-error'),dl=parseFloat($lat.val())||-6.2088,dlng=parseFloat($lng.val())||106.8456,m=L.map('kiriof-origin-map').setView([dl,dlng],15);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(m);setTimeout(function(){m.invalidateSize()},200);function se(m){$err.text(m).show();setTimeout(function(){$err.fadeOut()},5000)}function ui(lat,lng){if(isNaN(lat)||isNaN(lng)||lat<-90||lat>90||lng<-180||lng>180){se('Invalid coordinates');return}$lat.val(lat.toFixed(7));$lng.val(lng.toFixed(7));$coords.text(lat.toFixed(7)+', '+lng.toFixed(7));$err.hide()}m.on('moveend',function(){var c=m.getCenter();ui(c.lat,c.lng)});ui(dl,dlng);$('#kiriof-use-my-location').on('click',function(){var $b=$(this);$err.hide();if(!navigator.geolocation){se('Geolocation not supported');return}$b.prop('disabled',true).css('opacity','0.6');navigator.geolocation.getCurrentPosition(function(p){m.setView([p.coords.latitude,p.coords.longitude],17);$b.prop('disabled',false).css('opacity','1')},function(e){$b.prop('disabled',false).css('opacity','1');se(['Permission denied','Location unavailable','Timeout'][e.code-1]||'Unknown error')},{enableHighAccuracy:true,timeout:10000,maximumAge:0})})}setTimeout(initMap,500)});
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
