<div id="request-pickup-modal" class="kj-hidden">
    <div tabindex="0" class="media-modal" role="dialog">
        <div class="media-modal-container">
            <div class="closebtn-container" onclick="document.getElementById('request-pickup-modal').classList.add('kj-hidden');">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16.0659 8.99481C16.3588 8.70191 16.3588 8.22704 16.0659 7.93415C15.773 7.64125 15.2981 7.64125 15.0052 7.93415L12 10.9393L8.99482 7.93415C8.70192 7.64125 8.22705 7.64125 7.93416 7.93415C7.64126 8.22704 7.64126 8.70191 7.93416 8.99481L10.9394 12L7.93415 15.0052C7.64125 15.2981 7.64125 15.773 7.93415 16.0659C8.22704 16.3588 8.70191 16.3588 8.99481 16.0659L12 13.0607L15.0052 16.0659C15.2981 16.3588 15.773 16.3588 16.0659 16.0659C16.3588 15.773 16.3588 15.2981 16.0659 15.0052L13.0607 12L16.0659 8.99481Z" fill="black"/>
                </svg>
            </div>
            <div class="content-header" >
                <h1>Request Pickup</h1>
            </div>
            <div class="content-body">

                <div class="kj-modal-loader">
                    <div style="width: 100%;height: 10rem;position: relative; display: flex">
                        <div class="kj-loader" style="margin: auto"></div>
                    </div>
                </div>

                <div class="kj-modal-content kj-hidden">

                    <div style="margin-top: 15px">
                        <table class="wp-list-table widefat fixed striped table-view-list posts">
                            <tbody>
                            <tr>
                                <th colspan="2" scope="col" class="manage-column column-thumb">Ongkos Kirim</th>
                            </tr>
                            <tr>
                                <td scope="col" class="manage-column column-thumb">NON COD (<span id="detail-non_cod_count">1</span>)</td>
                                <td scope="col" class="manage-column column-thumb">
                                    <div style="float: right"><span id="detail-non_cod_sum">Rp. 20.000</span></div>
                                </td>
                            </tr>
                            <tr>
                                <td scope="col" class="manage-column column-thumb">COD (<span id="detail-cod_count">1</span>)</td>
                                <td scope="col" class="manage-column column-thumb">
                                    <div style="float: right"><span id="detail-cod_sum">Rp. 0</span></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="col" class="manage-column column-thumb">Total Bayar</th>
                                <th scope="col" class="manage-column column-thumb">
                                    <div style="float: right"><span id="detail-payment_amount">Rp. 20.000</span></div>
                                </th>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem"></div>
                    <span style="font-size: 1rem; font-weight: 600; ">Pilih Waktu Pickup</span>

                    <div style="margin-top: 1rem"></div>
                    <div>
                        <div style="overflow: auto; max-height: 30vh">
                            <?php
                            for ($i=0;$i<=10;$i++){
                                echo '
                                <div style="border: 1px solid gray; border-radius: 5px; padding: 12px;margin-bottom: .5rem">
                                    <div style="display: flex">
                                        <input style="margin: 0" value="15" type="radio" name="tax_input[product_cat][]" id="in-product_cat-15">
                                        <span style="margin-left: auto;margin-top: auto;margin-bottom: auto">2020-01-01 15:00</span>
                                    </div>
                                </div>
                        ';
                            }
                            ?>
                        </div>
                    </div>
                    <div style="margin-top: 1rem"></div>
                    <div style="text-align: center">
                        <button class="button-primary woocommerce-save-button" type="button" >Request Pickup</button>
                    </div>
                </div>

                <div class="kj-err-container" style="padding: 2.5rem; text-align: center">
                    <p style="margin-bottom: 1.5rem">Terjadi Kesalahan !</p>
                    <button style="background-color: #009b1e; border: 1px solid #009b1e" class="button-primary woocommerce-save-button" type="button" onclick="refreshShowDetail()">Refresh</button>
                </div>

            </div>
        </div>
    </div>
    <div class="media-modal-backdrop"></div>
</div>
