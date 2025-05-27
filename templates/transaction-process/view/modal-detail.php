<!--Modal-->
<div id="transaction-detail-modal" class="kj-hidden">
    <div class="modal-container">
        <div style="background-color: #f0f0f1" tabindex="0" class="media-modal" role="dialog">
            <div class="media-modal-container">
                <div class="closebtn-container" onclick="document.getElementById('transaction-detail-modal').classList.add('kj-hidden');">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.0659 8.99481C16.3588 8.70191 16.3588 8.22704 16.0659 7.93415C15.773 7.64125 15.2981 7.64125 15.0052 7.93415L12 10.9393L8.99482 7.93415C8.70192 7.64125 8.22705 7.64125 7.93416 7.93415C7.64126 8.22704 7.64126 8.70191 7.93416 8.99481L10.9394 12L7.93415 15.0052C7.64125 15.2981 7.64125 15.773 7.93415 16.0659C8.22704 16.3588 8.70191 16.3588 8.99481 16.0659L12 13.0607L15.0052 16.0659C15.2981 16.3588 15.773 16.3588 16.0659 16.0659C16.3588 15.773 16.3588 15.2981 16.0659 15.0052L13.0607 12L16.0659 8.99481Z" fill="black"/>
                    </svg>
                </div>
                <div class="content-header" style="background-color: white;position: relative" >
                    <h1>Order #<span class="wc-order-id"></span></h1>
                    <div style="position: absolute;top: 16px;right: 36px;" class="status-container"><span class="kj-badge processing"><?php esc_html_e('New','plugin-wp');?></span></div>
                    
                </div>
                <div class="content-body" style="padding: 20px 0 20px 0">

                    <div class="kj-modal-loader" style="padding: 0 15px 0 15px;">
                        <div style="width: 100%;height: 10rem;position: relative; display: flex">
                            <div class="kj-loader" style="margin: auto"></div>
                        </div>
                    </div>

                    <div class="kj-modal-content kj-hidden">
                        <div>
                            <div style="padding: 0 15px 0 15px;">
                                <!--1 row-->
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div class="row gx-2">
                                    <div class="col">
                                        <div style="font-weight: 700">Billing Details</div>
                                        <div class="row-divider" style="margin-top: .25rem"></div>
                                        <div>Bima Daniel, Jalan jalan ke Yogyakarta, Rumah Ungu, Kec. Banguntapan, Kabupaten Bantul, DI Yogyakarta, 55198</div>
                                    </div>
                                    <div class="col">
                                        <div style="font-weight: 700">Shipping Details</div>
                                        <div class="row-divider" style="margin-top: .25rem"></div>
                                        <div>Bima Daniel, Jalan jalan ke Yogyakarta, Rumah Ungu, Kec. Banguntapan, Kabupaten Bantul, DI Yogyakarta, 55198</div>
                                    </div>
                                </div>
                                <!--2 row-->
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div class="row gx-2">
                                    <div class="col">
                                        <div>
                                            <div style="font-weight: 700">Email</div>
                                            <div class="row-divider" style="margin-top: .25rem"></div>
                                            <div>coba@kiriminaja.com</div>
                                        </div>

                                        <div class="row-divider" style="margin-top: .75rem"></div>
                                        <div>
                                            <div style="font-weight: 700">Phone</div>
                                            <div class="row-divider" style="margin-top: .25rem"></div>
                                            <div>085156722807</div>
                                        </div>

                                        <div class="row-divider" style="margin-top: .75rem"></div>
                                        <div>
                                            <div style="font-weight: 700">Payment via</div>
                                            <div class="row-divider" style="margin-top: .25rem"></div>
                                            <div>Transfer</div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div style="font-weight: 700">Shipping Method</div>
                                        <div class="row-divider" style="margin-top: .25rem"></div>
                                        <div>ID Express Standard</div>
                                    </div>
                                </div>
                            </div>
                            <!--3 row-->
                            <div class="row-divider"></div>
                            <div>
                                <table id="cart-table">
                                    <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td>Product</td>
                                        <td>Quantity</td>
                                        <td>Total</td>
                                    </tr>
                                    </tbody>
                                    <tfoot>
                                    <tr>
                                        <th colspan="2">Sub Total</th>
                                        <th>Rp.10.000</th>
                                    </tr>
                                    <tr>
                                        <th colspan="2">Shipping Fee</th>
                                        <th>Rp.10.000</th>
                                    </tr>
                                    <tr>
                                        <th colspan="2">COD Fee</th>
                                        <th>Rp.10.000</th>
                                    </tr>
                                    <tr>
                                        <th colspan="2">Insurance Fee</th>
                                        <th>Rp.10.000</th>
                                    </tr>
                                    <tr>
                                        <th colspan="2">Total</th>
                                        <th>Rp.10.000</th>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="kj-err-container" style="padding: 2.5rem; text-align: center">
                        <p style="margin-bottom: 1.5rem"><?php esc_html_e('Terjadi Kesalahan !','plugin-wp'); ?></p>
                        <button style="background-color: #009b1e; border: 1px solid #009b1e" class="button-primary woocommerce-save-button" type="button" onclick="showTransactionSummaryModalRefresh()"><?php esc_html_e('Refresh','plugin-wp'); ?></button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="media-modal-backdrop"></div>
</div>