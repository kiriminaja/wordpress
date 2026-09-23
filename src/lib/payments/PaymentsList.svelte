<script lang="ts">
  import { onDestroy } from 'svelte';
  import { IconCalendar, IconCalendarClock, IconCreditCardPay, IconEye, IconRefresh, IconSearch } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Select from '$lib/components/ui/select';
  import * as Table from '$lib/components/ui/table';
  import ListPagination from '../admin-list/ListPagination.svelte';
  import StatusBadge from '../admin-list/StatusBadge.svelte';
  import StatusTabs from '../admin-list/StatusTabs.svelte';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import type { PaymentsBootstrap, PaymentRow } from './types';

  let { initialBootstrap }: { initialBootstrap: PaymentsBootstrap } = $props();
  function initialWorkspace(): PaymentsBootstrap {
    return structuredClone(initialBootstrap);
  }
  let bootstrap = $state<PaymentsBootstrap>(initialWorkspace());
  let search = $state('');
  let month = $state('');

  $effect(() => {
    search = bootstrap.filters.key;
    month = bootstrap.filters.month;
  });
  let refreshing = $state(false);
  let controller: AbortController | null = null;
  let searchTimer: number | null = null;

  const currentStatus = $derived(bootstrap.filters.status === 'all' ? '' : bootstrap.filters.status);
  const monthLabel = $derived(month ? bootstrap.monthOptions[month] ?? bootstrap.i18n.allDates : bootstrap.i18n.allDates);

  function buildUrl(values: Record<string, string>): URL {
    const url = new URL(window.location.href);
    for (const [key, value] of Object.entries(values)) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
    if (!('cpage' in values)) url.searchParams.set('cpage', '1');
    return url;
  }

  async function navigate(values: Record<string, string>, push = true): Promise<void> {
    const url = buildUrl(values);
    controller?.abort();
    controller = new AbortController();
    refreshing = true;
    try {
      const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
      const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
      const nextPayload = documentHtml.querySelector<HTMLScriptElement>('[data-kiriof-payments-payload]');
      if (!response.ok || !nextPayload?.textContent) throw new Error('Unable to load payments.');
      bootstrap = JSON.parse(nextPayload.textContent) as PaymentsBootstrap;
      search = bootstrap.filters.key;
      month = bootstrap.filters.month;
      if (push) history.pushState({ kiriofPayments: true }, '', url);
      document.title = documentHtml.title || document.title;
    } catch (error) {
      if ((error as Error).name !== 'AbortError') window.location.assign(url);
    } finally {
      refreshing = false;
    }
  }

  function applyFilters(): void {
    void navigate({ key: search, month });
  }

  function scheduleSearch(): void {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 350);
  }

  function actionClass(type: PaymentRow['actions'][number]['type']): string {
    if (type === 'pay') return 'kiriof-payment-button';
    if (type === 'reschedule') return 'kiriof-reschedule-button';
    return '';
  }

  function actionIcon(type: PaymentRow['actions'][number]['type']): typeof IconEye {
    if (type === 'pay') return IconCreditCardPay;
    if (type === 'reschedule') return IconCalendarClock;
    return IconEye;
  }

  function handlePopState(): void {
    void navigate(Object.fromEntries(new URL(window.location.href).searchParams.entries()), false);
  }

  window.addEventListener('popstate', handlePopState);
  onDestroy(() => {
    controller?.abort();
    if (searchTimer) window.clearTimeout(searchTimer);
    window.removeEventListener('popstate', handlePopState);
  });
</script>

<div class="kiriof-shadcn kiriof-payments-app">
  <Toolbar toolbar={bootstrap.toolbar} />

  <section class="kiriof-payments-card">
    <div class="kiriof-payments-filterbar">
      <StatusTabs tabs={bootstrap.statusTabs} value={currentStatus} onChange={(status) => void navigate({ status })} />
      <form class="kiriof-payments-search" onsubmit={(event) => { event.preventDefault(); applyFilters(); }}>
        <label class="sr-only" for="kiriof-svelte-payment-search">{bootstrap.i18n.search}</label>
        <InputGroup.Root>
          <InputGroup.Addon><IconSearch aria-hidden="true" /></InputGroup.Addon>
          <InputGroup.Input id="kiriof-svelte-payment-search" type="search" bind:value={search} placeholder={bootstrap.i18n.search} disabled={refreshing} oninput={scheduleSearch} />
        </InputGroup.Root>
      </form>
    </div>

    <div class="kiriof-payments-toolbar">
      <div class="kiriof-payments-month-filter">
        <Select.Root type="single" bind:value={month} disabled={refreshing} onValueChange={applyFilters}>
          <Select.Trigger hideIcon><IconCalendar /><Select.Value>{monthLabel}</Select.Value></Select.Trigger>
          <Select.Content class="kiriof-shadcn">
            <Select.Item value="">{bootstrap.i18n.allDates}</Select.Item>
            {#each Object.entries(bootstrap.monthOptions) as [value, label]}
              <Select.Item {value}>{label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Button variant="outline" disabled={refreshing} onclick={applyFilters}>
          <IconRefresh data-icon="inline-start" />{bootstrap.i18n.apply}
        </Button>
      </div>
      <ListPagination page={bootstrap.pagination.page} totalPages={bootstrap.pagination.totalPages} label={bootstrap.i18n.pageOf} onChange={(page) => void navigate({ cpage: String(page) })} />
    </div>

    <div class="kiriof-payments-table-wrap" aria-busy={refreshing}>
      <Table.Root>
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
                <Table.Cell><StatusBadge label={row.method} tone={row.method === 'QRIS' ? 'info' : 'neutral'} /></Table.Cell>
                <Table.Cell><StatusBadge label={row.status === 'paid' ? bootstrap.statusTabs[2].label : bootstrap.statusTabs[1].label} tone={row.status === 'paid' ? 'success' : 'warning'} /></Table.Cell>
                <Table.Cell class="text-right">
                  <div class="kiriof-row-actions">
                    {#each row.actions as action}
                      {@const ActionIcon = actionIcon(action.type)}
                      {#if action.type === 'details'}
                        <Button variant="outline" size="icon-sm" href={action.href} title={action.label} aria-label={action.label}><ActionIcon /></Button>
                      {:else}
                        <Button variant="outline" size="icon-sm" class={actionClass(action.type)} type="button" data-pickup-number={row.pickupNumber} title={action.label} aria-label={action.label}><ActionIcon /></Button>
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

    <div class="kiriof-payments-toolbar kiriof-payments-toolbar--bottom">
      <span></span>
      <ListPagination page={bootstrap.pagination.page} totalPages={bootstrap.pagination.totalPages} label={bootstrap.i18n.pageOf} onChange={(page) => void navigate({ cpage: String(page) })} />
    </div>
  </section>
</div>
