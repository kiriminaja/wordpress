<script lang="ts">
  import { onDestroy } from 'svelte';
  import {
    IconAdjustmentsHorizontal,
    IconAlertTriangle,
    IconArrowBackUp,
    IconCalendar,
    IconCash,
    IconCircleCheck,
    IconClock,
    IconCreditCard,
    IconEye,
    IconMapPin,
    IconPackage,
    IconPlane,
    IconPrinter,
    IconRefresh,
    IconSearch,
    IconListNumbers,
    IconChevronDown,
    IconTrash,
    IconTruck,
    IconX,
    IconXboxX,
  } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Select from '$lib/components/ui/select';
  import * as Table from '$lib/components/ui/table';
  import WorkspaceTabs from '$lib/ui/WorkspaceTabs.svelte';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import KiriofCard from '$lib/ui/KiriofCard.svelte';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';
  import CopyableValue from '$lib/ui/CopyableValue.svelte';
  import PrintPreviewDialog from '$lib/ui/PrintPreviewDialog.svelte';
  import AutoRefresh, { AUTO_REFRESH_INTERVALS } from '$lib/ui/AutoRefresh.svelte';
  import DataTableFooter from '../admin-list/DataTableFooter.svelte';
  import CourierCombobox from './CourierCombobox.svelte';
  import { courierImage } from './courier-images';
  import RequestPickupDialog from './RequestPickupDialog.svelte';
  import TransactionActionDialogs, { type TransactionActionDialog } from './TransactionActionDialogs.svelte';
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

  /** Re-run the current list query, preserving every filter + pagination state. */
  function refreshList(): void {
    void navigate({});
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
  let actionDialog = $state<TransactionActionDialog | null>(null);
  let printPreviewOpen = $state(false);
  let printPreviewOrderIds = $state<string[]>([]);

  const isOrderIssue = $derived(filters.status === 'order-issue');
  const selectedRows = $derived(isOrderIssue ? [] : bootstrap.rows.filter((row) => selected[row.kaOrderId]));
  const selectableRows = $derived(isOrderIssue ? [] : bootstrap.rows.filter((row) => !row.selection.disabled));
  const allSelected = $derived(selectableRows.length > 0 && selectableRows.every((row) => selected[row.kaOrderId]));
  const selectedPickupCount = $derived(selectedRows.filter((row) => row.selection.canPickup).length);
  const selectedPrintCount = $derived(selectedRows.filter((row) => row.selection.canPrint).length);
  const selectedCount = $derived(selectedRows.length);
  const statusLabel = $derived(
    filters.status && filters.status !== 'all'
      ? bootstrap.statusOptions.find((option) => option.value === filters.status)?.label ?? bootstrap.i18n.status
      : bootstrap.i18n.status,
  );
  const monthLabel = $derived(filters.month ? bootstrap.monthOptions[filters.month] ?? bootstrap.i18n.allDates : bootstrap.i18n.allDates);
  const paymentLabel = $derived(filters.cod === '1' ? bootstrap.i18n.cod : filters.cod === '0' ? bootstrap.i18n.nonCod : bootstrap.i18n.allPayment);
  const printLabel = $derived(filters.print_status === '1' ? bootstrap.i18n.printed : filters.print_status === '0' ? bootstrap.i18n.unprinted : bootstrap.i18n.allPrints);
  const courierOptions = $derived([{ value: '', label: bootstrap.i18n.allCouriers }, ...bootstrap.couriers]);
  const orderIssueOption = $derived(bootstrap.statusOptions.find((option) => option.value === 'order-issue'));
  const visibleStatusOptions = $derived(bootstrap.statusOptions.filter((option) => option.value !== 'order-issue'));
  const pickupOrderIds = $derived(selectedRows.filter((row) => row.selection.canPickup).map((row) => row.kaOrderId));
  const hasActiveFilters = $derived(
    Boolean(
      filters.key.trim() ||
        (!isOrderIssue && filters.status && filters.status !== 'all') ||
        filters.cod ||
        filters.courier ||
        filters.print_status,
    ),
  );
  const scopeValue = $derived(filters.status === 'order-issue' ? 'order-issue' : 'regular');
  const scopeTabs = $derived([
    { value: 'regular', label: 'Regular Delivery' },
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
    // Reset only the filter-row controls (search, payment, status, print,
    // courier). The tab, date range and rows-per-page are view state owned
    // by other controls, so they are preserved here.
    void navigate({
      key: '',
      month: filters.month,
      status: isOrderIssue ? 'order-issue' : 'all',
      cod: '',
      courier: '',
      print_status: '',
      per_page: String(bootstrap.pagination.perPage),
    });
  }

  function toggleAll(checked: boolean): void {
    selected = Object.fromEntries(selectableRows.map((row) => [row.kaOrderId, checked]));
  }

  function toggleRow(row: TransactionRow, checked: boolean): void {
    selected = { ...selected, [row.kaOrderId]: checked };
  }

  function openPrintPreview(orderIds: string[]): void {
    if (orderIds.length === 0) return;
    printPreviewOrderIds = orderIds;
    printPreviewOpen = true;
  }

  function printSelected(): void {
    openPrintPreview(selectedRows.filter((row) => row.selection.canPrint).map((row) => row.kaOrderId));
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


  function toneClass(tone: TransactionRow['status']['tone']): string {
    return `is-${tone}`;
  }

  /**
   * Package status icon map — each status carries its own icon, mirroring
   * the kaj-shopify-plugin `getLabelProps` icon mapping.
   * Pending/awaiting states (incl. request_pickup) use the clock icon;
   * New uses a package, In Transit a truck, terminal successes a check,
   * returns/warnings an arrow, danger an X.
   */
  function statusIcon(tone: TransactionRow['status']['tone'], deficit: boolean, status: TransactionRow['status']) {
    if (deficit) return IconAlertTriangle;
    if (status.label === 'Request Pickup') return IconClock;
    switch (tone) {
      case 'success':
        return IconCircleCheck;
      case 'primary':
        return IconPackage;
      case 'teal':
        return IconTruck;
      case 'warning':
        return IconArrowBackUp;
      case 'danger':
        return IconXboxX;
      default:
        return IconPackage;
    }
  }

  function changeScope(value: string): void {
    if (value === 'order-issue') void navigate({ status: 'order-issue' });
    if (value === 'regular') void navigate({ status: 'all' });
  }

  onDestroy(() => {
    if (searchTimer) window.clearTimeout(searchTimer);
  });
</script>

<div class="kiriof-shadcn kiriof-admin-list-app kiriof-transactions-app">
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

  <KiriofCard class="kiriof-transactions-card">
    <div class="kiriof-admin-list-filterbar kiriof-transactions-filterbar">
      <nav class="kiriof-admin-list-scopes kiriof-transactions-scopes" aria-label="Transaction scope">
        <WorkspaceTabs value={scopeValue} tabs={scopeTabs} onChange={changeScope} />
        <div class="kiriof-admin-list-tools kiriof-transactions-list-tools">
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
          <AutoRefresh
            storageKey="kiriof-transactions-refresh-interval"
            loading={refreshing}
            disabled={pickupDialogOpen || actionDialog !== null}
            hint={bootstrap.i18n.autoRefresh}
            options={AUTO_REFRESH_INTERVALS.map((option) => ({ ...option, label: bootstrap.i18n.refreshLabels[String(option.value)] ?? option.label }))}
            onRefresh={refreshList}
          />
        </div>
      </nav>
      <form
        class="kiriof-transactions-filterrow"
        class:is-order-issue={isOrderIssue}
        onsubmit={(event) => {
          event.preventDefault();
          applyFilters();
        }}
      >
        <InputGroup.Root class="kiriof-admin-list-search kiriof-transactions-search" data-disabled={refreshing ? 'true' : undefined}>
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
        {#if !isOrderIssue}
          <Select.Root type="single" bind:value={filters.status} disabled={refreshing} onValueChange={applySelectFilter}>
            <Select.Trigger hideIcon><IconAdjustmentsHorizontal /><Select.Value>{statusLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
            <Select.Content class="kiriof-shadcn">
              {#each visibleStatusOptions as option}
                <Select.Item value={option.value}>{option.label} ({option.count})</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        {/if}
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
          <ActionTooltip label={bootstrap.i18n.clear} disabled={refreshing}><Button class="kiriof-clear-filters" variant="outline" size="icon" disabled={refreshing} onclick={clearFilters} aria-label={bootstrap.i18n.clear}><IconX /></Button></ActionTooltip>
        {/if}
      </form>
    </div>

    <div class="kiriof-admin-list-tablewrap kiriof-transactions-tablewrap">
      <Table.Root class="kiriof-admin-list-table kiriof-transactions-table">
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
                    <ActionTooltip label={row.selection.title} disabled={!row.selection.title}>
                      <Checkbox
                        checked={Boolean(selected[row.kaOrderId])}
                        disabled={!row.selection.canPrint && !row.selection.canPickup}
                        name="transaction_id[]"
                        value={row.kaOrderId}
                        data-can-pickup={row.selection.canPickup ? '1' : '0'}
                        data-can-print={row.selection.canPrint ? '1' : '0'}
                        aria-label={`Select order ${row.wcOrderId}`}
                        onCheckedChange={(checked) => toggleRow(row, Boolean(checked))}
                      />
                    </ActionTooltip>
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
                    {#if row.status.deficit}
                      {@const DeficitIcon = statusIcon(row.status.tone, true, row.status)}
                      <ActionTooltip label="COD settlement requires action"><span class="kiriof-transaction-status {toneClass(row.status.tone)} kiriof-badge--strong"><DeficitIcon />{row.status.label}</span></ActionTooltip>
                    {:else}
                      {@const ShipmentIcon = statusIcon(row.status.tone, false, row.status)}
                      <span class="kiriof-transaction-status {toneClass(row.status.tone)}"><ShipmentIcon />{row.status.label}</span>
                    {/if}
                    {#if row.printStatus === 'unprinted'}
                      <ActionTooltip label={bootstrap.i18n.unprintedLabel}>
                        <span class="kiriof-print-status {row.printStatus}" aria-label={bootstrap.i18n.unprintedLabel}><IconPrinter />{bootstrap.i18n.unprintedLabel}</span>
                      </ActionTooltip>
                    {/if}
                  </div>
                </Table.Cell>
                <Table.Cell>
                  <CopyableValue label={bootstrap.i18n.awb} value={row.awb} copyLabel={bootstrap.i18n.copyAwb} copiedLabel={bootstrap.i18n.copied} />
                  <CopyableValue label={bootstrap.i18n.kaOrderId} value={row.kaOrderId} copyLabel={bootstrap.i18n.copyKaOrderId} copiedLabel={bootstrap.i18n.copied} />
                </Table.Cell>
                <Table.Cell>
                  <strong class="kiriof-row-title">{row.route.origin}</strong>
                  <span class="kiriof-route-arrow">↓ To</span>
                  <strong class="kiriof-row-title">{row.route.destination}</strong>
                  {#each row.route.addressLines as line}<span class="kiriof-row-muted">{line}</span>{/each}
                </Table.Cell>
                <Table.Cell>
                  <div class="kiriof-package-fees">
                    <div><span>{bootstrap.i18n.weight}:</span><strong>{row.package.weight} g{row.package.quantity > 1 ? ` × ${row.package.quantity}` : ''}</strong></div>
                    {#if Math.abs(row.package.actualShipping - row.package.paidShipping) > 0.01}
                      <div><span>{bootstrap.i18n.actualShipping}:</span><strong>{currency(row.package.actualShipping)}</strong></div>
                      {#if row.package.shippingDiscount > 0}<div class="is-discount"><span>{row.package.shippingCoupon || bootstrap.i18n.shippingDiscount}:</span><strong>−{currency(row.package.shippingDiscount)}</strong></div>{/if}
                      <div><span>{bootstrap.i18n.shippingCost}:</span><strong>{currency(row.package.paidShipping)}</strong></div>
                    {:else}
                      <div><span>{bootstrap.i18n.shippingCost}:</span><strong>{currency(row.package.paidShipping)}</strong></div>
                    {/if}
                    {#if row.package.insurance > 0}<div><span>{bootstrap.i18n.insurance}:</span><strong>{currency(row.package.insurance)}</strong></div>{/if}
                    {#if row.package.codFee > 0}<div><span>{bootstrap.i18n.codFee}:</span><strong>{currency(row.package.codFee)}</strong></div>{/if}
                    {#if row.package.codValue > 0}<div><span>{bootstrap.i18n.cod}:</span><strong>{currency(row.package.codValue)}</strong></div>{/if}
                    {#if row.package.itemDiscount > 0}<div class="is-discount"><span>{row.package.itemCoupon || bootstrap.i18n.itemDiscount}:</span><strong>−{currency(row.package.itemDiscount)}</strong></div>{/if}
                  </div>
                </Table.Cell>
                <Table.Cell class="is-actions">
                  <div class="kiriof-row-actions">
                    {#if row.actions.preview}
                      <ActionTooltip label={bootstrap.i18n.detail}><Button variant="outline" size="icon-sm" href={row.detailUrl} aria-label={bootstrap.i18n.detail}><IconEye /></Button></ActionTooltip>
                    {/if}
                    {#if row.actions.changeOrigin}
                      <ActionTooltip label={bootstrap.i18n.changeOrigin}><Button variant="outline" size="icon-sm" onclick={() => (actionDialog = { kind: 'origin', data: row.actionData })} aria-label={bootstrap.i18n.changeOrigin}><IconMapPin /></Button></ActionTooltip>
                    {/if}
                    {#if row.actions.adjustDeficit}
                      <ActionTooltip label={bootstrap.i18n.adjustDeficit}><Button variant="outline" size="icon-sm" onclick={() => (actionDialog = { kind: 'adjust-deficit', data: row.actionData })} aria-label={bootstrap.i18n.adjustDeficit}><IconRefresh /></Button></ActionTooltip>
                      <ActionTooltip label={bootstrap.i18n.cancel}><Button variant="destructive" size="icon-sm" onclick={() => (actionDialog = { kind: 'cancel-deficit', data: row.actionData })} aria-label={bootstrap.i18n.cancel}><IconTrash /></Button></ActionTooltip>
                    {:else}
                      {#if row.actions.print}<ActionTooltip label={bootstrap.i18n.print}><Button variant="outline" size="icon-sm" onclick={() => openPrintPreview([row.kaOrderId])} aria-label={bootstrap.i18n.print}><IconPrinter /></Button></ActionTooltip>{/if}
                      {#if row.actions.cancel}<ActionTooltip label={bootstrap.i18n.cancel}><Button variant="destructive" size="icon-sm" onclick={() => (actionDialog = { kind: 'cancel', data: row.actionData })} aria-label={bootstrap.i18n.cancel}><IconTrash /></Button></ActionTooltip>{/if}
                    {/if}
                  </div>
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
  </KiriofCard>
  <RequestPickupDialog
    bind:open={pickupDialogOpen}
    orderIds={pickupOrderIds}
    ajaxUrl={bootstrap.bulk.ajaxUrl}
    nonce={bootstrap.bulk.nonce}
    pickupUrl={bootstrap.bulk.pickupUrl}
    i18n={bootstrap.i18n}
  />
  <TransactionActionDialogs bind:action={actionDialog} locations={bootstrap.shipmentLocations} locationsUrl={bootstrap.locationsUrl} ajaxUrl={bootstrap.bulk.ajaxUrl} i18n={bootstrap.i18n} onComplete={refreshList} />
  <PrintPreviewDialog bind:open={printPreviewOpen} orderIds={printPreviewOrderIds} ajaxUrl={bootstrap.bulk.ajaxUrl} nonce={bootstrap.bulk.printPreviewNonce} i18n={bootstrap.i18n} />
</div>
