<script lang="ts">
  import {
    IconAdjustmentsHorizontal,
    IconChevronLeft,
    IconChevronRight,
    IconEye,
    IconMapPin,
    IconPrinter,
    IconRefresh,
    IconSearch,
    IconTrash,
    IconX,
  } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import { Input } from '$lib/components/ui/input';
  import * as Select from '$lib/components/ui/select';
  import type { TransactionFilters, TransactionRow, TransactionsBootstrap } from './types';

  let { bootstrap }: { bootstrap: TransactionsBootstrap } = $props();
  function initialFilters(): TransactionFilters {
    return { ...bootstrap.filters };
  }
  let filters = $state<TransactionFilters>(initialFilters());
  let selected = $state<Record<string, boolean>>({});

  const selectedRows = $derived(bootstrap.rows.filter((row) => selected[row.kaOrderId]));
  const selectableRows = $derived(bootstrap.rows.filter((row) => !row.selection.disabled));
  const allSelected = $derived(selectableRows.length > 0 && selectableRows.every((row) => selected[row.kaOrderId]));
  const selectedPickupCount = $derived(selectedRows.filter((row) => row.selection.canPickup).length);
  const selectedPrintCount = $derived(selectedRows.filter((row) => row.selection.canPrint).length);

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
    navigate({
      key: filters.key,
      search_by: filters.search_by,
      month: filters.month === 'all' ? '' : filters.month,
      status: filters.status,
      cod: filters.cod === 'all' ? '' : filters.cod,
      courier: filters.courier === 'all' ? '' : filters.courier,
      print_status: filters.print_status === 'all' ? '' : filters.print_status,
      per_page: String(bootstrap.pagination.perPage),
    });
  }

  function clearFilters(): void {
    navigate({ key: '', month: '', status: 'all', cod: '', courier: '', print_status: '', search_by: 'wc_order_id' });
  }

  function toggleAll(checked: boolean): void {
    selected = Object.fromEntries(selectableRows.map((row) => [row.kaOrderId, checked]));
  }

  function toggleRow(row: TransactionRow, checked: boolean): void {
    selected = { ...selected, [row.kaOrderId]: checked };
  }

  function printSelected(): void {
    const form = document.querySelector<HTMLFormElement>('#kiriof-print-bulk-form');
    if (!form) return;
    form.querySelectorAll('input[name="oids[]"]').forEach((input) => input.remove());
    for (const row of selectedRows.filter((item) => item.selection.canPrint)) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'oids[]';
      input.value = row.kaOrderId;
      form.append(input);
    }
    form.requestSubmit();
  }

  function currency(amount: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount)}`;
  }

  function toneClass(tone: TransactionRow['status']['tone']): string {
    return `is-${tone}`;
  }
</script>

<div class="kiriof-transactions-app">
  <header class="kiriof-transactions-toolbar">
    <div class="kiriof-transactions-toolbar__title">
      <img src={bootstrap.toolbar.logoUrl} alt="" />
      <span aria-hidden="true">›</span>
      <strong>{bootstrap.toolbar.title}</strong>
    </div>
    <div class="kiriof-transactions-toolbar__actions">
      <Button id="kj-print-btn" variant="outline" disabled={selectedPrintCount === 0} onclick={printSelected}>
        <IconPrinter />
        {bootstrap.i18n.print}
        {#if selectedPrintCount}<span>{selectedPrintCount}</span>{/if}
      </Button>
      <Button id="kj-request-pickup-btn" data-kj-action="request-pickup" disabled={selectedPickupCount === 0}>
        {bootstrap.i18n.requestPickup}
        {#if selectedPickupCount}<span>{selectedPickupCount}</span>{/if}
      </Button>
    </div>
  </header>

  <section class="kiriof-transactions-card">
    <div class="kiriof-transactions-filterbar">
      <form
        class="kiriof-transactions-search"
        onsubmit={(event) => {
          event.preventDefault();
          applyFilters();
        }}
      >
        <Select.Root type="single" bind:value={filters.search_by}>
          <Select.Trigger class="kiriof-filter-search-by"><Select.Value /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="wc_order_id">{bootstrap.i18n.orderNumber}</Select.Item>
            <Select.Item value="ka_order_id">{bootstrap.i18n.kaOrderId}</Select.Item>
            <Select.Item value="awb">{bootstrap.i18n.awb}</Select.Item>
          </Select.Content>
        </Select.Root>
        <div class="kiriof-transactions-search__input">
          <IconSearch />
          <Input bind:value={filters.key} placeholder={bootstrap.i18n.search} />
        </div>
      </form>

      <div class="kiriof-transactions-filtergrid">
        <Select.Root type="single" bind:value={filters.status}>
          <Select.Trigger><IconAdjustmentsHorizontal /><Select.Value placeholder={bootstrap.i18n.status} /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            {#each bootstrap.statusOptions as option}
              <Select.Item value={option.value}>{option.label} ({option.count})</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.month}>
          <Select.Trigger><Select.Value placeholder={bootstrap.i18n.allDates} /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allDates}</Select.Item>
            {#each Object.entries(bootstrap.monthOptions) as [value, label]}
              <Select.Item {value}>{label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.cod}>
          <Select.Trigger><Select.Value placeholder={bootstrap.i18n.allPayment} /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPayment}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.cod}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.nonCod}</Select.Item>
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.print_status}>
          <Select.Trigger><Select.Value placeholder={bootstrap.i18n.allPrints} /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPrints}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.printed}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.unprinted}</Select.Item>
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.courier}>
          <Select.Trigger><Select.Value placeholder={bootstrap.i18n.allCouriers} /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allCouriers}</Select.Item>
            {#each bootstrap.couriers as courier}
              <Select.Item value={courier.value}>{courier.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Button onclick={applyFilters}>{bootstrap.i18n.apply}</Button>
        <Button variant="ghost" size="icon" onclick={clearFilters} aria-label={bootstrap.i18n.clear} title={bootstrap.i18n.clear}>
          <IconX />
        </Button>
      </div>
    </div>

    <div class="kiriof-transactions-meta">
      <span>{bootstrap.pagination.total} {bootstrap.i18n.items}</span>
      <Select.Root type="single" value={String(bootstrap.pagination.perPage)} onValueChange={(value: string) => navigate({ per_page: value || '25' })}>
        <Select.Trigger size="sm"><Select.Value /></Select.Trigger>
        <Select.Content class="kiriof-shadcn">
          {#each ['10', '25', '50', '100'] as size}<Select.Item value={size}>{size}</Select.Item>{/each}
        </Select.Content>
      </Select.Root>
    </div>

    <div class="kiriof-transactions-tablewrap">
      <table class="kiriof-transactions-table">
        <thead>
          <tr>
            <th class="is-check"><Checkbox checked={allSelected} indeterminate={selectedRows.length > 0 && !allSelected} onCheckedChange={(checked) => toggleAll(Boolean(checked))} /></th>
            <th>{bootstrap.i18n.order}</th>
            <th>{bootstrap.i18n.expedition}</th>
            <th>{bootstrap.i18n.airwaybill}</th>
            <th>{bootstrap.i18n.route}</th>
            <th>{bootstrap.i18n.packages}</th>
            <th class="is-actions">{bootstrap.i18n.action}</th>
          </tr>
        </thead>
        <tbody>
          {#if bootstrap.rows.length === 0}
            <tr><td colspan="7" class="kiriof-empty-cell">{bootstrap.i18n.notFound}</td></tr>
          {:else}
            {#each bootstrap.rows as row (row.id)}
              <tr class:is-selected={selected[row.kaOrderId]}>
                <td class="is-check">
                  <Checkbox
                    checked={Boolean(selected[row.kaOrderId])}
                    disabled={row.selection.disabled}
                    aria-label={`Select order ${row.wcOrderId}`}
                    title={row.selection.title}
                    onCheckedChange={(checked) => toggleRow(row, Boolean(checked))}
                  />
                  <input type="checkbox" name="transaction_id[]" value={row.kaOrderId} checked={Boolean(selected[row.kaOrderId])} data-can-pickup={row.selection.canPickup ? '1' : '0'} data-can-print={row.selection.canPrint ? '1' : '0'} hidden readonly />
                </td>
                <td>
                  <a class="kiriof-order-link" href={row.wcOrderUrl} target="_blank">#{row.wcOrderId}</a>
                  <strong class="kiriof-row-title">{row.customer.name}</strong>
                  {#if row.customer.phone}<a class="kiriof-row-muted" href={`tel:${row.customer.phone}`}>{row.customer.phone}</a>{/if}
                  <span class="kiriof-row-muted">{row.createdAt}</span>
                </td>
                <td>
                  <strong class="kiriof-row-title">{row.courier.service}</strong>
                  <div class="kiriof-row-badges">
                    <span class="kiriof-transaction-status {toneClass(row.status.tone)}" title={row.status.deficit ? 'COD settlement requires action' : undefined}>{row.status.label}</span>
                    <span class="kiriof-row-muted">via {row.courier.paymentLabel}</span>
                  </div>
                  <span class="kiriof-print-status {row.printStatus}">{row.printStatus === 'printed' ? bootstrap.i18n.printedLabel : bootstrap.i18n.unprintedLabel}</span>
                </td>
                <td>
                  <span class="kiriof-row-label">AWB</span>
                  <strong>{row.awb || '—'}</strong>
                  <span class="kiriof-row-label">KA Order ID</span>
                  <code>{row.kaOrderId}</code>
                </td>
                <td>
                  <strong class="kiriof-row-title">{row.route.origin}</strong>
                  <span class="kiriof-route-arrow">↓ To</span>
                  <strong class="kiriof-row-title">{row.route.destination}</strong>
                  {#each row.route.addressLines as line}<span class="kiriof-row-muted">{line}</span>{/each}
                </td>
                <td>
                  <span class="kiriof-row-muted">{row.package.weight} g{row.package.quantity > 1 ? ` × ${row.package.quantity}` : ''}</span>
                  <strong class="kiriof-row-title">{currency(row.package.paidShipping)}</strong>
                  <div class="kiriof-fee-pills">
                    {#if row.package.insurance > 0}<span>Ins {currency(row.package.insurance)}</span>{/if}
                    {#if row.package.codFee > 0}<span>COD {currency(row.package.codFee)}</span>{/if}
                    {#if row.package.itemDiscount > 0}<span class="is-discount">{row.package.itemCoupon} −{currency(row.package.itemDiscount)}</span>{/if}
                    {#if row.package.shippingDiscount > 0}<span class="is-discount">{row.package.shippingCoupon} −{currency(row.package.shippingDiscount)}</span>{/if}
                  </div>
                </td>
                <td class="is-actions">
                  <div class="kiriof-row-actions">
                    {#if row.actions.preview}
                      <Button variant="outline" size="icon-sm" class="order-preview" data-order-id={row.wcOrderId} title={bootstrap.i18n.detail} aria-label={bootstrap.i18n.detail}><IconEye /></Button>
                    {/if}
                    {#if row.actions.changeOrigin}
                      <Button variant="outline" size="icon-sm" class="kiriof-change-origin-button" data-ka-order-id={row.kaOrderId} data-current-origin={row.actionData.currentOrigin} data-current-origin-address={row.actionData.currentOriginAddress} data-current-location-id={row.actionData.currentLocationId} data-nonce={row.actionData.nonce} title={bootstrap.i18n.changeOrigin} aria-label={bootstrap.i18n.changeOrigin}><IconMapPin /></Button>
                    {/if}
                    {#if row.actions.adjustDeficit}
                      <Button variant="outline" size="icon-sm" data-kj-action="cod-adjust" data-ka-order-id={row.kaOrderId} data-current-cod={row.actionData.currentCod} data-cod-minimum={row.actionData.codMinimum} data-cod-maximum={row.actionData.codMaximum} data-shipping-cost={row.actionData.shippingCost} data-insurance-fee={row.actionData.insuranceFee} data-cod-fee={row.actionData.codFee} data-item-price={row.actionData.itemPrice} data-item-discount={row.actionData.itemDiscount} data-shipping-discount={row.actionData.shippingDiscount} data-item-coupon={row.actionData.itemCoupon} data-shipping-coupon={row.actionData.shippingCoupon} data-nonce={row.actionData.nonce} title={bootstrap.i18n.adjustDeficit} aria-label={bootstrap.i18n.adjustDeficit}><IconRefresh /></Button>
                      <Button variant="destructive" size="icon-sm" data-kj-action="cancel-deficit" data-ka-order-id={row.kaOrderId} data-nonce={row.actionData.nonce} title={bootstrap.i18n.cancel} aria-label={bootstrap.i18n.cancel}><IconTrash /></Button>
                    {:else}
                      {#if row.actions.print}<Button variant="outline" size="icon-sm" href={row.actions.printUrl} target="_blank" title={bootstrap.i18n.print} aria-label={bootstrap.i18n.print}><IconPrinter /></Button>{/if}
                      {#if row.actions.cancel}<Button variant="destructive" size="icon-sm" data-kj-action="cancel" data-order-id={row.kaOrderId} title={bootstrap.i18n.cancel} aria-label={bootstrap.i18n.cancel}><IconTrash /></Button>{/if}
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          {/if}
        </tbody>
      </table>
    </div>

    <footer class="kiriof-transactions-pagination">
      <span>{bootstrap.pagination.total} {bootstrap.i18n.items}</span>
      <div>
        <Button variant="outline" size="icon-sm" disabled={bootstrap.pagination.page <= 1} onclick={() => navigate({ cpage: String(bootstrap.pagination.page - 1) })}><IconChevronLeft /></Button>
        <span>{bootstrap.pagination.page} {bootstrap.i18n.pageOf} {bootstrap.pagination.totalPages}</span>
        <Button variant="outline" size="icon-sm" disabled={bootstrap.pagination.page >= bootstrap.pagination.totalPages} onclick={() => navigate({ cpage: String(bootstrap.pagination.page + 1) })}><IconChevronRight /></Button>
      </div>
    </footer>
  </section>
</div>
