<script lang="ts">
  import { onDestroy } from 'svelte';
  import { IconBuildingBank, IconCalendar, IconCalendarClock, IconChevronDown, IconCircleCheck, IconClock, IconCreditCardPay, IconEye, IconQrcode, IconSearch } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Select from '$lib/components/ui/select';
  import * as Table from '$lib/components/ui/table';
  import DataTableFooter from '../admin-list/DataTableFooter.svelte';
  import StatusBadge from '../admin-list/StatusBadge.svelte';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import KiriofCard from '$lib/ui/KiriofCard.svelte';
  import WorkspaceTabs from '$lib/ui/WorkspaceTabs.svelte';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';
  import AutoRefresh, { AUTO_REFRESH_INTERVALS } from '$lib/ui/AutoRefresh.svelte';
  import PaymentScheduleDialog from './PaymentScheduleDialog.svelte';
  import ScanToPayDialog from './ScanToPayDialog.svelte';
  import type { PaymentsBootstrap, PaymentRow } from './types';

  let {
    initialBootstrap,
    onNavigate,
  }: {
    initialBootstrap: PaymentsBootstrap;
    onNavigate?: (href: string | URL) => Promise<void> | void;
  } = $props();
  function initialWorkspace(): PaymentsBootstrap {
    return structuredClone(initialBootstrap);
  }
  let bootstrap = $state<PaymentsBootstrap>(initialWorkspace());
  let search = $state('');
  let month = $state('all');

  $effect(() => {
    search = bootstrap.filters.key;
    month = bootstrap.filters.month || 'all';
  });
  let refreshing = $state(false);
  let searchTimer: number | null = null;
  let scheduleDialogOpen = $state(false);
  let schedulePickupNumber = $state('');
  let paymentDialogOpen = $state(false);
  let paymentPickupNumber = $state('');

  $effect(() => {
    const params = new URLSearchParams(window.location.search);
    const number = params.get('pickup_number');
    const open = params.get('open_payment');
    if (!number || (open !== '1' && open !== 'true')) return;
    params.delete('pickup_number');
    params.delete('open_payment');
    window.history.replaceState(null, '', `${window.location.pathname}${params.size ? `?${params}` : ''}${window.location.hash}`);
    if (bootstrap.rows.some((row) => row.pickupNumber === number && row.actions.some((action) => action.type === 'pay'))) {
      paymentPickupNumber = number;
      paymentDialogOpen = true;
    }
  });

  const currentStatus = $derived(bootstrap.filters.status || 'all');
  const paymentTabs = $derived(bootstrap.statusTabs.map((tab) => ({ ...tab, value: tab.value || 'all' })));
  const monthLabel = $derived(month !== 'all' ? bootstrap.monthOptions[month] ?? bootstrap.i18n.allDates : bootstrap.i18n.allDates);

  function buildUrl(values: Record<string, string>): URL {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(values)) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
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
    void navigate({ key: search, month: month === 'all' ? '' : month });
  }

  function changeMonth(value: string): void {
    month = value || 'all';
    if (!refreshing) applyFilters();
  }

  function changeStatus(status: string): void {
    if (!refreshing) void navigate({ status: status === 'all' ? '' : status });
  }

  function scheduleSearch(): void {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 350);
  }

  /** Re-run the current list query, preserving every filter + pagination state. */
  function refreshList(): void {
    void navigate({});
  }

  function actionIcon(type: PaymentRow['actions'][number]['type']): typeof IconEye {
    if (type === 'pay') return IconCreditCardPay;
    if (type === 'reschedule') return IconCalendarClock;
    return IconEye;
  }

  onDestroy(() => {
    if (searchTimer) window.clearTimeout(searchTimer);
  });
</script>

<div class="kiriof-shadcn kiriof-admin-list-app kiriof-payments-app">
  <Toolbar toolbar={bootstrap.toolbar} />

  <KiriofCard class="kiriof-payments-card">
    <div class="kiriof-admin-list-filterbar kiriof-payments-filterbar">
      <div class="kiriof-admin-list-scopes kiriof-payments-scopes">
        <nav aria-label="Payment status">
          <WorkspaceTabs value={currentStatus} tabs={paymentTabs} onChange={changeStatus} />
        </nav>
        <div class="kiriof-admin-list-tools">
          <form class="kiriof-payments-filterrow" onsubmit={(event) => { event.preventDefault(); applyFilters(); }}>
            <label class="sr-only" for="kiriof-svelte-payment-search">{bootstrap.i18n.search}</label>
            <InputGroup.Root class="kiriof-admin-list-search" data-disabled={refreshing ? 'true' : undefined}>
              <InputGroup.Addon align="inline-start"><IconSearch aria-hidden="true" /></InputGroup.Addon>
              <InputGroup.Input id="kiriof-svelte-payment-search" type="search" bind:value={search} placeholder={bootstrap.i18n.search} disabled={refreshing} oninput={scheduleSearch} />
            </InputGroup.Root>
          </form>
          <Select.Root type="single" value={month} disabled={refreshing} onValueChange={changeMonth}>
            <Select.Trigger hideIcon><IconCalendar /><Select.Value>{monthLabel}</Select.Value><IconChevronDown class="kiriof-select-chevron" /></Select.Trigger>
            <Select.Content class="kiriof-shadcn">
              <Select.Item value="all">{bootstrap.i18n.allDates}</Select.Item>
              {#each Object.entries(bootstrap.monthOptions) as [value, label]}
                <Select.Item {value}>{label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
          <AutoRefresh
            storageKey="kiriof-payments-refresh-interval"
            loading={refreshing}
            hint={bootstrap.i18n.autoRefresh}
            options={AUTO_REFRESH_INTERVALS.map((option) => ({ ...option, label: bootstrap.i18n.refreshLabels[String(option.value)] ?? option.label }))}
            onRefresh={refreshList}
          />
        </div>
      </div>
    </div>

    <div class="kiriof-admin-list-tablewrap kiriof-payments-table-wrap" aria-busy={refreshing}>
      <Table.Root class="kiriof-admin-list-table kiriof-payments-table">
        <Table.Header>
          <Table.Row>
            <Table.Head>{bootstrap.i18n.no}</Table.Head>
            <Table.Head>{bootstrap.i18n.pickupNumber}</Table.Head>
            <Table.Head>{bootstrap.i18n.schedule}</Table.Head>
            <Table.Head>{bootstrap.i18n.fees}</Table.Head>
            <Table.Head>{bootstrap.i18n.orders}</Table.Head>
            <Table.Head>{bootstrap.i18n.paymentMethod}</Table.Head>
            <Table.Head>{bootstrap.i18n.paymentStatus}</Table.Head>
            <Table.Head class="text-right">{bootstrap.i18n.action}</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#if bootstrap.rows.length === 0}
            <Table.Row><Table.Cell colspan={8} class="kiriof-empty-cell">{bootstrap.i18n.empty}</Table.Cell></Table.Row>
          {:else}
            {#each bootstrap.rows as row (row.pickupNumber)}
              <Table.Row>
                <Table.Cell><strong>{row.number}</strong></Table.Cell>
                <Table.Cell><strong>{row.pickupNumber}</strong><small>{bootstrap.i18n.requested}: {row.requestedAt}</small></Table.Cell>
                <Table.Cell>{row.schedule}</Table.Cell>
                <Table.Cell><strong>{row.fees}</strong></Table.Cell>
                <Table.Cell>{row.orders} {bootstrap.i18n.order}</Table.Cell>
                <Table.Cell><StatusBadge label={row.method} tone={row.method === 'QRIS' ? 'info' : 'neutral'} icon={row.method === 'QRIS' ? IconQrcode : IconBuildingBank} /></Table.Cell>
                <Table.Cell><StatusBadge label={row.status === 'paid' ? bootstrap.statusTabs[2].label : bootstrap.statusTabs[1].label} tone={row.status === 'paid' ? 'success' : 'warning'} icon={row.status === 'paid' ? IconCircleCheck : IconClock} /></Table.Cell>
                <Table.Cell class="text-right">
                  <div class="kiriof-row-actions">
                    {#each row.actions as action}
                      {@const ActionIcon = actionIcon(action.type)}
                      {#if action.type === 'details'}
                        <ActionTooltip label={action.label}><Button variant="outline" size="icon" href={action.href} aria-label={action.label}><ActionIcon /></Button></ActionTooltip>
                      {:else if action.type === 'reschedule'}
                        <ActionTooltip label={action.label}><Button variant="outline" size="icon" type="button" onclick={() => { schedulePickupNumber = row.pickupNumber; scheduleDialogOpen = true; }} aria-label={action.label}><ActionIcon /></Button></ActionTooltip>
                      {:else}
                        <ActionTooltip label={action.label}><Button variant="outline" size="icon" type="button" onclick={() => { paymentPickupNumber = row.pickupNumber; paymentDialogOpen = true; }} aria-label={action.label}><ActionIcon /></Button></ActionTooltip>
                      {/if}
                    {/each}
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
  <PaymentScheduleDialog bind:open={scheduleDialogOpen} pickupNumber={schedulePickupNumber} ajaxUrl={bootstrap.ajax.url} nonce={bootstrap.ajax.nonce} i18n={bootstrap.modals} onComplete={refreshList} />
  <ScanToPayDialog bind:open={paymentDialogOpen} pickupNumber={paymentPickupNumber} i18n={bootstrap.modals} />
</div>
