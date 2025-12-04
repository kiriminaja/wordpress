<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<style>
    body .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
        max-width: 1000px;
    }

    .col2-set{
        padding: 0.5rem;
    }
    #tracking-form ,#tracking-result{
        width: 100%;
    }
    form.checkout{
        display: block !important;
    }
    
    
    .tracking-table{
        width: 100%;
    }
    .tracking-table th{
        font-size: 18px;
        text-align: center;
        background-color: #f7f7f7;    
    }
    .tracking-table td{
        font-size: 14px;
        padding-left: 10px !important;
        padding-right: 10px !important;
    }
    .tracking-table td,.tracking-table th{
        padding: 10px 0;
    }

.tracking-table td:first-child {
    background: #f4f4f4;
}

.tracking-address {
    display: inline-flex;
    width: 100%; /* Set initial width to 50% */
    align-items: center;
    justify-content: space-between;
}

.track-inline {
    border-radius: 3px;
}

.track-inline p {
    margin: 0;
}

.textprimary {
    font-size: 1rem;
    line-height: 2.5rem;
    font-weight: 600;
}

.textseccond {
    font-size: 1rem;
    line-height: 1.5rem;
}

.tracking-header {
    display: inline-flex;
    justify-content: space-between; /* Mengatur jarak antara elemen */
    width: 100%;
}

.tracking-header p {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.tracking-details {
    background: #f7f7f7;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.tracking-details p {
    margin: 0;
}

.borderdashed {
    border-bottom-width: 0rem;
    border-style: dashed;
    border-color: #7c757e;
    margin: 0.5rem 0;
}

.tracking-courier {
    margin: 1rem 0;
}

.tracking-courier .textseccond {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .25rem;
}
    
</style>

<div style="min-height: 40vh" class="woocommerce woocommerce-page">
    <form style="width: 100%" name="checkout" method="post" class="checkout woocommerce-checkout"  enctype="multipart/form-data" novalidate="novalidate">
        <div class="col2-set" id="">
            <h3 ><?php esc_html_e('Pesanan Anda','kiriminaja-official'); ?></h3>
            <div class="woocommerce-checkout-review-order">


                <p class="form-row form-row-wide" id="billing_company_field" data-priority="30">
                    <label for="billing_company" class=""><?php esc_html_e('Nomor Resi','kiriminaja-official'); ?> <span style="color:red;">*</span></label>
                    <span class="woocommerce-input-wrapper">
                        <input type="text" class="input-text" name="order_number" placeholder="Masukan Nomor Resi atau Nomor Order ..." value="" autocomplete="organization">
                    </span>
                </p>

                <button style="width: 100%" type="button" onclick="trackOrder()" class="button track-btn alt wp-element-button track-btn"><?php esc_html_e('Lacak Pesanan','kiriminaja-official'); ?></button>
            </div>
        </div>
        <div class="col2-set" id="tracking-result">
            <div style="margin-top: 2rem"></div>
            <div class="state-blank">
                <div style="text-align: center">
                    <span style="font-weight: 700"><?php esc_html_e('Untuk mendapatkan informasi pesanan anda','kiriminaja-official'); ?><br><?php esc_html_e('Klik Track Pesanan','kiriminaja-official'); ?></span>
                </div>
            </div>
            <div class="state-err kj-hidden">
                <div style="text-align: center; margin-top: 4rem">
                    <span style="font-weight: 700" id="err_msg"><?php esc_html_e('Order tidak ditemukan','kiriminaja-official'); ?></span>
                </div>
            </div>
            <div class="state-loading kj-hidden">
                <div style="display: flex">
                    <div style="margin: 3rem auto">
                        <div class="kj-loader"></div>
                    </div>                    
                </div>
            </div>
            <div class="state-success kj-hidden">
                
                 <!-- Load Ajax -->
                <div class="tracking-details"></div>

                <table class="tracking-table">
                    <thead>
                        <tr>
                            <th width="20%">Tanggal</th>
                            <th>Status</th>
                        </tr>                    
                    </thead>
                    <tbody>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    function trackOrder(){

        hideStateComponent()
        jQuery('.state-loading').removeClass('kj-hidden')

        wp.ajax.post( "kj-tracking-ajax", {
            order_number:jQuery('[name="order_number"]').val()
        }).done(function(response) {  
                              
            hideStateComponent()
            jQuery('.track-btn').removeClass('kj-hidden')

            if (response.status === 200){
                    jQuery('.state-success').removeClass('kj-hidden')

                    const trackingHistories = response?.data?.histories ?? [];
                    const trackingDetails = response?.data?.details ?? [];
                    const trackingOrderNumber = response?.data?.number_order ?? [];

                    jQuery('.tracking-table tbody').empty();


                    let details = `
                        <div class="tracking-gorup">
                            <div class="tracking-header">
                               <p>Nomor Order : #${trackingOrderNumber}</p>
                               <p>Nomor Resi : ${trackingDetails?.awb ?? '-'}</p>
                            </div> 

                            <div class="tracking-address">
                                <div class="track-inline">
                                    <p class="textprimary">${trackingDetails?.destination?.name ?? '-'}</p>
                                    <p class="textseccond">${trackingDetails?.destination?.city ?? '-'}</p>
                                    <p class="textseccond textbold">${trackingDetails?.destination?.province ?? '-'}</p>
                                </div>
                            </div>
                            
                            <div class="tracking-courier">
                                <div class="borderdashed"></div>
                                
                                <div class="textseccond">
                                    <p>Kurir</p>
                                    <p class="fontbold">${trackingDetails?.service ?? '-'}</p>
                                </div>
                                
                                <div class="borderdashed"></div>
                            </div>

                        </div>
                    `;

                    jQuery('.tracking-details').html(details);
                    
                    jQuery.each(trackingHistories,function (index,trackData){   

                        jQuery('.tracking-table tbody').append(
                            `<tr>
                                <td>${trackData.created_at}</td>
                                <td>${trackData.status}</td>
                            </tr>`)
                    });                    
                return
            }

            jQuery('.state-err').removeClass('kj-hidden')
            jQuery('#err_msg').text(response?.message)
        });
    }

    const url = new URL(window.location.href);
    jQuery(document).ready(function() {
        const urlParams = url.searchParams;
        const orderIdToLoad = encodeURI(urlParams.get("order_id"));
        if (orderIdToLoad!=='null'){
            jQuery('[name="order_number"]').val(orderIdToLoad)
            trackOrder()
        }
    });
    
    
    function hideStateComponent(){
        jQuery('.track-btn').addClass('kj-hidden')
        jQuery('.state-blank').addClass('kj-hidden')
        jQuery('.state-err').addClass('kj-hidden')
        jQuery('.state-loading').addClass('kj-hidden')
        jQuery('.state-success').addClass('kj-hidden')
    }
</script>
