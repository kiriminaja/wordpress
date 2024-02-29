
function ajaxRouteGenerator(){
    let url = `${window.location.origin}/wp-admin/admin-ajax.php`;
    if (url.includes('localhost') && !url.includes('localhost:')){
        url = `${window.location.origin}${location.pathname}`
        let urlSplit = url.split("/wp-admin/");
        url = urlSplit[0]
        url += '/wp-admin/admin-ajax.php'
    }
    return url
}


jQuery(document).ready(function() {

    // jQuery('.select-2').select2();
    /** Hide  first menu of KJ MENU
     * Because fist menu has te same data as the parent*/
    jQuery('#toplevel_page_kiriminaja .wp-first-item').addClass('kj-hidden');

    jQuery( document ).ready(function() {
        console.log('sasasass')
        console.log(location.hostname)
        console.log(ajaxRouteGenerator())
        console.log('ssssssssssssssss')
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "my_action",  // the action to fire in the server
                data: {
                    value:'test'
                },         // any JS object
            },
            complete: function (response) {
                console.log(JSON.parse(response.responseText).data);
            },
        });
    });
    
    /** add wc class to body*/
    jQuery('body').addClass('woocommerce-admin-page')
});

function kjMoneyFormat(angka, prefix){
    var number_string = angka;
    number_string=number_string.toString();
    var split   		= number_string.split(',');
    var sisa     		= split[0].length % 3;
    var rupiah     		= split[0].substr(0, sisa);
    var ribuan     		= split[0].substr(sisa).match(/\d{3}/gi);

    // tambahkan titik jika yang di input sudah menjadi angka ribuan
    if(ribuan){
        separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
}

function printAsString (value, placeholder=''){
    if (value==null) return placeholder
    return value
}

jQuery(document).on("input", ".kj_int_input", function() {
    this.value = this.value.replace(/\D/g,'');
    if (jQuery(this).hasClass('duplicate_into')){
        var duplicateTarget=jQuery('input[name="'+$(this).data('duplicate_into')+'"]')
        duplicateTarget.val(this.value)
        duplicateTarget.trigger('change')
    }
    if (jQuery(this).hasClass('currency')){
        this.value=formatRupiah(this.value)
    }
});