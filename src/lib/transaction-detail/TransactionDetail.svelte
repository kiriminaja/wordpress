<script lang="ts">
  import { onMount } from 'svelte';
  import {
    IconBox,
    IconCheck,
    IconCircleCheck,
    IconExternalLink,
    IconMapPin,
    IconPackage,
    IconPhone,
    IconPrinter,
    IconRefresh,
    IconRoute,
    IconTruck,
    IconUser,
    IconX,
  } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import StatusBadge from '$lib/admin-list/StatusBadge.svelte';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { courierImage } from '$lib/transactions/courier-images';
  import type { TrackingResponse, TransactionDetailBootstrap } from './types';

  let { bootstrap }: { bootstrap: TransactionDetailBootstrap } = $props();
  let transaction = $derived(bootstrap.transaction);
  let i18n = $derived(bootstrap.i18n);
  let tracking = $state<TrackingResponse | null>(null);
  let trackingError = $state('');
  let loadingTracking = $state(false);

  function currency(amount: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount)}`;
  }

  function formatPhone(phone: string): string {
    const digits = phone.replace(/\D/g, '');
    if (!digits) return phone;
    if (digits.startsWith('62')) return `+${digits}`;
    if (digits.startsWith('0')) return `+62${digits.slice(1)}`;
    return `+${digits}`;
  }

  async function loadTracking(): Promise<void> {
    if (!transaction.supportsLiveTracking || !transaction.shipment.trackingOrder || loadingTracking) return;
    loadingTracking = true;
    trackingError = '';
    const body = new URLSearchParams({
      action: 'kiriof_transaction_detail_tracking',
      nonce: bootstrap.ajax.nonce,
      order_id: transaction.shipment.trackingOrder,
    });

    try {
      const response = await fetch(bootstrap.ajax.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
      });
      const payload = (await response.json()) as {
        success?: boolean;
        data?: TrackingResponse | { message?: string };
      };
      if (!response.ok || !payload.success) {
        throw new Error((payload.data as { message?: string })?.message ?? i18n.trackingError);
      }
      tracking = payload.data as TrackingResponse;
    } catch (error) {
      trackingError = error instanceof Error ? error.message : i18n.trackingError;
    } finally {
      loadingTracking = false;
    }
  }

  function legacyAction(action: 'cod-adjust' | 'cancel-deficit' | 'cancel'): void {
    const event = new CustomEvent('click', { bubbles: true });
    const trigger = document.createElement('button');
    trigger.dataset.kjAction = action;
    for (const [key, value] of Object.entries(transaction.actions.data)) {
      trigger.dataset[key.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`)] = String(value);
    }
    if (action === 'cancel') trigger.dataset.orderId = String(transaction.actions.data.kaOrderId);
    document.body.append(trigger);
    trigger.dispatchEvent(event);
    trigger.remove();
  }

  onMount(() => void loadTracking());
</script>

<div class="kiriof-shadcn kiriof-transaction-detail-app">
  <Toolbar toolbar={bootstrap.toolbar}>
    {#if transaction.shipment.printUrl}
      <Button href={transaction.shipment.printUrl} target="_blank" rel="noopener noreferrer">
        <IconPrinter data-icon="inline-start" />
        {i18n.printLabel}
      </Button>
    {/if}
    {#if transaction.supportsLiveTracking}
      <Button variant="outline" onclick={() => void loadTracking()} disabled={loadingTracking}>
        <IconRoute data-icon="inline-start" />
        {i18n.liveTracking}
      </Button>
    {/if}
  </Toolbar>

  <div class="kiriof-transaction-detail-grid">
    <main class="kiriof-transaction-detail-main">
      <Card.Root class="kiriof-admin-list-card kiriof-transaction-detail-status">
        <Card.Header>
          <Card.Title>{transaction.orderNumber}</Card.Title>
          <Card.Action><StatusBadge label={transaction.status.label} tone={transaction.status.tone} /></Card.Action>
        </Card.Header>
        <Card.Content>
          <div class="kiriof-transaction-detail-reference">
            <strong>{transaction.orderId}</strong>
            <span>{transaction.createdAt}</span>
          </div>
          <div class="kiriof-transaction-detail-steps">
            {#each transaction.steps as step, index}
              <div class:complete={step.completed} class="kiriof-transaction-detail-step">
                <span>{#if step.completed}<IconCheck />{:else}<IconBox />{/if}</span>
                <strong>{step.label}</strong>
                <small>{step.date || '—'}</small>
                {#if index < transaction.steps.length - 1}<i></i>{/if}
              </div>
            {/each}
          </div>
        </Card.Content>
      </Card.Root>

      <section class="kiriof-transaction-detail-addresses" aria-label={i18n.senderRecipientDetails}>
        <Card.Root class="kiriof-admin-list-card">
          <Card.Header>
            <Card.Title><IconMapPin />{i18n.sender}</Card.Title>
            {#if transaction.actions.changeOrigin}
              <Card.Action>
                <Button
                  variant="outline"
                  size="sm"
                  class="kiriof-transaction-detail-change-origin kiriof-change-origin-button"
                  data-ka-order-id={transaction.actions.data.kaOrderId}
                  data-current-origin={transaction.actions.data.currentOrigin}
                  data-current-origin-address={transaction.actions.data.currentOriginAddress}
                  data-current-location-id={transaction.actions.data.currentLocationId}
                  data-nonce={transaction.actions.data.nonce}
                >
                  <IconMapPin data-icon="inline-start" />
                  {i18n.changeOrigin}
                </Button>
              </Card.Action>
            {/if}
          </Card.Header>
          <Card.Content class="kiriof-transaction-detail-address">
            <strong>{transaction.sender.name}</strong>
            {#if transaction.sender.phone}<a href={`tel:${transaction.sender.phone}`}><IconPhone />{formatPhone(transaction.sender.phone)}</a>{/if}
            {#each transaction.sender.address as line}<span>{line}</span>{/each}
          </Card.Content>
        </Card.Root>

        <Card.Root class="kiriof-admin-list-card">
          <Card.Header><Card.Title><IconUser />{i18n.recipient}</Card.Title></Card.Header>
          <Card.Content class="kiriof-transaction-detail-address">
            <strong>{transaction.recipient.name}</strong>
            {#if transaction.recipient.phone}<a href={`tel:${transaction.recipient.phone}`}><IconPhone />{formatPhone(transaction.recipient.phone)}</a>{/if}
            {#each transaction.recipient.address as line}<span>{line}</span>{/each}
          </Card.Content>
        </Card.Root>
      </section>

      <Card.Root class="kiriof-admin-list-card">
        <Card.Header><Card.Title><IconPackage />{i18n.package}</Card.Title></Card.Header>
        <Card.Content class="kiriof-transaction-detail-package">
          <div><span>{i18n.weight}</span><strong>{transaction.package.weight} g</strong></div>
          <div><span>{i18n.dimensions}</span><strong>{transaction.package.length} × {transaction.package.width} × {transaction.package.height} cm</strong></div>
        </Card.Content>
      </Card.Root>

      <Card.Root class="kiriof-admin-list-card">
        <Card.Header><Card.Title><IconBox />{i18n.products}</Card.Title></Card.Header>
        <Card.Content class="kiriof-transaction-detail-products">
          {#if transaction.items.length === 0}<p>—</p>{/if}
          {#each transaction.items as item}
            <div><span><strong>{item.name}</strong>{#if item.sku}<small>SKU: {item.sku}</small>{/if}</span><span>× {item.quantity}</span><strong>{currency(item.total)}</strong></div>
          {/each}
        </Card.Content>
      </Card.Root>

      <Card.Root class="kiriof-admin-list-card">
        <Card.Header>
          <Card.Title><IconExternalLink />{transaction.orderNumber}</Card.Title>
          {#if transaction.orderUrl}
            <Card.Action><a class="kiriof-transaction-detail-order-link" href={transaction.orderUrl} target="_blank" rel="noopener noreferrer">{i18n.openOrder}<IconExternalLink /></a></Card.Action>
          {/if}
        </Card.Header>
        <Card.Content class="kiriof-transaction-detail-notes">
          {#if transaction.notes.length === 0}<p>—</p>{/if}
          {#each transaction.notes as note}<div><strong>{note.label}</strong><p>{note.content}</p></div>{/each}
        </Card.Content>
      </Card.Root>
    </main>

    <aside class="kiriof-transaction-detail-sidebar">
      <Card.Root class="kiriof-admin-list-card kiriof-transaction-detail-shipment">
        <Card.Header>
          <Card.Title><IconTruck />{i18n.shipment}</Card.Title>
          <Card.Action>
            <div class="kiriof-transaction-detail-badges">
              <StatusBadge label={transaction.paymentLabel} tone={transaction.isCod ? 'info' : 'neutral'} />
              <StatusBadge label={transaction.pickupNumber ? i18n.pickup : i18n.dropoff} tone="neutral" />
              {#if transaction.shipment.isPaid}<StatusBadge label={i18n.paid} tone="success" />{/if}
            </div>
          </Card.Action>
        </Card.Header>
        <Card.Content>
          <div class="kiriof-transaction-detail-3pl">
            {#if courierImage(transaction.shipment.courier.code, transaction.shipment.courier.service)}<img src={courierImage(transaction.shipment.courier.code, transaction.shipment.courier.service)} alt="" />{/if}
            <div>
              <strong>{transaction.shipment.courier.service || '—'}</strong>
              <div class="kiriof-transaction-detail-awb"><span>{i18n.airwaybill}</span><code>{transaction.shipment.awb || '—'}</code></div>
            </div>
          </div>
          <dl class="kiriof-transaction-detail-costs">
            <div><dt>{i18n.shipping}</dt><dd>{currency(transaction.shipment.costs.shipping)}</dd></div>
            {#if transaction.shipment.costs.insurance > 0}<div><dt>{i18n.insurance}</dt><dd>{currency(transaction.shipment.costs.insurance)}</dd></div>{/if}
            {#if transaction.shipment.costs.codFee > 0}<div><dt>{i18n.codFee}</dt><dd>{currency(transaction.shipment.costs.codFee)}</dd></div>{/if}
            {#if transaction.shipment.costs.discount > 0}<div class="discount"><dt>{i18n.discount}</dt><dd>−{currency(transaction.shipment.costs.discount)}</dd></div>{/if}
            <div class="total"><dt>{i18n.total}</dt><dd>{currency(transaction.shipment.costs.total)}</dd></div>
            {#if transaction.isCod}<div class="cod"><dt>{i18n.codValue}</dt><dd>{currency(transaction.shipment.codValue)}</dd></div>{/if}
          </dl>
          {#if transaction.actions.adjustDeficit}<Button variant="outline" onclick={() => legacyAction('cod-adjust')}><IconRefresh data-icon="inline-start" />{i18n.adjustDeficit}</Button>{/if}
          {#if transaction.actions.cancelDeficit}<Button variant="destructive" class="kiriof-transaction-detail-destructive-action" onclick={() => legacyAction('cancel-deficit')}><IconX data-icon="inline-start" />{i18n.cancel}</Button>{/if}
          {#if transaction.actions.cancel}<Button variant="destructive" class="kiriof-transaction-detail-destructive-action" onclick={() => legacyAction('cancel')}><IconX data-icon="inline-start" />{i18n.cancel}</Button>{/if}
        </Card.Content>
      </Card.Root>

      {#if transaction.supportsLiveTracking}
        <Card.Root class="kiriof-admin-list-card kiriof-transaction-detail-tracking">
          <Card.Header><Card.Title><IconRoute />{i18n.tracking}</Card.Title></Card.Header>
          <Card.Content>
            {#if loadingTracking}<p class="kiriof-transaction-detail-loading">{i18n.loadingTracking}</p>
            {:else if trackingError}<p role="alert">{trackingError}</p>
            {:else if !tracking?.histories?.length}<p>{i18n.trackingEmpty}</p>
            {:else}<ol>{#each tracking.histories as history}<li><span><IconCircleCheck /></span><div><strong>{history.status}</strong><small>{history.created_at}</small>{#if history.driver}<small>{history.driver}</small>{/if}</div></li>{/each}</ol>{/if}
          </Card.Content>
        </Card.Root>
      {/if}
    </aside>
  </div>
</div>
