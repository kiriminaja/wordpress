<script lang="ts">
  import { untrack } from 'svelte';
  import { parseDate, type DateValue } from '@internationalized/date';
  import { Button } from '$lib/components/ui/button';
  import { RangeCalendar } from '$lib/components/ui/range-calendar';

  let { dateFrom = '', dateTo = '', applyLabel, clearLabel, locale = 'en-US', onChange }: {
    dateFrom?: string; dateTo?: string; applyLabel: string; clearLabel: string; locale?: string;
    onChange: (range: { date_from: string; date_to: string; month: string }) => void;
  } = $props();

  function load(): { start: DateValue | undefined; end: DateValue | undefined } {
    try { return { start: dateFrom ? parseDate(dateFrom) : undefined, end: dateTo ? parseDate(dateTo) : undefined }; }
    catch { return { start: undefined, end: undefined }; }
  }

  let value = $state(untrack(load));
</script>

<RangeCalendar bind:value {locale} />
<div class="flex justify-end gap-2 border-t border-border p-3">
  <Button variant="ghost" onclick={() => { value = { start: undefined, end: undefined }; onChange({ date_from: '', date_to: '', month: '' }); }}>{clearLabel}</Button>
  <Button disabled={!value.start || !value.end} onclick={() => { if (value.start && value.end) onChange({ date_from: value.start.toString(), date_to: value.end.toString(), month: '' }); }}>{applyLabel}</Button>
</div>
