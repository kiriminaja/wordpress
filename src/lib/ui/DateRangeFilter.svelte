<script lang="ts">
  import { parseDate, type DateValue } from '@internationalized/date';
  import { IconCalendar, IconChevronDown } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Popover from '$lib/components/ui/popover';
  import { RangeCalendar } from '$lib/components/ui/range-calendar';

  let { dateFrom = '', dateTo = '', month = '', disabled = false, label, applyLabel, clearLabel, locale = 'en-US', onChange }: {
    dateFrom?: string; dateTo?: string; month?: string; disabled?: boolean; label: string;
    applyLabel: string; clearLabel: string; locale?: string; onChange: (range: { date_from: string; date_to: string; month: string }) => void;
  } = $props();
  let open = $state(false);
  let value = $state<{ start: DateValue | undefined; end: DateValue | undefined }>({ start: undefined, end: undefined });
  const displayLabel = $derived(dateFrom || dateTo ? `${dateFrom || '…'} – ${dateTo || '…'}` : month || label);
  function load(): void {
    try { value = { start: dateFrom ? parseDate(dateFrom) : undefined, end: dateTo ? parseDate(dateTo) : undefined }; }
    catch { value = { start: undefined, end: undefined }; }
  }
</script>

<Popover.Root bind:open onOpenChange={(next) => { if (next) load(); }}>
  <Popover.Trigger>
    {#snippet child({ props })}<Button {...props} variant="outline" disabled={disabled} class="kiriof-date-range-trigger" aria-label={label}><IconCalendar data-icon="inline-start" />{displayLabel}<IconChevronDown data-icon="inline-end" /></Button>{/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn kiriof-date-range-popover w-auto p-0" align="end">
    <RangeCalendar bind:value {locale} />
    <div class="flex justify-end gap-2 border-t border-border p-3">
      <Button variant="ghost" onclick={() => { value = { start: undefined, end: undefined }; open = false; onChange({ date_from: '', date_to: '', month: '' }); }}>{clearLabel}</Button>
      <Button disabled={!value.start || !value.end} onclick={() => { if (value.start && value.end) { open = false; onChange({ date_from: value.start.toString(), date_to: value.end.toString(), month: '' }); } }}>{applyLabel}</Button>
    </div>
  </Popover.Content>
</Popover.Root>
