<script lang="ts">
  import { IconRefresh, IconX } from '@tabler/icons-svelte';

  let { i18n }: { i18n: Record<string, string> } = $props();
</script>

<div id="payment-modal" class="kj-hidden">
  <div class="modal-container">
    <div class="media-modal kiriof-payment-modal" tabindex="0" role="dialog" aria-modal="true" aria-label={i18n.scanToPay}>
      <div class="media-modal-container">
        <button type="button" class="closebtn-container" aria-label="Close"><IconX size={22} /></button>
        <div class="content-header"><h1>{i18n.scanToPay}</h1></div>
        <div class="content-body">
          <div class="kj-modal-loader"><div class="kiriof-modal-loading"><div class="kj-loader"></div></div></div>
          <div class="kj-modal-content kj-hidden">
            <div id="paymentQR" class="kiriof-qr"></div>
            <div class="kiriof-payment-code"><strong>{i18n.code}: <span id="trx-code">—</span></strong><span class="trx-pay-amount">Rp0</span></div>
            <div class="kiriof-charge-list"><div><span>{i18n.codCharges}</span><strong>Rp0</strong></div><div><span>{i18n.nonCodCharges}</span><strong class="trx-pay-amount">Rp0</strong></div><div><span>{i18n.totalCharges}</span><strong class="trx-pay-amount">Rp0</strong></div></div>
            <div class="kiriof-payment-expiry"><span>{i18n.expiresAt}</span><strong id="trx-expired-at">—</strong></div>
            <button class="button button-primary kiriof-payment-refresh" type="button"><IconRefresh size={16} />{i18n.refresh}</button>
          </div>
          <div class="kj-err-container kiriof-modal-error"><p>{i18n.error}</p><button class="button button-primary kiriof-payment-refresh" type="button">{i18n.refresh}</button></div>
        </div>
      </div>
    </div>
  </div>
  <div class="media-modal-backdrop"></div>
</div>

<div id="request-pickup-modal" class="kj-hidden">
  <div class="modal-container">
    <div class="media-modal kiriof-payment-modal" tabindex="0" role="dialog" aria-modal="true" aria-label={i18n.schedule}>
      <div class="media-modal-container">
        <button type="button" class="closebtn-container" aria-label="Close"><IconX size={22} /></button>
        <div class="content-header"><h1>{i18n.schedule}</h1></div>
        <div class="content-body">
          <div class="kj-modal-loader"><div class="kiriof-modal-loading"><div class="kj-loader"></div></div></div>
          <div class="kj-modal-content kj-hidden">
            <div id="schedule-transaction-summary" class="kiriof-charge-list"></div>
            <div id="schedule-opt-list" class="kiriof-schedule-list"></div>
            <div class="err_msg kj-hidden kiriof-section-message error"></div>
            <button class="button button-primary kiriof-request-pickup-submit" type="button"><span>{i18n.pickSchedule}</span></button>
          </div>
          <div class="kj-err-container kiriof-modal-error"><p>{i18n.error}</p><button class="button button-primary kiriof-reschedule-refresh" type="button">{i18n.refresh}</button></div>
        </div>
      </div>
    </div>
  </div>
  <div class="media-modal-backdrop"></div>
</div>
