<p class="form-row form-row-wide">
    <label for="custom_select_field"><?php esc_html_e('Kelurahan', 'kiriminaja'); ?> <span class="required">*</span></label>
    <select name="custom_select_field_shipping" id="custom_select_field_shipping" class="select2 custom_select_field_shipping" style="width: 100%;" required></select>
</p>
<script type="text/javascript">
    let subdistrictAjaxTimeoutShipping = null
    const elemSelectNameShipping = 'custom_select_field_shipping';


    jQuery(document).ready(function($) {
        $('#custom_select_field_shipping').select2({
            tags: true,
            placeholder:"Masukkan Kelurahan",
        }).on('select2:open', function(e) {
            $('.select2-search__field').prop('id', 'shipping_search');
        });

    });

    jQuery('body').on('keyup', `#shipping_search`, function(e) {
        const thisElem = jQuery(this);

        const searchInputVal = jQuery(this).val()
        if (subdistrictAjaxTimeoutShipping) {
            clearTimeout(subdistrictAjaxTimeoutShipping)
        }
        subdistrictAjaxTimeoutShipping = setTimeout(function() {
            jQuery(`[name="${elemSelectNameShipping}"]`).empty()
            jQuery(`[name="${elemSelectNameShipping}"]`).append("<option value='' disabled>Loading...</option>");
            jQuery(`[name="${elemSelectNameShipping}"]`).trigger('change');
            jQuery(`[name="${elemSelectNameShipping}"]`).select2('close');
            jQuery(`[name="${elemSelectNameShipping}"]`).select2('open');
            thisElem.val(searchInputVal);


            wp.ajax.post( "kiriminaja_subdistrict_search", {
                data: {
                    search: searchInputVal
                }
            })
                .done(function(response) {
                    const options = response
                    jQuery(`[name="${elemSelectNameShipping}"]`).empty()
                    options.forEach(function(arr) {
                        jQuery(`[name="${elemSelectNameShipping}"]`).append("<option value='" + arr.id + "'>" + arr.text + "</option>");
                    })
                    jQuery(`[name="${elemSelectNameShipping}"]`).trigger('change');
                    jQuery(`[name="${elemSelectNameShipping}"]`).select2('close');
                    jQuery(`[name="${elemSelectNameShipping}"]`).select2('open');
                    thisElem.val(searchInputVal);
                });
        }, 1000)

    })



</script>