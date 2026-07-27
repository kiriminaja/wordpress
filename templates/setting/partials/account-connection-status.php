<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared KiriminAja account connection status card.
 *
 * @var bool        $kiriof_is_connected
 * @var object|null $kiriof_profile
 * @var bool        $kiriof_profile_err
 */
?>
<?php if ( $kiriof_is_connected && $kiriof_profile ) : ?>
	<div class="kiriof-account-connection-status" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
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
			<button type="button" class="button kj-disconnect" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html( __( 'Disconnect', 'kiriminaja-official' ) ); ?></button>
		</div>
	</div>
<?php elseif ( $kiriof_is_connected && $kiriof_profile_err ) : ?>
	<div style="color:#d63638;"><?php echo esc_html( __( 'Unable to load account information. Your integration may be incomplete.', 'kiriminaja-official' ) ); ?></div>
	<div style="margin-top:12px;text-align:right;">
		<button type="button" class="button kj-disconnect" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html( __( 'Disconnect', 'kiriminaja-official' ) ); ?></button>
	</div>
<?php elseif ( $kiriof_is_connected ) : ?>
	<div style="color:#646970;"><?php echo esc_html( __( 'Account is connected, but profile details are unavailable right now.', 'kiriminaja-official' ) ); ?></div>
	<div style="margin-top:12px;text-align:right;">
		<button type="button" class="button kj-disconnect" style="color:#b32d2e;border-color:#b32d2e;"><?php echo esc_html( __( 'Disconnect', 'kiriminaja-official' ) ); ?></button>
	</div>
<?php endif; ?>
