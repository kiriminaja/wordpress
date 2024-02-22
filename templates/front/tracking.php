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
    @media only screen and (min-width: 900px) {
        #tracking-result{
            width: 68%;
        }
        #tracking-form{
            width: 28%;
        }
        form.checkout{
            display: flex !important;
        }
    }
    
    .tracking-table{
        width: 100%;
    }
    .tracking-table th{
        /*text-align: left;*/
        font-size: 18px;
        text-align: center;
    }
    .tracking-table td{
        font-size: 14px;
        text-align: center;
    }
    .tracking-table td,.tracking-table th{
        padding: 10px 0;
    }
    
</style>
<div style="min-height: 40vh" class="woocommerce woocommerce-page">
    <form style="width: 100%" name="checkout" method="post" class="checkout woocommerce-checkout" action="http://localhost/works/wp_kj_test_v2/wordpress/checkout/" enctype="multipart/form-data" novalidate="novalidate">
        <div class="col2-set" id="tracking-form">
            <h3 id="order_review_heading">Pesanan Anda</h3>
            <div id="order_review" class="woocommerce-checkout-review-order">


                <p class="form-row form-row-wide" id="billing_company_field" data-priority="30">
                    <label for="billing_company" class="">Order Number</label>
                    <span class="woocommerce-input-wrapper">
                        <input type="text" class="input-text " name="order_number" placeholder="" value="" autocomplete="organization">
                    </span>
                </p>

                <button style="width: 100%" type="button" onclick="trackOrder()" class="button track-btn alt wp-element-button track-btn">Track Pesanan</button>
            </div>
        </div>
        <div class="col2-set" id="tracking-result">
            <div style="margin-top: 2rem"></div>
            <div class="state-blank">
                <div style="text-align: center">
                    <span style="font-weight: 700">Untuk mendapatkan informasi pesanan anda<br>Klik Track Pesanan</span>
                </div>
            </div>
            <div class="state-err kj-hidden">
                <div style="text-align: center; margin-top: 4rem">
                    <span style="font-weight: 700" id="err_msg">Order tidak ditemukan</span>
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
                <table class="tracking-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
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
        })
            .done(function(response) {
                hideStateComponent()
                jQuery('.track-btn').removeClass('kj-hidden')

                if (response.status === 200){
                    jQuery('.state-success').removeClass('kj-hidden')

                    const trackingHistories = response?.data?.histories ?? []
                    jQuery('.tracking-table tbody').empty()
                    jQuery.each(trackingHistories,function (index,trackData){
                        jQuery('.tracking-table tbody').append(
                            `<tr>
                                <td>${trackData.created_at}</td>
                                <td>${trackData.status}</td>
                            </tr>`)
                    })
                    return
                }

                jQuery('.state-err').removeClass('kj-hidden')
                jQuery('#err_msg').text(response?.message)
      
                
            });
    }
    
    
    
    function hideStateComponent(){
        jQuery('.track-btn').addClass('kj-hidden')
        jQuery('.state-blank').addClass('kj-hidden')
        jQuery('.state-err').addClass('kj-hidden')
        jQuery('.state-loading').addClass('kj-hidden')
        jQuery('.state-success').addClass('kj-hidden')
    }
</script>
