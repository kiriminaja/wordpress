<script lang="ts">
  import type { TransactionTableBootstrap } from './types';

  let { bootstrap }: { bootstrap: TransactionTableBootstrap } = $props();
</script>

<div class="kiriof-table-wrap">
  <table class="wp-list-table widefat fixed striped table-view-list posts kiriof-transaction-table">
    <thead>
      <tr>
        <th style="width: 24px" scope="col" class="manage-column column-thumb kiriof-col-select"><input style="margin:0" type="checkbox" id="check_order_id_all_top" /></th>
        <th scope="col" class="manage-column column-thumb kiriof-col-order">{bootstrap.i18n.order}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-expedition">{bootstrap.i18n.expedition}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-airwaybill">{bootstrap.i18n.airwaybill}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-shipto">{bootstrap.i18n.route}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-packages">{bootstrap.i18n.packages}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-action kiriof-actions-column" style="width:7rem">{bootstrap.i18n.action}</th>
      </tr>
    </thead>
    <tbody id="the-list">
      {#each bootstrap.rowsHtml as rowHtml}
        {@html rowHtml}
      {/each}
    </tbody>
    <tfoot>
      <tr>
        <th style="width: 24px" scope="col" class="manage-column column-thumb kiriof-col-select"><input style="margin:0" type="checkbox" id="check_order_id_all_bottom" /></th>
        <th scope="col" class="manage-column column-thumb kiriof-col-order">{bootstrap.i18n.order}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-expedition">{bootstrap.i18n.expedition}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-airwaybill">{bootstrap.i18n.airwaybill}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-shipto">{bootstrap.i18n.route}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-packages">{bootstrap.i18n.packages}</th>
        <th scope="col" class="manage-column column-thumb kiriof-col-action"></th>
      </tr>
    </tfoot>
  </table>
</div>
