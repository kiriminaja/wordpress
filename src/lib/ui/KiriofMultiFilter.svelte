<script lang="ts">
  import { untrack } from 'svelte';
  import { IconChevronDown, IconSearch } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import { Badge } from '$lib/components/ui/badge';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Popover from '$lib/components/ui/popover';
  import { parseFilterSelection, serializeFilterSelection, toggleFilterOption, type FilterOption } from './multi-filter';

  let { value, options, allLabel, allValue = '', applyLabel, emptyLabel, searchLabel, selectedLabel, disabled = false, prefix, onChange }: {
    value: string;
    options: FilterOption[];
    allLabel: string;
    allValue?: string;
    applyLabel: string;
    emptyLabel: string;
    searchLabel: string;
    selectedLabel: string;
    disabled?: boolean;
    prefix?: import('svelte').Snippet;
    onChange: (value: string) => void;
  } = $props();
  const id = $props.id();
  let open = $state(false);
  let query = $state('');
  let draft = $state<string[]>([]);
  const committed = $derived(parseFilterSelection(value, options, allValue));
  const selectedText = $derived(committed.length === 0 ? allLabel : committed.length === 1 ? options.find((option) => option.value === committed[0])?.label ?? allLabel : selectedLabel.replace('%s', String(committed.length)));
  const visibleOptions = $derived(options.filter((option) => `${option.label} ${option.value}`.toLowerCase().includes(query.trim().toLowerCase())));
  const allChecked = $derived(draft.length === 0);

  $effect(() => {
    const active = open;
    const current = committed;
    if (active) untrack(() => { draft = [...current]; });
  });

  function changeOpen(next: boolean): void {
    open = next;
    if (next) { draft = [...committed]; query = ''; }
  }

  function toggle(option: FilterOption, checked: boolean): void {
    draft = toggleFilterOption(draft, option.value, checked, options);
  }

  function apply(): void {
    onChange(serializeFilterSelection(draft, options, allValue));
    open = false;
  }
</script>

<Popover.Root {open} onOpenChange={changeOpen}>
  <Popover.Trigger>
    {#snippet child({ props })}
      <Button {...props} variant="outline" class="kiriof-courier-trigger !h-9 !w-full !min-w-0 !justify-between !px-3 !font-normal" aria-label={allLabel} aria-expanded={open} {disabled}>
        <span class="!flex min-w-0 items-center gap-2">
          {#if prefix}{@render prefix()}{/if}
          <span class="truncate">{selectedText}</span>
          {#if committed.length > 1}<Badge variant="secondary" class="!px-1.5 !py-0">{committed.length}</Badge>{/if}
        </span>
        <IconChevronDown class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn !z-[100002] !w-80 !max-w-full !gap-0 !p-0" align="start">
    <div class="p-2">
      <InputGroup.Root>
        <InputGroup.Addon align="inline-start"><IconSearch class="size-4" aria-hidden="true" /></InputGroup.Addon>
        <InputGroup.Input type="search" bind:value={query} aria-label={searchLabel} placeholder={searchLabel} />
      </InputGroup.Root>
    </div>
    <div class="border-y border-border p-2">
      <div class="!flex items-center gap-2 rounded-md px-2 py-2 hover:bg-muted">
        <Checkbox id={`${id}-all`} checked={allChecked} {disabled} onCheckedChange={() => { draft = []; }} />
        <label class="flex-1 cursor-pointer text-sm font-medium" for={`${id}-all`}>{allLabel}</label>
      </div>
    </div>
    <div class="max-h-64 overflow-y-auto p-2" role="group" aria-label={allLabel}>
      {#each visibleOptions as option (option.value)}
        <div class="!flex items-center gap-2 rounded-md px-2 py-2 hover:bg-muted">
          <Checkbox id={`${id}-${option.value}`} checked={draft.includes(option.value)} {disabled} onCheckedChange={(checked) => toggle(option, checked)} />
          <label class="min-w-0 flex-1 cursor-pointer text-sm" for={`${id}-${option.value}`}>{option.label}</label>
          {#if option.count !== undefined}<span class="text-xs tabular-nums text-muted-foreground">{option.count}</span>{/if}
        </div>
      {:else}
        <p class="m-0 px-2 py-5 text-center text-sm text-muted-foreground">{emptyLabel}</p>
      {/each}
    </div>
    <div class="!flex items-center justify-between gap-3 border-t border-border p-3">
      <span class="text-xs text-muted-foreground" role="status">{allChecked ? allLabel : selectedLabel.replace('%s', String(draft.length))}</span>
      <Button size="sm" onclick={apply} {disabled}>{applyLabel}</Button>
    </div>
  </Popover.Content>
</Popover.Root>
