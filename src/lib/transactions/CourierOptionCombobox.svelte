<script lang="ts">
  import { tick } from 'svelte';
  import { IconCheck, IconChevronDown, IconTruck } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Command from '$lib/components/ui/command';
  import * as Popover from '$lib/components/ui/popover';

  export type CourierOption = {
    courier?: string;
    service?: string;
    service_code: string;
    service_name: string;
    price?: string;
    raw_price?: number;
  };

  let {
    value,
    options,
    disabled = false,
    placeholder,
    selectedLabel,
    onChange,
  }: {
    value: string;
    options: CourierOption[];
    disabled?: boolean;
    placeholder: string;
    selectedLabel: (option: CourierOption) => string;
    onChange: (value: string) => void;
  } = $props();

  let open = $state(false);
  let query = $state('');
  let triggerRef = $state<HTMLButtonElement>(null!);
  const selected = $derived(options.find((option) => `${option.service_code}|${option.service_name}` === value));
  const filtered = $derived(options.filter((option) => `${selectedLabel(option)} ${option.price ?? option.raw_price ?? ''}`.toLowerCase().includes(query.trim().toLowerCase())));

  function select(nextValue: string): void {
    onChange(nextValue);
    open = false;
    void tick().then(() => triggerRef?.focus());
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger bind:ref={triggerRef}>
    {#snippet child({ props })}
      <Button {...props} variant="outline" class="h-10 w-full justify-between px-3 font-normal" role="combobox" aria-expanded={open} {disabled}>
        <span class="flex min-w-0 items-center gap-2"><IconTruck class="size-4 shrink-0 text-muted-foreground" /><span class="truncate">{selected ? selectedLabel(selected) : placeholder}</span></span>
        <IconChevronDown class="size-4 shrink-0 text-muted-foreground" />
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn !z-[100002] w-[var(--bits-popover-anchor-width)] p-0" align="start">
    <Command.Root shouldFilter={false}>
      <Command.Input bind:value={query} placeholder={placeholder} />
      <Command.List class="max-h-64">
        {#if filtered.length === 0}
          <Command.Empty>No courier option found.</Command.Empty>
        {:else}
          <Command.Group>
            {#each filtered as option (`${option.service_code}|${option.service_name}`)}
              {@const key = `${option.service_code}|${option.service_name}`}
              <Command.Item value={key} onSelect={() => select(key)}>
                <IconCheck class="size-4 {value === key ? 'opacity-100' : 'opacity-0'}" />
                <span class="grid min-w-0 flex-1 gap-0.5"><strong class="truncate text-sm">{selectedLabel(option)}</strong><small class="text-xs text-muted-foreground">{option.price ?? `Rp${new Intl.NumberFormat('id-ID').format(option.raw_price ?? 0)}`}</small></span>
              </Command.Item>
            {/each}
          </Command.Group>
        {/if}
      </Command.List>
    </Command.Root>
  </Popover.Content>
</Popover.Root>
