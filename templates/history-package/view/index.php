<div class="wrap">
    <h1><?php _e('Histori Paket','kiriminaja'); ?></h1>

    <!-- Tab Status Transaksi -->
    <div class="tabgroup-filter">
        <?php include_once ( KJ_DIR . 'templates/history-package/view/tabfilter.php' ); ?>
    </div>

    <!-- Advanced Filter -->
    <div class="advanced-filter">
        <?php include_once ( KJ_DIR . 'templates/history-package/view/advancedfilter.php' ); ?>
    </div>
    
    <!-- List data Histori -->
    <table class="table display" id="tbhistory" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>No</th>
                <th>Transaksi</th>
                <th>Alamat</th>
                <th>Expedisi & Ongkir</th>
                <th>Isi Paket</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div>