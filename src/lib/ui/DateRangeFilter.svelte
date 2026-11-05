<script lang="ts">
  import { untrack } from 'svelte';
  import { IconCalendar, IconChevronDown } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Popover from '$lib/components/ui/popover';

  let { dateFrom = '', dateTo = '', month = '', disabled = false, label, applyLabel, clearLabel, locale = 'en-US', loadingLabel = 'Loading…', errorLabel = 'Unable to load calendar.', retryLabel = 'Retry', onChange }: {
    dateFrom?: string; dateTo?: string; month?: string; disabled?: boolean; label: string;
    applyLabel: string; clearLabel: string; locale?: string; loadingLabel?: string; errorLabel?: string; retryLabel?: string;
    onChange: (range: { date_from: string; date_to: string; month: string }) => void;
  } = $props();
  let open = $state(false);
  let panelPromise = $state<Promise<typeof import('./DateRangePanel.svelte')> | undefined>();
  const displayLabel = $derived(dateFrom || dateTo ? `${dateFrom || '…'} – ${dateTo || '…'}` : month || label);

  function load(): void {
    panelPromise = import('./DateRangePanel.svelte');
  }

  $effect(() => {
    if (open) untrack(load);
    else panelPromise = undefined;
  });
</script>

<Popover.Root bind:open>
  <Popover.Trigger>
    {#snippet child({ props })}<Button {...props} variant="outline" disabled={disabled} class="kiriof-date-range-trigger" aria-label={label}><IconCalendar data-icon="inline-start" />{displayLabel}<IconChevronDown data-icon="inline-end" /></Button>{/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn kiriof-date-range-popover w-auto p-0" align="end">
    {#if open && panelPromise}
      {#await panelPromise}
        <p class="p-3" role="status">{loadingLabel}</p>
      {:then { default: Panel }}
        <Panel {dateFrom} {dateTo} {locale} {applyLabel} {clearLabel} onChange={(range) => { open = false; onChange(range); }} />
      {:catch}
        <div class="p-3">
          <p role="alert">{errorLabel}</p>
          <Button variant="outline" onclick={load}>{retryLabel}</Button>
        </div>
      {/await}
    {/if}
  </Popover.Content>
</Popover.Root>
