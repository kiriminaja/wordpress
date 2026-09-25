<script lang="ts">
  import { tick } from 'svelte';
  import { IconCheck, IconChevronDown } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Command from '$lib/components/ui/command';
  import * as Popover from '$lib/components/ui/popover';

  export type KiriofComboboxOption = { value: string; label: string; description?: string };

  let {
    value = $bindable(''),
    options,
    placeholder,
    disabled = false,
    prefix,
    suffix,
    onChange,
  }: {
    value?: string;
    options: KiriofComboboxOption[];
    placeholder: string;
    disabled?: boolean;
    prefix?: import('svelte').Snippet;
    suffix?: import('svelte').Snippet;
    onChange?: (value: string) => void;
  } = $props();

  let open = $state(false);
  let query = $state('');
  let triggerRef = $state<HTMLButtonElement>(null!);
  const selected = $derived(options.find((option) => option.value === value));
  const filtered = $derived(options.filter((option) => `${option.label} ${option.description ?? ''}`.toLowerCase().includes(query.trim().toLowerCase())));

  function select(nextValue: string): void {
    value = nextValue;
    onChange?.(nextValue);
    open = false;
    void tick().then(() => triggerRef?.focus());
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger bind:ref={triggerRef}>
    {#snippet child({ props })}
      <Button {...props} variant="outline" class="kiriof-combobox !h-10 !w-full !justify-between !bg-background !border-input !px-3 !font-normal" role="combobox" aria-expanded={open} {disabled}>
        <span class="flex min-w-0 items-center gap-2">
          {#if prefix}<span class="flex shrink-0 items-center text-muted-foreground">{@render prefix()}</span>{/if}
          <span class="truncate">{selected?.label ?? placeholder}</span>
        </span>
        {#if suffix}{@render suffix()}{:else}<IconChevronDown class="size-4 shrink-0 text-muted-foreground" />{/if}
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn !z-[100002] w-[var(--bits-popover-anchor-width)] p-0" align="start">
    <Command.Root shouldFilter={false}>
      <Command.Input bind:value={query} placeholder={placeholder} />
      <Command.List class="max-h-64">
        {#if filtered.length === 0}
          <Command.Empty>No option found.</Command.Empty>
        {:else}
          <Command.Group>
            {#each filtered as option (option.value)}
              <Command.Item value={option.value} onSelect={() => select(option.value)}>
                <IconCheck class="size-4 {value === option.value ? 'opacity-100' : 'opacity-0'}" />
                <span class="grid min-w-0 gap-0.5"><strong class="truncate text-sm">{option.label}</strong>{#if option.description}<small class="truncate text-xs text-muted-foreground">{option.description}</small>{/if}</span>
              </Command.Item>
            {/each}
          </Command.Group>
        {/if}
      </Command.List>
    </Command.Root>
  </Popover.Content>
</Popover.Root>
