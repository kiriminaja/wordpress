<script lang="ts">
  import { onDestroy } from 'svelte';
  import {
    IconAdjustmentsHorizontal,
    IconCalendar,
    IconCash,
    IconChevronLeft,
    IconChevronRight,
    IconEye,
    IconMapPin,
    IconPrinter,
    IconRefresh,
    IconSearch,
    IconListNumbers,
    IconChevronDown,
    IconTrash,
    IconX,
  } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as ButtonGroup from '$lib/components/ui/button-group';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Select from '$lib/components/ui/select';
  import * as Table from '$lib/components/ui/table';
  import WorkspaceTabs from '$lib/ui/WorkspaceTabs.svelte';
  import SettingsToolbar from '$lib/settings/SettingsToolbar.svelte';
  import CourierCombobox from './CourierCombobox.svelte';
  import type { TransactionFilters, TransactionRow, TransactionsBootstrap } from './types';

  let { bootstrap: initialBootstrap }: { bootstrap: TransactionsBootstrap } = $props();
  function initialWorkspace(): TransactionsBootstrap {
    return structuredClone(initialBootstrap);
  }
  let bootstrap = $state<TransactionsBootstrap>(initialWorkspace());
  function initialFilters(): TransactionFilters {
    return { ...bootstrap.filters };
  }
  let filters = $state<TransactionFilters>(initialFilters());
  let selected = $state<Record<string, boolean>>({});
  let refreshing = $state(false);
  let navigationController: AbortController | null = null;

  const selectedRows = $derived(bootstrap.rows.filter((row) => selected[row.kaOrderId]));
  const selectableRows = $derived(bootstrap.rows.filter((row) => !row.selection.disabled));
  const allSelected = $derived(selectableRows.length > 0 && selectableRows.every((row) => selected[row.kaOrderId]));
  const selectedPickupCount = $derived(selectedRows.filter((row) => row.selection.canPickup).length);
  const selectedPrintCount = $derived(selectedRows.filter((row) => row.selection.canPrint).length);
  const selectedCount = $derived(selectedRows.length);
  const statusLabel = $derived(bootstrap.statusOptions.find((option) => option.value === filters.status)?.label ?? bootstrap.i18n.status);
  const monthLabel = $derived(filters.month ? bootstrap.monthOptions[filters.month] ?? bootstrap.i18n.allDates : bootstrap.i18n.allDates);
  const paymentLabel = $derived(filters.cod === '1' ? bootstrap.i18n.cod : filters.cod === '0' ? bootstrap.i18n.nonCod : bootstrap.i18n.allPayment);
  const printLabel = $derived(filters.print_status === '1' ? bootstrap.i18n.printed : filters.print_status === '0' ? bootstrap.i18n.unprinted : bootstrap.i18n.allPrints);
  const courierOptions = $derived([{ value: '', label: bootstrap.i18n.allCouriers }, ...bootstrap.couriers]);
  const searchByLabel = $derived(filters.search_by === 'ka_order_id' ? bootstrap.i18n.kaOrderId : filters.search_by === 'awb' ? bootstrap.i18n.awb : bootstrap.i18n.orderNumber);
  const orderIssueOption = $derived(bootstrap.statusOptions.find((option) => option.value === 'order-issue'));
  const scopeValue = $derived(filters.status === 'order-issue' ? 'order-issue' : 'regular');
  const scopeTabs = $derived([
    { value: 'regular', label: 'Regular Delivery' },
    { value: 'international', label: 'International Delivery', disabled: true, title: 'International delivery is not available in this workspace' },
    { value: 'instant', label: 'Instant Delivery', disabled: true, title: 'Instant delivery is not available in this workspace' },
    { value: 'order-issue', label: 'Order Issue', count: orderIssueOption?.count ?? 0 },
  ]);

  function buildUrl(values: Record<string, string>): URL {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(values)) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
    if (!('cpage' in values)) url.searchParams.set('cpage', '1');
    return url;
  }

  function syncFilters(next: TransactionFilters): void {
    filters = { ...next };
    selected = {};
  }

  async function navigate(values: Record<string, string>, push = true): Promise<void> {
    const url = buildUrl(values);
    navigationController?.abort();
    navigationController = new AbortController();
    refreshing = true;

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        signal: navigationController.signal,
        headers: { 'X-KiriminAja-Workspace': 'transactions' },
      });
      const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
      const payload = documentHtml.querySelector<HTMLScriptElement>('[data-kiriof-transactions-payload]');
      if (!response.ok || !payload?.textContent) throw new Error('Unable to load transactions.');

      const nextBootstrap = JSON.parse(payload.textContent) as TransactionsBootstrap;
      bootstrap = nextBootstrap;
      syncFilters(nextBootstrap.filters);
      if (push) history.pushState({ kiriofTransactions: true }, '', url);
      document.title = documentHtml.title || document.title;
    } catch (requestError) {
      if ((requestError as Error).name !== 'AbortError') window.location.assign(url);
    } finally {
      refreshing = false;
    }
  }

  function applyFilters(): void {
    void navigate({
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
    void navigate({ key: '', month: '', status: 'all', cod: '', courier: '', print_status: '', search_by: 'wc_order_id' });
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

  function changeScope(value: string): void {
    if (value === 'order-issue') void navigate({ status: 'order-issue' });
    if (value === 'regular') void navigate({ status: 'all' });
  }

  function handlePopState(): void {
    void navigate(Object.fromEntries(new URL(window.location.href).searchParams.entries()), false);
  }

  window.addEventListener('popstate', handlePopState);
  onDestroy(() => {
    navigationController?.abort();
    window.removeEventListener('popstate', handlePopState);
  });
</script>

<div class="kiriof-shadcn kiriof-transactions-app">
  <SettingsToolbar toolbar={bootstrap.toolbar}>
    <div class="kiriof-transactions-toolbar__actions">
      <Button id="kj-print-btn" variant="outline" disabled={refreshing || selectedPrintCount === 0} onclick={printSelected}>
        <IconPrinter data-icon="inline-start" />
        <span>{bootstrap.i18n.print} ({selectedPrintCount} of {selectedCount})</span>
      </Button>
      <Button id="kj-request-pickup-btn" data-kj-action="request-pickup" disabled={refreshing || selectedPickupCount === 0}>
        <span>{bootstrap.i18n.requestPickup} ({selectedPickupCount} of {selectedCount})</span>
      </Button>
    </div>
  </SettingsToolbar>

  <section class="kiriof-transactions-card">
    <div class="kiriof-transactions-filterbar">
      <nav class="kiriof-transactions-scopes" aria-label="Transaction scope">
        <WorkspaceTabs value={scopeValue} tabs={scopeTabs} onChange={changeScope} />
        <div class="kiriof-transactions-list-tools">
          <Select.Root type="single" bind:value={filters.month} disabled={refreshing} onValueChange={() => applyFilters()}>
            <Select.Trigger hideIcon><IconCalendar /><Select.Value>{monthLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
            <Select.Content class="kiriof-shadcn">
              <Select.Item value="all">{bootstrap.i18n.allDates}</Select.Item>
              {#each Object.entries(bootstrap.monthOptions) as [value, label]}
                <Select.Item {value}>{label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
          <Select.Root type="single" value={String(bootstrap.pagination.perPage)} disabled={refreshing} onValueChange={(value: string) => void navigate({ per_page: value || '25' })}>
            <Select.Trigger hideIcon><IconListNumbers /><Select.Value>{bootstrap.pagination.perPage}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
            <Select.Content class="kiriof-shadcn">
              {#each ['10', '25', '50', '100'] as size}<Select.Item value={size}>{size}</Select.Item>{/each}
            </Select.Content>
          </Select.Root>
        </div>
      </nav>
      <form
        class="kiriof-transactions-filterrow"
        onsubmit={(event) => {
          event.preventDefault();
          applyFilters();
        }}
      >
        <InputGroup.Root class="kiriof-transactions-search" data-disabled={refreshing ? 'true' : undefined}>
          <InputGroup.Addon class="kiriof-search-prefix p-0">
            <Select.Root type="single" bind:value={filters.search_by} disabled={refreshing}>
              <Select.Trigger hideIcon class="kiriof-filter-search-by border-0 shadow-none"><Select.Value>{searchByLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
              <Select.Content class="kiriof-shadcn">
                <Select.Item value="wc_order_id">{bootstrap.i18n.orderNumber}</Select.Item>
                <Select.Item value="ka_order_id">{bootstrap.i18n.kaOrderId}</Select.Item>
                <Select.Item value="awb">{bootstrap.i18n.awb}</Select.Item>
              </Select.Content>
            </Select.Root>
          </InputGroup.Addon>
          <InputGroup.Input bind:value={filters.key} placeholder={bootstrap.i18n.search} disabled={refreshing} />
          <InputGroup.Addon class="kiriof-search-suffix" align="inline-end"><IconSearch /></InputGroup.Addon>
        </InputGroup.Root>
        <Select.Root type="single" bind:value={filters.cod} disabled={refreshing}>
          <Select.Trigger hideIcon><IconCash /><Select.Value>{paymentLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPayment}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.cod}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.nonCod}</Select.Item>
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.status} disabled={refreshing}>
          <Select.Trigger hideIcon><IconAdjustmentsHorizontal /><Select.Value>{statusLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            {#each bootstrap.statusOptions as option}
              <Select.Item value={option.value}>{option.label} ({option.count})</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.print_status} disabled={refreshing}>
          <Select.Trigger hideIcon><IconPrinter /><Select.Value>{printLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPrints}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.printed}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.unprinted}</Select.Item>
          </Select.Content>
        </Select.Root>
        <CourierCombobox value={filters.courier} options={courierOptions} placeholder={bootstrap.i18n.allCouriers} disabled={refreshing} onChange={(value) => (filters.courier = value)} />
        <ButtonGroup.Root>
          <Button variant="outline" size="icon" disabled={refreshing} onclick={applyFilters} aria-label={bootstrap.i18n.apply} title={bootstrap.i18n.apply}><IconSearch /></Button>
          <Button variant="outline" size="icon" disabled={refreshing} onclick={clearFilters} aria-label={bootstrap.i18n.clear} title={bootstrap.i18n.clear}><IconX /></Button>
        </ButtonGroup.Root>
      </form>
    </div>

    <div class="kiriof-transactions-meta">
      <span>{bootstrap.pagination.total} {bootstrap.i18n.items}</span>
    </div>

    <div class="kiriof-transactions-tablewrap">
      <Table.Root class="kiriof-transactions-table">
        <Table.Header>
          {#if selectedCount > 0}
            <Table.Row class="kiriof-selection-summary">
              <Table.Head class="is-check"><Checkbox checked={allSelected} indeterminate={!allSelected} onCheckedChange={(checked) => toggleAll(Boolean(checked))} /></Table.Head>
              <Table.Head colspan={6}>{selectedCount} selected</Table.Head>
            </Table.Row>
          {:else}
            <Table.Row>
              <Table.Head class="is-check"><Checkbox checked={allSelected} indeterminate={false} onCheckedChange={(checked) => toggleAll(Boolean(checked))} /></Table.Head>
              <Table.Head>{bootstrap.i18n.order}</Table.Head>
              <Table.Head>{bootstrap.i18n.expedition}</Table.Head>
              <Table.Head>{bootstrap.i18n.airwaybill}</Table.Head>
              <Table.Head>{bootstrap.i18n.route}</Table.Head>
              <Table.Head>{bootstrap.i18n.packages}</Table.Head>
              <Table.Head class="is-actions">{bootstrap.i18n.action}</Table.Head>
            </Table.Row>
          {/if}
        </Table.Header>
        <Table.Body>
          {#if bootstrap.rows.length === 0}
            <Table.Row><Table.Cell colspan={7} class="kiriof-empty-cell">{bootstrap.i18n.notFound}</Table.Cell></Table.Row>
          {:else}
            {#each bootstrap.rows as row (row.id)}
              <Table.Row class={selected[row.kaOrderId] ? 'is-selected' : undefined}>
                <Table.Cell class="is-check">
                  <Checkbox
                    checked={Boolean(selected[row.kaOrderId])}
                    disabled={row.selection.disabled}
                    name="transaction_id[]"
                    value={row.kaOrderId}
                    data-can-pickup={row.selection.canPickup ? '1' : '0'}
                    data-can-print={row.selection.canPrint ? '1' : '0'}
                    aria-label={`Select order ${row.wcOrderId}`}
                    title={row.selection.title}
                    onCheckedChange={(checked) => toggleRow(row, Boolean(checked))}
                  />
                </Table.Cell>
                <Table.Cell>
                  <a class="kiriof-order-link" href={row.wcOrderUrl} target="_blank">#{row.wcOrderId}</a>
                  <strong class="kiriof-row-title">{row.customer.name}</strong>
                  {#if row.customer.phone}<a class="kiriof-row-muted" href={`tel:${row.customer.phone}`}>{row.customer.phone}</a>{/if}
                  <span class="kiriof-row-muted">{row.createdAt}</span>
                </Table.Cell>
                <Table.Cell>
                  <strong class="kiriof-row-title">{row.courier.service}</strong>
                  <div class="kiriof-row-badges">
                    <span class="kiriof-transaction-status {toneClass(row.status.tone)}" title={row.status.deficit ? 'COD settlement requires action' : undefined}>{row.status.label}</span>
                    <span class="kiriof-row-muted">via {row.courier.paymentLabel}</span>
                  </div>
                  <span class="kiriof-print-status {row.printStatus}">{row.printStatus === 'printed' ? bootstrap.i18n.printedLabel : bootstrap.i18n.unprintedLabel}</span>
                </Table.Cell>
                <Table.Cell>
                  <span class="kiriof-row-label">AWB</span>
                  <strong>{row.awb || '—'}</strong>
                  <span class="kiriof-row-label">KA Order ID</span>
                  <code>{row.kaOrderId}</code>
                </Table.Cell>
                <Table.Cell>
                  <strong class="kiriof-row-title">{row.route.origin}</strong>
                  <span class="kiriof-route-arrow">↓ To</span>
                  <strong class="kiriof-row-title">{row.route.destination}</strong>
                  {#each row.route.addressLines as line}<span class="kiriof-row-muted">{line}</span>{/each}
                </Table.Cell>
                <Table.Cell>
                  <span class="kiriof-row-muted">{row.package.weight} g{row.package.quantity > 1 ? ` × ${row.package.quantity}` : ''}</span>
                  <strong class="kiriof-row-title">{currency(row.package.paidShipping)}</strong>
                  <div class="kiriof-fee-pills">
                    {#if row.package.insurance > 0}<span>Ins {currency(row.package.insurance)}</span>{/if}
                    {#if row.package.codFee > 0}<span>COD {currency(row.package.codFee)}</span>{/if}
                    {#if row.package.itemDiscount > 0}<span class="is-discount">{row.package.itemCoupon} −{currency(row.package.itemDiscount)}</span>{/if}
                    {#if row.package.shippingDiscount > 0}<span class="is-discount">{row.package.shippingCoupon} −{currency(row.package.shippingDiscount)}</span>{/if}
                  </div>
                </Table.Cell>
                <Table.Cell class="is-actions">
                  <ButtonGroup.Root class="kiriof-row-actions">
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
                  </ButtonGroup.Root>
                </Table.Cell>
              </Table.Row>
            {/each}
          {/if}
        </Table.Body>
      </Table.Root>
    </div>

    <footer class="kiriof-transactions-pagination">
      <span>{bootstrap.pagination.total} {bootstrap.i18n.items}</span>
      <div>
        <Button variant="outline" size="icon-sm" disabled={refreshing || bootstrap.pagination.page <= 1} onclick={() => void navigate({ cpage: String(bootstrap.pagination.page - 1) })}><IconChevronLeft /></Button>
        <span>{bootstrap.pagination.page} {bootstrap.i18n.pageOf} {bootstrap.pagination.totalPages}</span>
        <Button variant="outline" size="icon-sm" disabled={refreshing || bootstrap.pagination.page >= bootstrap.pagination.totalPages} onclick={() => void navigate({ cpage: String(bootstrap.pagination.page + 1) })}><IconChevronRight /></Button>
      </div>
    </footer>
  </section>
</div>
