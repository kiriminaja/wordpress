<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Courier List (detail page)
 *
 * @var string $locale
 * @var string $kiriof_base_url
 */
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <?php $kiriof_title = __( 'Courier List', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail">

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div style="margin-bottom:0.75rem;">
                <button type="button" class="button kj-courier-enable-all"><?php echo esc_html( __( 'Enable All', 'kiriminaja-official' ) ); ?></button>
                <button type="button" class="button kj-courier-disable-all" style="margin-left:0.5rem"><?php echo esc_html( __( 'Disable All', 'kiriminaja-official' ) ); ?></button>
                <span class="kj-courier-status" style="margin-left:0.75rem;vertical-align:middle;font-size:12px;color:#50575e"></span>
            </div>
            <div id="kiriof-courier-list" class="kj-courier-grid">
                <div style="padding:0.5rem;text-align:center;color:#50575e"><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><?php echo esc_html( __( 'Loading couriers…', 'kiriminaja-official' ) ); ?></div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
    jQuery(document).ready(function($){var $list=$('#kiriof-courier-list'),$status=$('.kj-courier-status'),allCouriers=[],whitelistSet={};function render(){var h='';allCouriers.forEach(function(c){var e=whitelistSet.hasOwnProperty(c.code);h+='<div class="kj-courier-item"><div class="kj-courier-item-info"><div class="kj-courier-item-name">'+escHtml(c.name)+'</div><div class="kj-courier-item-type">'+escHtml(c.type||'')+'</div></div><label class="kj-ios-toggle"><input type="checkbox" class="kj-courier-toggle" data-code="'+escAttr(c.code)+'" data-name="'+escAttr(c.name)+'" '+(e?'checked':'')+'><span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span></label></div>'});$list.html(h||'<div style="padding:1rem;color:#787c82">No couriers</div>');updateStatus()}function updateStatus(){var c=Object.keys(whitelistSet).length;$status.text(c+' of '+allCouriers.length+' enabled')}function escHtml(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML}function escAttr(s){return(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}function saveW(){var ids=Object.keys(whitelistSet),names=ids.map(function(id){return whitelistSet[id]});return jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_store_courier_whitelist',data:{whitelist_ids:ids.join(','),whitelist_names:names.join(','),nonce:kiriofAjax.nonce}}})}jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_get_courier_whitelist',data:{nonce:kiriofAjax.nonce}},complete:function(r){var p=kiriofParseAjaxResponse(r);if(p&&p.status===200&&p.data){allCouriers=p.data.couriers||[];(p.data.whitelist_ids||[]).forEach(function(id){whitelistSet[id]=true});allCouriers.forEach(function(c){if(whitelistSet.hasOwnProperty(c.code))whitelistSet[c.code]=c.name})}render()},error:function(){$list.html('<div style="padding:1rem;color:#d63638">Failed to load couriers.</div>')}});$list.on('change','.kj-courier-toggle',function(){var $t=jQuery(this),code=$t.data('code'),name=$t.data('name');$t.prop('disabled',true);if($t.is(':checked'))whitelistSet[code]=name;else delete whitelistSet[code];saveW().always(function(){$t.prop('disabled',false)}).done(function(){updateStatus()}).fail(function(){if($t.is(':checked'))delete whitelistSet[code];else whitelistSet[code]=name;$t.prop('checked',!$t.is(':checked'));updateStatus()})});jQuery('.kj-courier-enable-all').on('click',function(){allCouriers.forEach(function(c){whitelistSet[c.code]=c.name});render();saveW()});jQuery('.kj-courier-disable-all').on('click',function(){whitelistSet={};render();saveW()})});
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
