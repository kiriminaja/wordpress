<script lang="ts">
  import type { AccountView } from './types';
  let { state, strings }: { state: AccountView; strings: Record<string, string> } = $props();
  const ready = $derived(state.selection && !state.loading && !state.failed);
</script>
{#if state.address.country === 'ID'}
  {#if !ready}<span class="kiriof-address-status__badge is-warning" title={strings.pinRequirement || ''}>⚠ {state.loading ? strings.checkingDistrict || '' : strings.districtNotSet || ''}</span>{/if}
  <span class="kiriof-address-status__badge" class:is-complete={Boolean(state.pin)} class:is-warning={!state.pin} title={strings.pinRequirement || ''}>{state.pin ? '✓ ' : '⚠ '}{state.pin ? strings.pinLocation || '' : strings.needPinLocation || ''}</span>
{/if}
