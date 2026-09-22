<script lang="ts">
  import { IconEye, IconPrinter } from '@tabler/icons-svelte';
  import StatusBadge from '../admin-list/StatusBadge.svelte';
  import type { PickupDetailBootstrap } from './types';

  let { bootstrap }: { bootstrap: PickupDetailBootstrap } = $props();

  function statusTone(status: string): 'success' | 'warning' | 'info' | 'neutral' {
    const value = status.toLowerCase();
    if (value.includes('finish') || value.includes('ship')) return 'success';
    if (value.includes('cancel') || value.includes('return')) return 'warning';
    if (value.includes('pickup') || value.includes('process')) return 'info';
    return 'neutral';
  }
</script>

<div class="kiriof-pickup-detail">
  <div class="kiriof-summary-grid">
    {#each bootstrap.summary as item}
      <article><strong>{item.value}</strong><span>{item.label}</span></article>
    {/each}
  </div>

  <div class="kiriof-table-wrap">
    <table class="wp-list-table widefat fixed striped">
      <thead><tr><th>#</th><th>{bootstrap.i18n.order}</th><th>{bootstrap.i18n.courier}</th><th>{bootstrap.i18n.airwaybill}</th><th>{bootstrap.i18n.route}</th><th>{bootstrap.i18n.packages}</th><th>{bootstrap.i18n.codValue}</th><th>{bootstrap.i18n.status}</th><th class="kiriof-actions-column">{bootstrap.i18n.action}</th></tr></thead>
      <tbody>
        {#if bootstrap.rows.length === 0}<tr><td colspan="9" class="kiriof-empty-cell">{bootstrap.i18n.empty}</td></tr>{/if}
        {#each bootstrap.rows as row}
          <tr>
            <td>{row.number}</td>
            <td><a href={row.orderUrl} target="_blank" rel="noopener noreferrer"><strong>{row.orderId}</strong></a></td>
            <td><strong>{row.courier}</strong><small>{bootstrap.i18n.pickup}: {bootstrap.schedule}</small></td>
            <td>{row.awb || '—'}<small>{row.orderId}</small></td>
            <td>{row.origin}<small>→ {row.destination || '—'}</small></td>
            <td>{row.weight > 0 ? `${row.weight} g` : '—'}<small>{row.fee}</small></td>
            <td><strong>{row.codValue}</strong></td>
            <td><StatusBadge label={row.status} tone={statusTone(row.status)} /></td>
            <td><div class="kiriof-row-actions">{#if row.printUrl}<a class="button" href={row.printUrl} target="_blank" rel="noopener noreferrer" title={bootstrap.i18n.print} aria-label={bootstrap.i18n.print}><IconPrinter size={18} /></a>{/if}<a class="button" href={row.orderUrl} target="_blank" rel="noopener noreferrer" title={bootstrap.i18n.detail} aria-label={bootstrap.i18n.detail}><IconEye size={18} /></a></div></td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
</div>
