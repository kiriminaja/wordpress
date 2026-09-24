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

extract( $settingsPageData->prepareAccount(), EXTR_SKIP );

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

    <?php $kiriof_title = __( 'Account Configuration', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail" style="max-width:720px;">

        <!-- Enabled Couriers -->
        <?php if ( ! empty( $kiriof_wl_id_arr ) ) : ?>
        <div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:16px;"><?php echo esc_html( __( 'Enabled Couriers', 'kiriminaja-official' ) ); ?></div>
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
			<div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:16px;"><?php echo esc_html( __( 'Connection', 'kiriminaja-official' ) ); ?></div>

			<?php if ( $kiriof_is_connected && $kiriof_profile ) : ?>
				<?php include KIRIOF_DIR . 'templates/setting/partials/account-connection-status.php'; ?>
			<?php elseif ( $kiriof_is_connected && $kiriof_profile_err ) : ?>
				<?php include KIRIOF_DIR . 'templates/setting/partials/account-connection-status.php'; ?>
			<?php else : ?>
                <!-- Not connected: setup key form -->
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <!-- Left: setup key input -->
                    <div style="flex:1;min-width:280px;">
                        <div style="margin-bottom:8px;">
                            <label style="font-size:13px;font-weight:500;color:#1d2327;"><?php echo esc_html( __( 'Setup Key', 'kiriminaja-official' ) ); ?> <span style="color:#d63638;">*</span></label>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input id="kiriof-setup-key-input" type="text" class="input-text regular-input" style="flex:1;max-width:340px;padding:8px 12px;border-radius:8px;border-color:#8c8f94;" placeholder="<?php echo esc_attr( __( 'Input your setup key for KiriminAja', 'kiriminaja-official' ) ); ?>">
                            <button id="kiriof-setup-key-connect" type="button" class="button" style="background:#7d3eb9;color:#fff;border-color:#7d3eb9;border-radius:8px;padding:6px 16px;font-weight:500;display:flex;align-items:center;gap:6px;">
                                <?php echo esc_html( __( 'Connect', 'kiriminaja-official' ) ); ?>
                            </button>
                        </div>
                        <div style="margin-top:8px;font-size:12px;color:#50575e;line-height:1.5;">
                            <?php echo esc_html( __( 'By clicking Connect, you agree to accept KiriminAja\'s', 'kiriminaja-official' ) ); ?>
                            <a href="https://kiriminaja.com/syarat-ketentuan" target="_blank" style="color:#7d3eb9;"><?php echo esc_html( __( 'terms and conditions', 'kiriminaja-official' ) ); ?></a>
                            <?php echo esc_html( __( 'and its', 'kiriminaja-official' ) ); ?>
                            <a href="https://kiriminaja.com/privacy-policy" target="_blank" style="color:#7d3eb9;"><?php echo esc_html( __( 'privacy policy', 'kiriminaja-official' ) ); ?></a>.
                        </div>
                        <div id="kiriof-connect-msg" style="margin-top:8px;font-size:13px;display:none;"></div>
                    </div>

                    <!-- Right: how-to box -->
                    <div style="flex:0 0 320px;background:#f9f9f9;border:1px solid #e3e3e3;border-radius:10px;padding:16px;font-size:13px;color:#50575e;line-height:1.6;">
                        <div style="font-weight:600;color:#1d2327;margin-bottom:8px;"><?php echo esc_html( __( 'How to Obtain Your Kiriminaja Credentials:', 'kiriminaja-official' ) ); ?></div>
                        <ol style="margin:0;padding-left:18px;">
                            <li><?php echo esc_html( __( 'Log in to your Kiriminaja dashboard.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Go to the Settings menu and select App Integrations.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Click Add Integration and choose WooCommerce.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Enter your store domain.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Please allow up to 2 business days for API generation.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Setup Key will appear on the App Integrations page.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Copy and paste the Setup Key above.', 'kiriminaja-official' ) ); ?></li>
                            <li><?php echo esc_html( __( 'Start using Kiriminaja in your store.', 'kiriminaja-official' ) ); ?></li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="text-align:center;padding:16px 0;color:#8c8f94;font-size:12px;">
            <?php echo esc_html( __( '© 2025 PT Selalu Siap Solusi. All rights reserved.', 'kiriminaja-official' ) ); ?>
        </div>

    </div>
</div>
