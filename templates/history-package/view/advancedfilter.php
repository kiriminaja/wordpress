<div class="row-filter">
    <form action="" id="frm-advancedfilter">
        <div class="row-grid-filter">
            <div class="row-item-filter">
                <div class="wpform-group">
                    <label><?php _e('Cari','kiriminaja'); ?></label>
                    <div class="input-group">
                        <select class="dropdown-select number-order" name="prefix">
                            <option value="oid">OID</option>
                            <option value="awb">AWB</option>
                            <option value="hp">No HP</option>
                        </select>
                        <input type="text" autocomplete="off" name="stext" class="input-field" placeholder="Enter text here" />
                    </div>
                </div>
                <div class="wpform-group">
                    <label><?php _e('Alamat Pickup','kiriminaja'); ?></label>
                    <select class="dropdown-select ka-select2" id="address-select2">
                        <option>Pilih Alamat</option>
                    </select>
                </div>
                <div class="wpform-group">
                    <label><?php _e('Ekpedisi','kiriminaja'); ?></label>
                    <select class="dropdown-select ka-select2" id="ekpedisi-select2">
                        <option>Pilih Ekspedisi</option>
                    </select>
                </div>
                <div class="wpform-group">
                    <label><?php _e('Tipe Pembayaran','kiriminaja'); ?></label>
                    <select class="dropdown-select ka-select2" id="payment-select2">
                        <option value="cod">COD</option>
                        <option value="noncod">NO COD</option>
                    </select>
                </div>
                <div class="wpform-group">
                    <button class="button button-primary" type="submit" id="btn-advancedfilter"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </div>
        </div>
    </form>
</div>