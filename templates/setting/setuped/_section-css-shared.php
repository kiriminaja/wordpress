<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Shared CSS for all settings pages (list + detail sections)
?>
    .kj-settings { max-width: 680px; margin: 0 auto; }
    .kj-detail { max-width: 680px; margin: 0 auto; }
    .kj-notice { padding: 10px 12px; border-left: 4px solid; background: #fff; border: 1px solid #c3c4c7; margin-bottom: 1rem; }
    .kj-notice-warning { border-left-color: #7d3eb9; }
    .kj-notice-success { border-left-color: #00a32a; }
    .kj-setting-row {
        display: flex; align-items: center;
        padding: 14px 16px; background: #fff; border: 1px solid #c3c4c7;
        margin-bottom: -1px; text-decoration: none; color: inherit;
    }
    .kj-setting-row:first-child { border-radius: 4px 4px 0 0; }
    .kj-setting-row:last-child { border-radius: 0 0 4px 4px; margin-bottom: 0; }
    a.kj-setting-row:hover { background: #f6f7f7; color: inherit; }
    .kj-setting-row-inner { display: flex; align-items: center; gap: 12px; width: 100%; }
    .kj-row-icon { flex-shrink: 0; }
    .kj-setting-row-text { flex: 1; min-width: 0; }
    .kj-setting-row-label { display: block; font-size: 14px; font-weight: 500; }
    .kj-setting-row-desc { display: block; font-size: 12px; color: #646970; margin-top: 2px; }
    .kj-status-pill { display: inline-flex; align-items: center; flex-shrink: 0; max-width: 220px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.6; text-align: center; white-space: normal; }
    .kj-status-pill.is-ready { background: #edfaef; color: #007017; border: 1px solid #b7e5be; }
    .kj-status-pill.is-warning { background: #fcf0f1; color: #8a2424; border: 1px solid #f4cccc; }
    .kj-group-header {
        padding: 20px 16px 8px; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px; color: #646970;
    }
    .kj-group-header:first-child { padding-top: 0; }
    .kj-ios-toggle { position: relative; display: inline-block; cursor: pointer; vertical-align: middle; flex-shrink: 0; }
    .kj-ios-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
    .kj-ios-toggle-track { display: inline-block; width: 44px; height: 24px; border-radius: 12px; background: #ccc; position: relative; transition: background 0.2s; }
    .kj-ios-toggle input:checked + .kj-ios-toggle-track { background: #2271b1; }
    .kj-ios-toggle-thumb { position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: left 0.2s; }
    .kj-ios-toggle input:checked + .kj-ios-toggle-track .kj-ios-toggle-thumb { left: 22px; }
    .kj-ios-toggle input:disabled + .kj-ios-toggle-track { opacity: 0.5; cursor: not-allowed; }
    .kj-courier-grid { display: grid; grid-template-columns: 1fr; gap: 0; }
    .kj-courier-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
    .kj-courier-item:last-child { border-bottom: none; }
    .kj-courier-item-name { font-weight: 500; }
    .kj-courier-item-type { font-size: 12px; color: #787c82; }
    @media (max-width: 560px) {
        .kj-setting-row-inner { align-items: flex-start; flex-wrap: wrap; }
        .kj-setting-row-text { flex-basis: calc(100% - 36px); }
        .kj-status-pill { margin-left: 36px; max-width: calc(100% - 36px); }
        .kj-chevron { margin-left: auto; }
    }
