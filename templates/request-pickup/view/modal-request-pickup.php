<div id="request-pickup-modal" class="kj-hidden">
    <div class="modal-container">
        <div style="width: 100%; max-width: 400px;background-color: #f0f0f1" tabindex="0" class="media-modal" role="dialog">
            <div class="media-modal-container">
                <div class="closebtn-container" onclick="document.getElementById('request-pickup-modal').classList.add('kj-hidden');">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.0659 8.99481C16.3588 8.70191 16.3588 8.22704 16.0659 7.93415C15.773 7.64125 15.2981 7.64125 15.0052 7.93415L12 10.9393L8.99482 7.93415C8.70192 7.64125 8.22705 7.64125 7.93416 7.93415C7.64126 8.22704 7.64126 8.70191 7.93416 8.99481L10.9394 12L7.93415 15.0052C7.64125 15.2981 7.64125 15.773 7.93415 16.0659C8.22704 16.3588 8.70191 16.3588 8.99481 16.0659L12 13.0607L15.0052 16.0659C15.2981 16.3588 15.773 16.3588 16.0659 16.0659C16.3588 15.773 16.3588 15.2981 16.0659 15.0052L13.0607 12L16.0659 8.99481Z" fill="black"/>
                    </svg>
                </div>
                <div class="content-header" style="background-color: #ffffff" >
                    <h1>Schedule for Pickup</h1>
                </div>
                <div class="content-body">

                    <div class="kj-modal-loader">
                        <div style="width: 100%;height: 10rem;position: relative; display: flex">
                            <div class="kj-loader" style="margin: auto"></div>
                        </div>
                    </div>

                    <div class="kj-modal-content kj-hidden">

                        <div style="margin-top: .75rem"></div>
                        <div style="padding: 10px;border: 1px solid #c3c4c7;background-color: #ffffff">
                            <div class="row">
                                <div class="col">Tagihan Paket COD</div>
                                <div class="col" style="text-align: right; font-weight: 700">Rp0</div>
                            </div>
                            <div class="row-divider" style="margin-top: .5rem"></div>
                            <div class="row">
                                <div class="col">Tagihan Paket Non-COD</div>
                                <div class="col" style="text-align: right; font-weight: 700">Rp300.000</div>
                            </div>
                            <div class="row-divider" style="margin-top: .5rem"></div>
                            <div class="row">
                                <div class="col">Total Tagihan</div>
                                <div class="col" style="text-align: right; font-weight: 700">Rp300.000</div>
                            </div>
                        </div>
                        <div style="margin-top: .75rem"></div>
                        <div>
                            <div style="overflow: auto; max-height: 30vh; font-weight: 600">
                                <?php
                                for ($i=0;$i<=10;$i++){
                                    echo '
                                <div style="margin-bottom: .75rem">
                                    <div style="display: flex">
                                        <input style="margin: 0" value="15" type="radio" name="tax_input[product_cat][]" id="in-product_cat-15">
                                        <span style="margin-left: .5rem;margin-top: auto;margin-bottom: auto">Tuesday, 2024-02-06 08:00</span>
                                    </div>
                                </div>
                        ';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div>
                            <button onclick="kjRequestPickupProcess()" class="button-wp btn-lg" type="button">
                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                    <span style="margin: auto">Pick Schedule</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="kj-err-container" style="padding: 2.5rem; text-align: center">
                        <p style="margin-bottom: 1.5rem">Terjadi Kesalahan !</p>
                        <button style="background-color: #009b1e; border: 1px solid #009b1e" class="button-primary woocommerce-save-button" type="button" onclick="refreshShowDetail()">Refresh</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="media-modal-backdrop"></div>
</div>
