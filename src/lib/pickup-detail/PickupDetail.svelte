<script lang="ts">
  import { IconCash, IconCircleCheck, IconClock, IconCreditCard, IconEye, IconMapPin, IconPackage, IconPrinter, IconTruck, IconXboxX } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';
  import StatusBadge from '../admin-list/StatusBadge.svelte';
  import { courierImage } from '../transactions/courier-images';
  import type { PickupDetailBootstrap } from './types';

  let {
    bootstrap,
    onNavigate,
  }: {
    bootstrap: PickupDetailBootstrap;
    onNavigate?: (href: string | URL) => Promise<void> | void;
  } = $props();

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

</script>

<div class="kiriof-shadcn kiriof-admin-list-app kiriof-pickup-detail-app">
  <Toolbar toolbar={bootstrap.toolbar} onNavigate={(href) => void onNavigate?.(href)}>
    {#if bootstrap.printAllUrl}
      <Button class="kiriof-pickup-detail-print-all" href={bootstrap.printAllUrl} target="_blank" rel="noopener noreferrer">
        <IconPrinter class="kiriof-pickup-detail-print-all__icon" aria-hidden="true" />
        <span>{bootstrap.i18n.printAll}</span>
      </Button>
    {/if}
  </Toolbar>

  {#if bootstrap.printError}
    <div class="kiriof-pickup-detail-alert" role="alert">{bootstrap.printError}</div>
  {/if}

  <section class="kiriof-summary-grid" aria-label="Pickup summary">
    {#each bootstrap.summary as item}
      <Card.Root size="sm" class="kiriof-pickup-summary-card">
        <Card.Content>
          <strong>{item.value}</strong>
          <span>{item.label}</span>
        </Card.Content>
      </Card.Root>
    {/each}
  </section>

  <section class="kiriof-admin-list-card kiriof-pickup-detail-card">
    <div class="kiriof-pickup-detail-filterbar">
      <div>
        <strong>{bootstrap.toolbar.title}</strong>
        <span><IconMapPin />{bootstrap.i18n.pickup}: {bootstrap.schedule}</span>
      </div>
    </div>

    <div class="kiriof-admin-list-tablewrap">
      <Table.Root class="kiriof-admin-list-table kiriof-pickup-detail-table">
        <Table.Header>
          <Table.Row>
            <Table.Head class="is-row-number">#</Table.Head>
            <Table.Head>{bootstrap.i18n.order}</Table.Head>
            <Table.Head>{bootstrap.i18n.courier}</Table.Head>
            <Table.Head>{bootstrap.i18n.airwaybill}</Table.Head>
            <Table.Head>{bootstrap.i18n.route}</Table.Head>
            <Table.Head>{bootstrap.i18n.packages}</Table.Head>
            <Table.Head>{bootstrap.i18n.codValue}</Table.Head>
            <Table.Head>{bootstrap.i18n.status}</Table.Head>
            <Table.Head class="is-actions">{bootstrap.i18n.action}</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#if bootstrap.rows.length === 0}
            <Table.Row><Table.Cell colspan={9} class="kiriof-empty-cell">{bootstrap.i18n.empty}</Table.Cell></Table.Row>
          {/if}
          {#each bootstrap.rows as row (row.orderId)}
            <Table.Row>
              <Table.Cell class="is-row-number">{row.number}</Table.Cell>
              <Table.Cell>
                <a class="kiriof-order-link" href={row.orderUrl} target="_blank" rel="noopener noreferrer"><strong>{row.orderId}</strong></a>
                <strong class="kiriof-row-title">{row.customer.name || '—'}</strong>
                {#if row.customer.phone}<a class="kiriof-customer-phone" href={`tel:${formatPhone(row.customer.phone)}`}>{formatPhone(row.customer.phone)}</a>{/if}
                <span class="kiriof-payment-type {row.customer.paymentLabel === 'COD' ? 'is-cod' : 'is-non-cod'}">
                  {#if row.customer.paymentLabel === 'COD'}<IconCash />{:else}<IconCreditCard />{/if}
                  {row.customer.paymentLabel}
                </span>
              </Table.Cell>
              <Table.Cell>
                <div class="kiriof-courier-summary">
                  {#if courierImage(row.courier.code, row.courier.service)}<img class="kiriof-courier-logo" src={courierImage(row.courier.code, row.courier.service)} alt="" />{/if}
                  <div class="kiriof-courier-summary__content"><strong class="kiriof-row-title">{row.courier.service}</strong><span class="kiriof-row-muted">{bootstrap.i18n.pickup}: {bootstrap.schedule}</span></div>
                </div>
              </Table.Cell>
              <Table.Cell>
                <div class="kiriof-copy-field"><span class="kiriof-row-label">AWB</span><span class="kiriof-copy-field__value"><strong>{row.awb || '—'}</strong></span></div>
                <div class="kiriof-copy-field"><span class="kiriof-row-label">Order ID</span><span class="kiriof-copy-field__value"><code>{row.orderId}</code></span></div>
              </Table.Cell>
              <Table.Cell>
                <strong class="kiriof-row-title">{row.route.recipient || '—'}</strong>
                <span class="kiriof-row-muted">{row.route.origin} →</span>
                {#each row.route.addressLines as line}<span class="kiriof-row-muted">{line}</span>{/each}
              </Table.Cell>
              <Table.Cell>
                <div class="kiriof-package-fees">
                  <div><span>{bootstrap.i18n.weight}:</span><strong>{row.package.weight > 0 ? `${row.package.weight} g` : '—'}</strong></div>
                  <div><span>{bootstrap.i18n.shipping}:</span><strong>{currency(row.package.shipping)}</strong></div>
                  {#if row.package.insurance > 0}<div><span>{bootstrap.i18n.insurance}:</span><strong>{currency(row.package.insurance)}</strong></div>{/if}
                  {#if row.package.codFee > 0}<div><span>{bootstrap.i18n.codFee}:</span><strong>{currency(row.package.codFee)}</strong></div>{/if}
                  {#if row.package.discount > 0}<div class="is-discount"><span>{bootstrap.i18n.discount}:</span><strong>−{currency(row.package.discount)}</strong></div>{/if}
                  <div class="is-total"><span>{bootstrap.i18n.total}:</span><strong>{currency(row.package.total)}</strong></div>
                </div>
              </Table.Cell>
              <Table.Cell><strong>{currency(row.package.codValue)}</strong></Table.Cell>
              <Table.Cell><StatusBadge label={row.status.label} tone={row.status.tone} icon={row.status.tone === 'success' ? IconCircleCheck : row.status.tone === 'warning' ? IconClock : row.status.tone === 'primary' ? IconPackage : row.status.tone === 'teal' ? IconTruck : row.status.tone === 'info' ? IconTruck : IconPackage} /></Table.Cell>
              <Table.Cell class="is-actions">
                <div class="kiriof-row-actions">
                  {#if row.printUrl}<ActionTooltip label={bootstrap.i18n.print}><Button href={row.printUrl} target="_blank" rel="noopener noreferrer" variant="outline" size="icon-sm" aria-label={bootstrap.i18n.print}><IconPrinter /></Button></ActionTooltip>{/if}
                  <ActionTooltip label={bootstrap.i18n.detail}><Button href={row.orderUrl} target="_blank" rel="noopener noreferrer" variant="outline" size="icon-sm" aria-label={bootstrap.i18n.detail}><IconEye /></Button></ActionTooltip>
                </div>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </section>
</div>
