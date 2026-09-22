<script lang="ts">
  import { IconSearch } from '@tabler/icons-svelte';
  import ListPagination from '../admin-list/ListPagination.svelte';
  import StatusTabs from '../admin-list/StatusTabs.svelte';
  import type { TransactionFiltersBootstrap } from './types';

  let { bootstrap }: { bootstrap: TransactionFiltersBootstrap } = $props();
  function initial(): TransactionFiltersBootstrap['filters'] { return { ...bootstrap.filters }; }
  let filters = $state(initial());

  function navigate(values: Record<string, string>): void {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(values)) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
    if (!('cpage' in values)) url.searchParams.set('cpage', '1');
    window.location.assign(url.toString());
  }

  function applyFilters(): void {
    navigate({ month: filters.month, cod: filters.cod, courier: filters.courier, print_status: filters.print_status });
  }
</script>

<div class="kiriof-transaction-controls">
  <div class="kiriof-admin-list__filter">
    <StatusTabs tabs={bootstrap.statusTabs} value={filters.status || 'all'} onChange={(status) => navigate({ status })} />
    <form class="kiriof-transaction-search" onsubmit={(event) => { event.preventDefault(); navigate({ key: filters.key, search_by: filters.search_by }); }}>
      <select bind:value={filters.search_by}><option value="wc_order_id">{bootstrap.i18n.orderNumber}</option><option value="ka_order_id">{bootstrap.i18n.kaOrderId}</option><option value="awb">{bootstrap.i18n.awb}</option></select>
      <span class="kiriof-search-field"><IconSearch size={17} /><input type="search" bind:value={filters.key} placeholder={bootstrap.i18n.search} /></span>
    </form>
  </div>
  <div class="kiriof-admin-list__toolbar">
    <div class="kiriof-transaction-selects"><select bind:value={filters.month}><option value="">{bootstrap.i18n.allDates}</option>{#each Object.entries(bootstrap.monthOptions) as [value,label]}<option {value}>{label}</option>{/each}</select><select bind:value={filters.cod}><option value="">{bootstrap.i18n.allPayment}</option><option value="1">{bootstrap.i18n.cod}</option><option value="0">{bootstrap.i18n.nonCod}</option></select><select bind:value={filters.courier}><option value="">{bootstrap.i18n.allCouriers}</option>{#each bootstrap.couriers as courier}<option value={courier.value}>{courier.label}</option>{/each}</select><select bind:value={filters.print_status}><option value="">{bootstrap.i18n.allPrints}</option><option value="1">{bootstrap.i18n.printed}</option><option value="0">{bootstrap.i18n.unprinted}</option></select><button class="button" onclick={applyFilters}>{bootstrap.i18n.apply}</button></div>
    <div class="kiriof-transaction-pagination"><span>{bootstrap.pagination.total} {bootstrap.i18n.items}</span><ListPagination page={bootstrap.pagination.page} totalPages={bootstrap.pagination.totalPages} label={bootstrap.i18n.pageOf} onChange={(page) => navigate({ cpage: String(page) })} /></div>
  </div>
</div>
