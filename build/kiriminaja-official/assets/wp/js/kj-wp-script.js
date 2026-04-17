// Use AJAX URL passed from WordPress via wp_localize_script
function ajaxRouteGenerator(){
    return kirioAjax.ajaxurl;
}

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