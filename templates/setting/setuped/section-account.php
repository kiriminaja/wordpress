<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Account Configuration (detail page)
 *
 * @var string $locale
 * @var string $kiriof_base_url
 * @var object|null $approvedSetupKey
 */

// Check integration status
$kiriof_setup_key_row = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('setup_key');
$kiriof_is_connected  = ! empty( $kiriof_setup_key_row->value ?? null );

// Fetch profile if connected
$kiriof_profile     = null;
$kiriof_profile_err = false;
if ( $kiriof_is_connected ) {
    try {
        $kiriof_profile_svc = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->getProfile();
        if ( 200 === $kiriof_profile_svc->status && ! empty( $kiriof_profile_svc->data ) ) {
            $kiriof_profile = $kiriof_profile_svc->data;
        } else {
            $kiriof_profile_err = true;
        }
    } catch ( \Throwable $th ) {
        $kiriof_profile_err = true;
    }
}

// Fetch enabled couriers for display
$kiriof_wl = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['origin_whitelist_expedition_id','origin_whitelist_expedition_name']);
$kiriof_wl_ids   = '';
$kiriof_wl_names  = '';
foreach ( $kiriof_wl as $kiriof_wl_row ) {
    if ( 'origin_whitelist_expedition_id' === $kiriof_wl_row->key ) {
        $kiriof_wl_ids = $kiriof_wl_row->value;
    }
    if ( 'origin_whitelist_expedition_name' === $kiriof_wl_row->key ) {
        $kiriof_wl_names = $kiriof_wl_row->value;
    }
}
$kiriof_wl_id_arr   = $kiriof_wl_ids ? array_map( 'trim', explode( ',', $kiriof_wl_ids ) ) : array();
$kiriof_wl_name_arr  = $kiriof_wl_names ? array_map( 'trim', explode( ',', $kiriof_wl_names ) ) : array();
$kiriof_wl_map       = array_combine( $kiriof_wl_id_arr, array_pad( $kiriof_wl_name_arr, count( $kiriof_wl_id_arr ), '' ) );

// Fallback: try fetching all couriers from API to get names for IDs
if ( ! empty( $kiriof_wl_id_arr ) ) {
    try {
        $kiriof_couriers_svc = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->get_couriers();
        if ( 200 === $kiriof_couriers_svc->status && ! empty( $kiriof_couriers_svc->data ) ) {
            foreach ( $kiriof_couriers_svc->data as $kiriof_courier ) {
                if ( in_array( $kiriof_courier->code, $kiriof_wl_id_arr, true ) && empty( $kiriof_wl_map[ $kiriof_courier->code ] ) ) {
                    $kiriof_wl_map[ $kiriof_courier->code ] = $kiriof_courier->name;
                }
            }
        }
    } catch ( \Throwable $th ) {
        // Non-critical — use whatever names we have
    }
}

// Brand colors for courier chips (fallback gradient based on code hash)
$kiriof_courier_colors = array(
    'jne' => '#25387B', 'tiki' => '#0632AD', 'sicepat' => '#D5232B',
    'jnt' => '#ED3237', 'anteraja' => '#EC1A77', 'pos' => '#182C61',
    'rpx' => '#1A1A1A', 'lion' => '#FF0000', 'paxel' => '#5F51A1',
    'sap' => '#862880', 'ninja' => '#E41E26', 'idexpress' => '#FE1600',
    'ncs' => '#0D1B6F', 'borzo' => '#0048FF', 'grab' => '#00AB4E',
    'gosend' => '#000000', 'shopee' => '#EE4D2D', 'sentral' => '#A71E22',
);
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <h1 class="wp-heading-inline" style="display:flex;align-items:center;gap:6px;">
        <a href="<?php echo esc_url( $kiriof_base_url ); ?>" style="color:#2271b1;text-decoration:none;"><?php echo esc_html( kiriof_helper()->tlThis('Settings',$locale) ); ?></a>
        <span style="color:#8c8f94;">›</span>
        <span style="font-weight:500;"><?php echo esc_html( kiriof_helper()->tlThis('Account Configuration',$locale) ); ?></span>
    </h1>
    <hr class="wp-header-end">

    <div class="kj-detail" style="max-width:720px;">

        <!-- Enabled Couriers -->
        <?php if ( ! empty( $kiriof_wl_id_arr ) ) : ?>
        <div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:16px;"><?php echo esc_html( kiriof_helper()->tlThis('Enabled Couriers',$locale) ); ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ( $kiriof_wl_id_arr as $kiriof_cid ) :
                    $kiriof_cname  = $kiriof_wl_map[ $kiriof_cid ] ?? strtoupper( $kiriof_cid );
                    $kiriof_hash   = crc32( $kiriof_cid );
                    $kiriof_bg     = $kiriof_courier_colors[ strtolower( $kiriof_cid ) ] ?? sprintf( '#%02x%02x%02x', ($kiriof_hash & 0xFF0000) >> 16, ($kiriof_hash & 0x00FF00) >> 8, $kiriof_hash & 0x0000FF );
                    $kiriof_r = hexdec( substr( $kiriof_bg, 1, 2 ) );
                    $kiriof_g = hexdec( substr( $kiriof_bg, 3, 2 ) );
                    $kiriof_b = hexdec( substr( $kiriof_bg, 5, 2 ) );
                    $kiriof_fg = (($kiriof_r*0.299 + $kiriof_g*0.587 + $kiriof_b*0.114) > 150) ? '#1d2327' : '#fff';
                ?>
                <div style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:<?php echo esc_attr($kiriof_bg); ?>;color:<?php echo esc_attr($kiriof_fg); ?>;border-radius:8px;font-size:12px;font-weight:500;white-space:nowrap;">
                    <span style="opacity:0.9"><?php echo esc_html( mb_strtoupper( mb_substr( $kiriof_cid, 0, 3 ) ) ); ?></span>
                    <span style="font-size:11px;opacity:0.7;"><?php echo esc_html( $kiriof_cname ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Connection -->
        <div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:16px;"><?php echo esc_html( kiriof_helper()->tlThis('Connection',$locale) ); ?></div>

            <?php if ( $kiriof_is_connected && $kiriof_profile ) : ?>
                <!-- Connected state: profile card -->
                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:44px;height:44px;border-radius:50%;background:#7d3eb9;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:600;flex-shrink:0;">
                            <?php echo esc_html( mb_substr( $kiriof_profile->name ?? '?', 0, 1 ) ); ?>
                        </div>
                        <div>
                            <div style="font-weight:600;color:#1d2327;">
                                <?php echo esc_html( $kiriof_profile->name ?? '—' ); ?>
                                <?php if ( ! empty( $kiriof_profile->metadata->payment_method ) ) : ?>
                                <span style="display:inline-block;margin-left:8px;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:600;vertical-align:middle;<?php echo 'TOP' === $kiriof_profile->metadata->payment_method ? 'background:#edfaef;color:#007017;border:1px solid #b7e5be;' : 'background:#f0f6fc;color:#135e96;border:1px solid #bcd8f0;'; ?>">
                                    <?php echo esc_html( $kiriof_profile->metadata->payment_method ); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:13px;color:#646970;"><?php echo esc_html( $kiriof_profile->email ?? '—' ); ?></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if ( ! empty( $kiriof_profile->status ) ) : ?>
                        <span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase;<?php echo 'active' === $kiriof_profile->status ? 'background:#edfaef;color:#007017;border:1px solid #b7e5be;' : 'background:#fcf0f1;color:#8a2424;border:1px solid #f4cccc;'; ?>">
                            <?php echo esc_html( $kiriof_profile->status ); ?>
                        </span>
                        <?php endif; ?>
                        <button type="button" class="button kj-disconnect" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html( kiriof_helper()->tlThis('Disconnect',$locale) ); ?></button>
                    </div>
                </div>
            <?php elseif ( $kiriof_is_connected && $kiriof_profile_err ) : ?>
                <div style="color:#d63638;"><?php echo esc_html( kiriof_helper()->tlThis('Unable to load account information. Your integration may be incomplete.',$locale) ); ?></div>
                <div style="margin-top:12px;text-align:right;">
                    <button type="button" class="button kj-disconnect" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html( kiriof_helper()->tlThis('Disconnect',$locale) ); ?></button>
                </div>
            <?php else : ?>
                <!-- Not connected: setup key form -->
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <!-- Left: setup key input -->
                    <div style="flex:1;min-width:280px;">
                        <div style="margin-bottom:8px;">
                            <label style="font-size:13px;font-weight:500;color:#1d2327;"><?php echo esc_html( kiriof_helper()->tlThis('Setup Key',$locale) ); ?> <span style="color:#d63638;">*</span></label>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input id="kiriof-setup-key-input" type="text" class="input-text regular-input" style="flex:1;max-width:340px;padding:8px 12px;border-radius:8px;border-color:#8c8f94;" placeholder="<?php echo esc_attr( kiriof_helper()->tlThis('Input your setup key for KiriminAja',$locale) ); ?>">
                            <button id="kiriof-setup-key-connect" type="button" class="button" style="background:#7d3eb9;color:#fff;border-color:#7d3eb9;border-radius:8px;padding:6px 16px;font-weight:500;display:flex;align-items:center;gap:6px;">
                                <?php echo esc_html( kiriof_helper()->tlThis('Connect',$locale) ); ?>
                            </button>
                        </div>
                        <div style="margin-top:8px;font-size:12px;color:#50575e;line-height:1.5;">
                            <?php echo esc_html( kiriof_helper()->tlThis('By clicking Connect, you agree to accept KiriminAja\'s',$locale) ); ?>
                            <a href="https://kiriminaja.com/syarat-ketentuan" target="_blank" style="color:#7d3eb9;"><?php echo esc_html( kiriof_helper()->tlThis('terms and conditions',$locale) ); ?></a>
                            <?php echo esc_html( kiriof_helper()->tlThis('and its',$locale) ); ?>
                            <a href="https://kiriminaja.com/privacy-policy" target="_blank" style="color:#7d3eb9;"><?php echo esc_html( kiriof_helper()->tlThis('privacy policy',$locale) ); ?></a>.
                        </div>
                        <div id="kiriof-connect-msg" style="margin-top:8px;font-size:13px;display:none;"></div>
                    </div>

                    <!-- Right: how-to box -->
                    <div style="flex:0 0 320px;background:#f9f9f9;border:1px solid #e3e3e3;border-radius:10px;padding:16px;font-size:13px;color:#50575e;line-height:1.6;">
                        <div style="font-weight:600;color:#1d2327;margin-bottom:8px;"><?php echo esc_html( kiriof_helper()->tlThis('How to Obtain Your Kiriminaja Credentials:',$locale) ); ?></div>
                        <ol style="margin:0;padding-left:18px;">
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Log in to your Kiriminaja dashboard.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Go to the Settings menu and select App Integrations.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Click Add Integration and choose WooCommerce.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Enter your store domain.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Please allow up to 2 business days for API generation.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Setup Key will appear on the App Integrations page.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Copy and paste the Setup Key above.',$locale) ); ?></li>
                            <li><?php echo esc_html( kiriof_helper()->tlThis('Start using Kiriminaja in your store.',$locale) ); ?></li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="text-align:center;padding:16px 0;color:#8c8f94;font-size:12px;">
            <?php echo esc_html( kiriof_helper()->tlThis('© 2025 PT Selalu Siap Solusi. All rights reserved.',$locale) ); ?>
        </div>

    </div>
</div>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>

    // Setup Key Connect handler
    jQuery(document).on('click','#kiriof-setup-key-connect',function(){
        var $btn = jQuery(this);
        var key  = jQuery('#kiriof-setup-key-input').val().trim();
        var $msg = jQuery('#kiriof-connect-msg');
        if (!key) { $msg.show().css('color','#d63638').text('<?php echo esc_js(kiriof_helper()->tlThis('Please enter a setup key.',$locale)); ?>'); return; }
        $btn.prop('disabled',true).text('<?php echo esc_js(kiriof_helper()->tlThis('Connecting…',$locale)); ?>');
        $msg.hide();
        jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_store_integration_data',data:{setup_key:key,nonce:kiriofAjax.nonce}},complete:function(r){var p=kiriofParseAjaxResponse(r);if(p&&p.status===200){window.location.reload()}else{$btn.prop('disabled',false).text('<?php echo esc_js(kiriof_helper()->tlThis('Connect',$locale)); ?>');$msg.show().css('color','#d63638').text((p&&p.message)?p.message:'<?php echo esc_js(kiriof_helper()->tlThis('Connection failed. Please check your setup key.',$locale)); ?>')}}});
    });

    // Disconnect handler
    jQuery('body').on('click','.kj-disconnect',function(e){
        if(!confirm('<?php echo esc_js(kiriof_helper()->tlThis('Disconnect KiriminAja integration?',$locale)); ?>')) return;
        jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_disconnect_integration',data:{nonce:kiriofAjax.nonce}},error:function(){alert('<?php echo esc_js(kiriof_helper()->tlThis('Network error.',$locale)); ?>')},complete:function(r){var p=kiriofParseAjaxResponse(r);if(p&&p.status===200){window.location.reload();return}alert((p&&p.message)?p.message:'<?php echo esc_js(kiriof_helper()->tlThis('Disconnect failed.',$locale)); ?>')}});
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
