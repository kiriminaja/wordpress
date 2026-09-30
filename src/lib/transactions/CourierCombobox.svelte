<script lang="ts">
  import { tick } from 'svelte';
  import * as Command from '$lib/components/ui/command';
  import * as Popover from '$lib/components/ui/popover';
  import { Button } from '$lib/components/ui/button';
  import { IconCheck, IconChevronDown, IconTruck } from '@tabler/icons-svelte';

  let {
    value,
    options,
    placeholder,
    disabled = false,
    onChange,
  }: {
    value: string;
    options: Array<{ value: string; label: string }>;
    placeholder: string;
    disabled?: boolean;
    onChange: (value: string) => void;
  } = $props();

  let open = $state(false);
  let query = $state('');
  let triggerRef = $state<HTMLButtonElement>(null!);
  const selectedLabel = $derived(options.find((option) => option.value === value)?.label ?? placeholder);
  const filteredOptions = $derived(
    options.filter((option) => option.label.toLowerCase().includes(query.trim().toLowerCase())),
  );

  function select(nextValue: string): void {
    onChange(nextValue);
    open = false;
    void tick().then(() => triggerRef?.focus());
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger bind:ref={triggerRef}>
    {#snippet child({ props })}
      <Button {...props} variant="outline" class="kiriof-courier-trigger h-9 w-full justify-between px-3 font-normal" role="combobox" aria-expanded={open} {disabled}>
        <span class="flex min-w-0 items-center gap-2"><IconTruck class="size-4 shrink-0 text-muted-foreground" /><span class="truncate">{selectedLabel}</span></span>
        <IconChevronDown class="size-4 shrink-0 text-muted-foreground" />
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn w-[var(--bits-popover-anchor-width)] p-0" align="start">
    <Command.Root shouldFilter={false}>
      <Command.Input bind:value={query} placeholder={placeholder} />
      <Command.List class="max-h-64">
        {#if filteredOptions.length === 0}
          <Command.Empty>No courier found.</Command.Empty>
        {:else}
          <Command.Group>
            {#each filteredOptions as option (option.value)}
              <Command.Item value={option.value} onSelect={() => select(option.value)}>
                <IconCheck class="size-4 {value === option.value ? 'opacity-100' : 'opacity-0'}" />
                {option.label}
              </Command.Item>
            {/each}
          </Command.Group>
        {/if}
      </Command.List>
    </Command.Root>
  </Popover.Content>
</Popover.Root>
