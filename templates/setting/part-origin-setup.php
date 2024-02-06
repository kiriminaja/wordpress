<h2>Atur Lokasi</h2>
<div id="store_address-description"><p>This is where your business is located. Tax rates and shipping rates will use this address.</p></div>
<div class="kj-form">
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    Nama Toko / Pengirim
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_name" type="text" class="input-text regular-input" value="" >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    No. Hp
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_phone" type="text" class="input-text regular-input" value="" >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    Alamat
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_address" type="text" class="input-text regular-input" value="" >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    Area Pengirim
                </label>
            </th>
            <td class="forminp forminp-text">
                <select name="origin_sub_district_id" class="select-2"></select>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="kj-alert kj-hidden" style="margin-top:1rem">
        <span class="closebtn" onclick="this.parentElement.classList.add('kj-hidden');">&times;</span>
        <strong class="title">Danger!</strong>
        <br>
        <span class="sub-title">Indicates a dangerous or potentially negative action.</span>
    </div>
    
    <div class="submit">
        <div class="kj-btn-container">
            <button name="save" class="button-primary woocommerce-save-button kj-submit-btn" type="button" value="Save changes">Simpan</button>
        </div>
        <div class="kj-btn-loader-container kj-hidden">
            <div class="kj-btn-loader" style="margin-top: auto; margin-bottom: auto; margin-left: .5rem"></div>
        </div>
    </div>
</div>
<div class="kj-form-loader">
    <div style="width: 100%;height: 10rem;position: relative; display: flex">
        <div class="kj-loader" style="margin: auto"></div>
    </div>
</div>
<div class="kj-form-err kj-hidden" style="position: relative" onclick="fetchStoreOriginData()">
    <div class="kj-alert" style="margin-top:1rem">
        <strong class="title">Terjadi Kesalahan</strong>
        <br>
        <span class="sub-title">Click To Refresh</span>
    </div>
</div>