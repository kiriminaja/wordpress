jQuery(document).ready(function (){


    const newVersionIsExist = jQuery('#kj-setting-link').data('update') === 1;

    if (!newVersionIsExist){return;}
    const pluginTable = jQuery(".wp-list-table.plugins").find(`[data-plugin='kiriminaja/kiriminaja.php']`)

    if (pluginTable.length<1){return;}
    pluginTable.addClass('kj-plugin-row')
    pluginTable.addClass('update')
    jQuery( 
        `
            <tr class="plugin-update-tr active">
               <td colspan="4" class="plugin-update colspanchange">
                  <div class="update-message notice inline notice-warning notice-alt">
                     <p>There is a new version of KiriminAja available. <a href="https://storage.googleapis.com/tprt0ezsggqjornc7nf1wwluvgulhr/wp/kiriminaja-pre-release-v0.1.0.zip" target="_blank" class="update-link" aria-label="Update WooCommerce now">Download Now</a>.</p>
                  </div>
               </td>
            </tr>
        `
    ).insertAfter( ".kj-plugin-row" );
    
})