<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Setup Guide notice.
 *
 * @var array $steps       Each step: ['title','description','done','required','url']
 * @var int   $done_count  Number of completed steps.
 */
?>
<div class="notice" style="border-left-color:#7d3eb9;padding:16px 20px;max-width:none;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="28" height="28" viewBox="0 0 28 28" style="flex-shrink:0">
            <defs>
                <linearGradient id="kiriof-sg-grad0" gradientUnits="userSpaceOnUse" x1="128" y1="0" x2="128" y2="256" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                    <stop offset="0" style="stop-color:rgb(100%,11.372549%,74.509804%);stop-opacity:1"/>
                    <stop offset="0.13" style="stop-color:rgb(87.45098%,11.372549%,74.117647%);stop-opacity:1"/>
                    <stop offset="0.3" style="stop-color:rgb(73.333333%,11.764706%,73.72549%);stop-opacity:1"/>
                    <stop offset="0.48" style="stop-color:rgb(62.352941%,11.764706%,73.333333%);stop-opacity:1"/>
                    <stop offset="0.65" style="stop-color:rgb(54.509804%,11.764706%,73.333333%);stop-opacity:1"/>
                    <stop offset="0.83" style="stop-color:rgb(49.803922%,11.764706%,73.333333%);stop-opacity:1"/>
                    <stop offset="1" style="stop-color:rgb(48.235294%,12.156863%,73.333333%);stop-opacity:1"/>
                </linearGradient>
                <linearGradient id="kiriof-sg-grad1" gradientUnits="userSpaceOnUse" x1="195.660004" y1="85.4842" x2="59.348301" y2="155.867004" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                    <stop offset="0" style="stop-color:rgb(100%,100%,100%);stop-opacity:1"/>
                    <stop offset="0.99" style="stop-color:rgb(94.509804%,94.509804%,94.509804%);stop-opacity:0.501961"/>
                </linearGradient>
                <linearGradient id="kiriof-sg-grad2" gradientUnits="userSpaceOnUse" x1="73.133102" y1="156.063995" x2="170.735001" y2="155.994995" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                    <stop offset="0" style="stop-color:rgb(100%,100%,100%);stop-opacity:0.101961"/>
                    <stop offset="1" style="stop-color:rgb(94.509804%,94.509804%,94.509804%);stop-opacity:0.8"/>
                </linearGradient>
            </defs>
            <rect x="0" y="0" width="28" height="28" rx="6" ry="6" style="fill:url(#kiriof-sg-grad0)"/>
            <path style="fill:url(#kiriof-sg-grad1)" d="M19.082 6.336l-2.188 6.645c-.152.527-.59.727-.894.445l-.606-.586-.289-.293-1.593 1.473c-3.11 3.379.077 6.57.077 6.57l-4.933-4.933c-.016-.016-.035-.031-.051-.047-.012-.016-.024-.031-.04-.043l-.03-.035c-.829-.922-.759-2.285.128-3.176l3.105-3.164-.27-.273-.593-.535c-.305-.281-.191-.789.2-.91l7.23-1.645c.394-.125.773.227.746.508z"/>
            <path style="fill:url(#kiriof-sg-grad2)" d="M17.973 18.473c.937.937.945 2.39.03 3.3-.917.907-2.347.864-3.277-.066l-1.238-1.23c-.52-.586-2.746-3.446.023-6.457zm-9.407-2.907c.016.012.028.028.04.043.015.016.035.031.05.047l3.375 3.375-3.316-3.3c-.067-.063-.125-.13-.18-.2zm-.03-.035c-.013-.012-.02-.024-.032-.04.012.016.02.028.032.04z"/>
        </svg>
        <div>
            <strong style="font-size:14px"><?php echo esc_html__( 'KiriminAja Setup Guide', 'kiriminaja-official' ); ?></strong>
            <span style="background:#7d3eb9;color:#fff;border-radius:20px;padding:1px 8px;font-size:11px;font-weight:600;margin-left:8px"><?php echo absint( $done_count ); ?>/<?php echo absint( count( $steps ) ); ?></span>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:0">
        <?php foreach ( $steps as $kiriof_step ) : ?>
        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-top:1px solid #f0f0f1;<?php echo $kiriof_step['done'] ? 'opacity:.6' : ''; ?>">
            <div style="flex-shrink:0;margin-top:1px">
                <?php if ( $kiriof_step['done'] ) : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" style="color:#00a32a"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12S6.477 2 12 2s10 4.477 10 10m-5.186-2.419a1 1 0 1 0-1.628-1.162l-4.314 6.04l-2.165-2.166a1 1 0 0 0-1.414 1.414l3 3a1 1 0 0 0 1.52-.126z" clip-rule="evenodd"/></svg>
                <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" style="color:#7d3eb9"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11s11-4.925 11-11S18.075 1 12 1m1 6a1 1 0 0 0-2 0v5a1 1 0 0 0 2 0zm-1 8.5a1.25 1.25 0 1 0 0-2.5a1.25 1.25 0 0 0 0 2.5"/></svg>
                <?php endif; ?>
            </div>
            <div style="min-width:0">
                <a href="<?php echo esc_url( $kiriof_step['url'] ); ?>" style="font-size:13px;font-weight:600;color:<?php echo $kiriof_step['done'] ? '#787c82' : '#7d3eb9'; ?>;text-decoration:<?php echo $kiriof_step['done'] ? 'line-through' : 'none'; ?>"><?php echo esc_html( $kiriof_step['title'] ); ?></a>
                <?php if ( ! $kiriof_step['required'] ) : ?>
                <span style="font-size:10px;color:#8c8f94;margin-left:4px"><?php echo esc_html__( '(Optional)', 'kiriminaja-official' ); ?></span>
                <?php endif; ?>
                <div style="font-size:12px;color:#646970;margin-top:2px"><?php echo esc_html( $kiriof_step['description'] ); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
