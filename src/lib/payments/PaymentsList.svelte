<script lang="ts">
  import { IconCalendarClock, IconCreditCardPay, IconEye, IconSearch } from '@tabler/icons-svelte';
  import ListPagination from '../admin-list/ListPagination.svelte';
  import StatusBadge from '../admin-list/StatusBadge.svelte';
  import StatusTabs from '../admin-list/StatusTabs.svelte';
  import type { PaymentsBootstrap } from './types';

  let { bootstrap }: { bootstrap: PaymentsBootstrap } = $props();
  function initialFilters(): { search: string; month: string } {
    return { search: bootstrap.filters.key, month: bootstrap.filters.month };
  }
  const initial = initialFilters();
  let search = $state(initial.search);
  let month = $state(initial.month);

  function navigate(values: Record<string, string>): void {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(values)) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
    if (!('cpage' in values)) url.searchParams.set('cpage', '1');
    window.location.assign(url.toString());
  }

  function actionClass(type: string): string {
    if (type === 'pay') return 'kiriof-payment-button';
    if (type === 'reschedule') return 'kiriof-reschedule-button';
    return '';
  }
</script>

<div class="kiriof-admin-list">
  <div class="kiriof-admin-list__filter">
    <StatusTabs tabs={bootstrap.statusTabs} value={bootstrap.filters.status === 'all' ? '' : bootstrap.filters.status} onChange={(status) => navigate({ status })} />
    <form onsubmit={(event) => { event.preventDefault(); navigate({ key: search }); }}>
      <label class="screen-reader-text" for="kiriof-svelte-payment-search">Search</label>
      <span class="kiriof-search-field"><IconSearch size={17} aria-hidden="true" /><input id="kiriof-svelte-payment-search" type="search" bind:value={search} placeholder={bootstrap.i18n.search} /></span>
    </form>
  </div>
  <div class="kiriof-admin-list__toolbar">
    <div><select bind:value={month}><option value="">{bootstrap.i18n.allDates}</option>{#each Object.entries(bootstrap.monthOptions) as [value, label]}<option {value}>{label}</option>{/each}</select><button class="button" onclick={() => navigate({ month })}>{bootstrap.i18n.apply}</button></div>
    <ListPagination page={bootstrap.pagination.page} totalPages={bootstrap.pagination.totalPages} label={bootstrap.i18n.pageOf} onChange={(page) => navigate({ cpage: String(page) })} />
  </div>
  <div class="kiriof-table-wrap">
    <table class="wp-list-table widefat fixed striped">
      <thead><tr><th>No</th><th>{bootstrap.i18n.pickupNumber}</th><th>{bootstrap.i18n.schedule}</th><th>{bootstrap.i18n.fees}</th><th>{bootstrap.i18n.orders}</th><th>{bootstrap.i18n.paymentMethod}</th><th>{bootstrap.i18n.paymentStatus}</th><th class="kiriof-actions-column">{bootstrap.i18n.action}</th></tr></thead>
      <tbody>
        {#if bootstrap.rows.length === 0}<tr><td colspan="8" class="kiriof-empty-cell">{bootstrap.i18n.empty}</td></tr>{/if}
        {#each bootstrap.rows as row}
          <tr><td><strong>{row.number}</strong></td><td><strong>{row.pickupNumber}</strong><small>{bootstrap.i18n.requested}: {row.requestedAt}</small></td><td>{row.schedule}</td><td><strong>{row.fees}</strong></td><td>{row.orders} {bootstrap.i18n.order}</td><td><StatusBadge label={row.method} tone={row.method === 'QRIS' ? 'info' : 'neutral'} /></td><td><StatusBadge label={row.status === 'paid' ? bootstrap.statusTabs[2].label : bootstrap.statusTabs[1].label} tone={row.status === 'paid' ? 'success' : 'warning'} /></td><td><div class="kiriof-row-actions">{#each row.actions as action}{#if action.type === 'details'}<a class="button" href={action.href} title={action.label} aria-label={action.label}><IconEye size={18} /></a>{:else}<button class={`button ${actionClass(action.type)}`} type="button" data-pickup-number={row.pickupNumber} title={action.label} aria-label={action.label}>{#if action.type === 'pay'}<IconCreditCardPay size={18} />{:else}<IconCalendarClock size={18} />{/if}</button>{/if}{/each}</div></td></tr>
        {/each}
      </tbody>
    </table>
  </div>
  <div class="kiriof-admin-list__toolbar kiriof-admin-list__toolbar--bottom"><span></span><ListPagination page={bootstrap.pagination.page} totalPages={bootstrap.pagination.totalPages} label={bootstrap.i18n.pageOf} onChange={(page) => navigate({ cpage: String(page) })} /></div>
</div>
