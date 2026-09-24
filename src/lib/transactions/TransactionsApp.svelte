<script lang="ts">
  import { onDestroy } from 'svelte';
  import {
    IconAdjustmentsHorizontal,
    IconCalendar,
    IconCash,
    IconCheck,
    IconCircleCheck,
    IconClock,
    IconCopy,
    IconCreditCard,
    IconEye,
    IconMapPin,
    IconPackage,
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
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import DataTableFooter from '../admin-list/DataTableFooter.svelte';
  import CourierCombobox from './CourierCombobox.svelte';
  import { courierImage } from './courier-images';
  import RequestPickupDialog from './RequestPickupDialog.svelte';
  import type { TransactionFilters, TransactionRow, TransactionsBootstrap } from './types';

  let {
    bootstrap: initialBootstrap,
    onNavigate,
  }: {
    bootstrap: TransactionsBootstrap;
    onNavigate?: (href: string | URL) => Promise<void> | void;
  } = $props();
  function initialWorkspace(): TransactionsBootstrap {
    return structuredClone(initialBootstrap);
  }

  function openPickupDialog(): void {
    if (pickupOrderIds.length > 0) pickupDialogOpen = true;
  }

  function scheduleSearch(): void {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 350);
  }

  function applySelectFilter(): void {
    if (!refreshing) applyFilters();
  }
  let bootstrap = $state<TransactionsBootstrap>(initialWorkspace());
  function initialFilters(): TransactionFilters {
    return { ...bootstrap.filters };
  }
  let filters = $state<TransactionFilters>(initialFilters());
  let selected = $state<Record<string, boolean>>({});
  let refreshing = $state(false);
  let searchTimer: number | null = null;
  let pickupDialogOpen = $state(false);
  let copiedValue = $state('');
  let copyTimer: number | null = null;

  const isOrderIssue = $derived(filters.status === 'order-issue');
  const selectedRows = $derived(isOrderIssue ? [] : bootstrap.rows.filter((row) => selected[row.kaOrderId]));
  const selectableRows = $derived(isOrderIssue ? [] : bootstrap.rows.filter((row) => !row.selection.disabled));
  const allSelected = $derived(selectableRows.length > 0 && selectableRows.every((row) => selected[row.kaOrderId]));
  const selectedPickupCount = $derived(selectedRows.filter((row) => row.selection.canPickup).length);
  const selectedPrintCount = $derived(selectedRows.filter((row) => row.selection.canPrint).length);
  const selectedCount = $derived(selectedRows.length);
  const statusLabel = $derived(bootstrap.statusOptions.find((option) => option.value === filters.status)?.label ?? bootstrap.i18n.status);
  const monthLabel = $derived(filters.month ? bootstrap.monthOptions[filters.month] ?? bootstrap.i18n.allDates : bootstrap.i18n.allDates);
  const paymentLabel = $derived(filters.cod === '1' ? bootstrap.i18n.cod : filters.cod === '0' ? bootstrap.i18n.nonCod : bootstrap.i18n.allPayment);
  const printLabel = $derived(filters.print_status === '1' ? bootstrap.i18n.printed : filters.print_status === '0' ? bootstrap.i18n.unprinted : bootstrap.i18n.allPrints);
  const courierOptions = $derived([{ value: '', label: bootstrap.i18n.allCouriers }, ...bootstrap.couriers]);
  const orderIssueOption = $derived(bootstrap.statusOptions.find((option) => option.value === 'order-issue'));
  const visibleStatusOptions = $derived(bootstrap.statusOptions.filter((option) => (isOrderIssue ? option.value === 'order-issue' : option.value !== 'order-issue')));
  const pickupOrderIds = $derived(selectedRows.filter((row) => row.selection.canPickup).map((row) => row.kaOrderId));
  const hasActiveFilters = $derived(
    Boolean(
      filters.key.trim() ||
        filters.month ||
        (filters.status && filters.status !== 'all') ||
        filters.cod ||
        filters.courier ||
        filters.print_status,
    ),
  );
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
    url.searchParams.delete('search_by');
    if (!('cpage' in values)) url.searchParams.set('cpage', '1');
    return url;
  }

  async function navigate(values: Record<string, string>): Promise<void> {
    const url = buildUrl(values);
    refreshing = true;

    try {
      if (onNavigate) await onNavigate(url);
      else window.location.assign(url);
    } finally {
      refreshing = false;
    }
  }

  function applyFilters(): void {
    void navigate({
      key: filters.key,
      month: filters.month === 'all' ? '' : filters.month,
      status: filters.status,
      cod: filters.cod === 'all' ? '' : filters.cod,
      courier: filters.courier === 'all' ? '' : filters.courier,
      print_status: filters.print_status === 'all' ? '' : filters.print_status,
      per_page: String(bootstrap.pagination.perPage),
    });
  }

  function clearFilters(): void {
    void navigate({ key: '', month: '', status: 'all', cod: '', courier: '', print_status: '' });
  }

  function toggleAll(checked: boolean): void {
    selected = Object.fromEntries(selectableRows.map((row) => [row.kaOrderId, checked]));
  }

  function toggleRow(row: TransactionRow, checked: boolean): void {
    selected = { ...selected, [row.kaOrderId]: checked };
  }

  function printSelected(): void {
    if (selectedPrintCount === 0) return;
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

  function formatPhone(phone: string): string {
    const value = phone.trim();
    if (!value) return '';

    const digits = value.replace(/\D/g, '');
    if (!digits) return value;
    if (digits.startsWith('62')) return `+${digits}`;
    if (digits.startsWith('0')) return `+62${digits.slice(1)}`;
    if (digits.startsWith('8')) return `+62${digits}`;
    return `+${digits}`;
  }

  async function copyText(value: string): Promise<void> {
    if (!value) return;

    try {
      await navigator.clipboard.writeText(value);
    } catch {
      const input = document.createElement('textarea');
      input.value = value;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.append(input);
      input.select();
      document.execCommand('copy');
      input.remove();
    }

    copiedValue = value;
    if (copyTimer) window.clearTimeout(copyTimer);
    copyTimer = window.setTimeout(() => {
      copiedValue = '';
    }, 1600);
  }

  function toneClass(tone: TransactionRow['status']['tone']): string {
    return `is-${tone}`;
  }

  function changeScope(value: string): void {
    if (value === 'order-issue') void navigate({ status: 'order-issue' });
    if (value === 'regular') void navigate({ status: 'all' });
  }

  onDestroy(() => {
    if (searchTimer) window.clearTimeout(searchTimer);
    if (copyTimer) window.clearTimeout(copyTimer);
  });
</script>

<div class="kiriof-shadcn kiriof-transactions-app">
  <Toolbar toolbar={bootstrap.toolbar}>
    <div class="kiriof-transactions-toolbar__actions">
      <Button id="kj-print-btn" variant="outline" disabled={refreshing || selectedPrintCount === 0} onclick={printSelected}>
        <IconPrinter data-icon="inline-start" />
        <span>{bootstrap.i18n.print} ({selectedPrintCount} of {selectedCount})</span>
      </Button>
      <Button id="kj-request-pickup-btn" disabled={refreshing || selectedPickupCount === 0} onclick={openPickupDialog}>
        <span>{bootstrap.i18n.requestPickup} ({selectedPickupCount} of {selectedCount})</span>
      </Button>
    </div>
  </Toolbar>

  <section class="kiriof-transactions-card">
    <div class="kiriof-transactions-filterbar">
      <nav class="kiriof-transactions-scopes" aria-label="Transaction scope">
        <WorkspaceTabs value={scopeValue} tabs={scopeTabs} onChange={changeScope} />
        <div class="kiriof-transactions-list-tools">
          <Select.Root type="single" bind:value={filters.month} disabled={refreshing} onValueChange={applySelectFilter}>
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
          <InputGroup.Addon align="inline-start"><IconSearch /></InputGroup.Addon>
          <InputGroup.Input bind:value={filters.key} placeholder={bootstrap.i18n.search} disabled={refreshing} oninput={scheduleSearch} />
        </InputGroup.Root>
        <Select.Root type="single" bind:value={filters.cod} disabled={refreshing} onValueChange={applySelectFilter}>
          <Select.Trigger hideIcon><IconCash /><Select.Value>{paymentLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPayment}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.cod}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.nonCod}</Select.Item>
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.status} disabled={refreshing} onValueChange={applySelectFilter}>
          <Select.Trigger hideIcon><IconAdjustmentsHorizontal /><Select.Value>{statusLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            {#each visibleStatusOptions as option}
              <Select.Item value={option.value}>{option.label} ({option.count})</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filters.print_status} disabled={refreshing} onValueChange={applySelectFilter}>
          <Select.Trigger hideIcon><IconPrinter /><Select.Value>{printLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="all">{bootstrap.i18n.allPrints}</Select.Item>
            <Select.Item value="1">{bootstrap.i18n.printed}</Select.Item>
            <Select.Item value="0">{bootstrap.i18n.unprinted}</Select.Item>
          </Select.Content>
        </Select.Root>
        <CourierCombobox
          value={filters.courier}
          options={courierOptions}
          placeholder={bootstrap.i18n.allCouriers}
          disabled={refreshing}
          onChange={(value) => {
            filters.courier = value;
            applySelectFilter();
          }}
        />
        {#if hasActiveFilters}
          <Button class="kiriof-clear-filters" variant="outline" size="icon" disabled={refreshing} onclick={clearFilters} aria-label={bootstrap.i18n.clear} title={bootstrap.i18n.clear}><IconX /></Button>
        {/if}
      </form>
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
              {#if isOrderIssue}<Table.Head class="is-row-number">#</Table.Head>{:else}<Table.Head class="is-check"><Checkbox checked={allSelected} indeterminate={false} onCheckedChange={(checked) => toggleAll(Boolean(checked))} /></Table.Head>{/if}
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
            {#each bootstrap.rows as row, rowIndex (row.id)}
              <Table.Row class={selected[row.kaOrderId] ? 'is-selected' : undefined}>
                {#if isOrderIssue}
                  <Table.Cell class="is-row-number">{(bootstrap.pagination.page - 1) * bootstrap.pagination.perPage + rowIndex + 1}</Table.Cell>
                {:else}
                  <Table.Cell class="is-check">
                    <Checkbox
                      checked={Boolean(selected[row.kaOrderId])}
                      disabled={!row.selection.canPrint && !row.selection.canPickup}
                      name="transaction_id[]"
                      value={row.kaOrderId}
                      data-can-pickup={row.selection.canPickup ? '1' : '0'}
                      data-can-print={row.selection.canPrint ? '1' : '0'}
                      aria-label={`Select order ${row.wcOrderId}`}
                      title={row.selection.title}
                      onCheckedChange={(checked) => toggleRow(row, Boolean(checked))}
                    />
                  </Table.Cell>
                {/if}
                <Table.Cell>
                  <a class="kiriof-order-link" href={row.wcOrderUrl} target="_blank">#{row.wcOrderId}</a>
                  <strong class="kiriof-row-title">{row.customer.name}</strong>
                  {#if row.customer.phone}<a class="kiriof-customer-phone" href={`tel:${formatPhone(row.customer.phone)}`}>{formatPhone(row.customer.phone)}</a>{/if}
                  <span class="kiriof-row-muted">{row.createdAt}</span>
                </Table.Cell>
                <Table.Cell>
                  <div class="kiriof-courier-summary">
                    {#if courierImage(row.courier.code, row.courier.service)}
                      <img class="kiriof-courier-logo" src={courierImage(row.courier.code, row.courier.service)} alt="" />
                    {/if}
                    <div class="kiriof-courier-summary__content">
                      <strong class="kiriof-row-title">{row.courier.service}</strong>
                      <span class="kiriof-payment-type {row.courier.paymentLabel === 'COD' ? 'is-cod' : 'is-non-cod'}">
                        {#if row.courier.paymentLabel === 'COD'}<IconCash />{:else}<IconCreditCard />{/if}
                        {row.courier.paymentLabel}
                      </span>
                    </div>
                  </div>
                  <div class="kiriof-shipment-states">
                    <span class="kiriof-transaction-status {toneClass(row.status.tone)}" title={row.status.deficit ? 'COD settlement requires action' : undefined}>
                      <IconPackage />
                      {row.status.label}
                    </span>
                    <span class="kiriof-print-status {row.printStatus}">
                      {#if row.printStatus === 'printed'}<IconCircleCheck />{:else}<IconClock />{/if}
                      {row.printStatus === 'printed' ? bootstrap.i18n.printedLabel : bootstrap.i18n.unprintedLabel}
                    </span>
                  </div>
                </Table.Cell>
                <Table.Cell>
                  <div class="kiriof-copy-field">
                    <span class="kiriof-row-label">{bootstrap.i18n.awb}</span>
                    <span class="kiriof-copy-field__value"><strong>{row.awb || '—'}</strong>{#if row.awb}<button type="button" class="kiriof-copy-button" onclick={() => void copyText(row.awb)} title={copiedValue === row.awb ? bootstrap.i18n.copied : bootstrap.i18n.copyAwb} aria-label={copiedValue === row.awb ? bootstrap.i18n.copied : bootstrap.i18n.copyAwb}>{#if copiedValue === row.awb}<IconCheck />{:else}<IconCopy />{/if}</button>{/if}</span>
                  </div>
                  <div class="kiriof-copy-field">
                    <span class="kiriof-row-label">{bootstrap.i18n.kaOrderId}</span>
                    <span class="kiriof-copy-field__value"><code>{row.kaOrderId}</code><button type="button" class="kiriof-copy-button" onclick={() => void copyText(row.kaOrderId)} title={copiedValue === row.kaOrderId ? bootstrap.i18n.copied : bootstrap.i18n.copyKaOrderId} aria-label={copiedValue === row.kaOrderId ? bootstrap.i18n.copied : bootstrap.i18n.copyKaOrderId}>{#if copiedValue === row.kaOrderId}<IconCheck />{:else}<IconCopy />{/if}</button></span>
                  </div>
                </Table.Cell>
                <Table.Cell>
                  <strong class="kiriof-row-title">{row.route.origin}</strong>
                  <span class="kiriof-route-arrow">↓ To</span>
                  <strong class="kiriof-row-title">{row.route.destination}</strong>
                  {#each row.route.addressLines as line}<span class="kiriof-row-muted">{line}</span>{/each}
                </Table.Cell>
                <Table.Cell>
                  <div class="kiriof-package-fees">
                    <div><span>{bootstrap.i18n.weight}</span><strong>{row.package.weight} g{row.package.quantity > 1 ? ` × ${row.package.quantity}` : ''}</strong></div>
                    <div><span>{bootstrap.i18n.shippingCost}</span><strong>{currency(row.package.paidShipping)}</strong></div>
                    {#if row.package.insurance > 0}<div><span>{bootstrap.i18n.insurance}</span><strong>{currency(row.package.insurance)}</strong></div>{/if}
                    {#if row.package.codFee > 0}<div><span>{bootstrap.i18n.codFee}</span><strong>{currency(row.package.codFee)}</strong></div>{/if}
                    {#if row.package.itemDiscount > 0}<div class="is-discount"><span>{row.package.itemCoupon || bootstrap.i18n.itemDiscount}</span><strong>−{currency(row.package.itemDiscount)}</strong></div>{/if}
                    {#if row.package.shippingDiscount > 0}<div class="is-discount"><span>{row.package.shippingCoupon || bootstrap.i18n.shippingDiscount}</span><strong>−{currency(row.package.shippingDiscount)}</strong></div>{/if}
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

    <DataTableFooter
      count={bootstrap.pagination.total}
      countLabel={bootstrap.i18n.items}
      page={bootstrap.pagination.page}
      totalPages={bootstrap.pagination.totalPages}
      pageLabel={bootstrap.i18n.pageOf}
      onPageChange={(page) => void navigate({ cpage: String(page) })}
    />
  </section>
  <RequestPickupDialog
    bind:open={pickupDialogOpen}
    orderIds={pickupOrderIds}
    ajaxUrl={bootstrap.bulk.ajaxUrl}
    nonce={bootstrap.bulk.nonce}
    pickupUrl={bootstrap.bulk.pickupUrl}
    i18n={bootstrap.i18n}
  />
</div>
