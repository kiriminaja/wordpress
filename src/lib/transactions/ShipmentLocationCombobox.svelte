<script lang="ts">
  import { tick } from 'svelte';
  import { IconCheck, IconChevronDown, IconMapPin } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Command from '$lib/components/ui/command';
  import * as Popover from '$lib/components/ui/popover';

  type Location = { id: number; name: string; address: string };

  let {
    value,
    locations,
    currentLocationId,
    disabled = false,
    placeholder,
    onChange,
  }: {
    value: string;
    locations: Location[];
    currentLocationId: number;
    disabled?: boolean;
    placeholder: string;
    onChange: (value: string) => void;
  } = $props();

  let open = $state(false);
  let query = $state('');
  let triggerRef = $state<HTMLButtonElement>(null!);
  const selected = $derived(locations.find((location) => String(location.id) === value));
  const options = $derived(locations.filter((location) => location.id !== currentLocationId));
  const filtered = $derived(options.filter((location) => `${location.name} ${location.address}`.toLowerCase().includes(query.trim().toLowerCase())));

  function select(id: string): void {
    onChange(id);
    open = false;
    void tick().then(() => triggerRef?.focus());
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger bind:ref={triggerRef}>
    {#snippet child({ props })}
      <Button {...props} variant="outline" class="h-10 w-full justify-between px-3 font-normal" role="combobox" aria-expanded={open} {disabled}>
        <span class="flex min-w-0 items-center gap-2"><IconMapPin class="size-4 shrink-0 text-muted-foreground" /><span class="truncate">{selected?.name ?? placeholder}</span></span>
        <IconChevronDown class="size-4 shrink-0 text-muted-foreground" />
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="kiriof-shadcn !z-[100002] w-[var(--bits-popover-anchor-width)] p-0" align="start">
    <Command.Root shouldFilter={false}>
      <Command.Input bind:value={query} placeholder={placeholder} />
      <Command.List class="max-h-64">
        {#if filtered.length === 0}
          <Command.Empty>No alternate shipment location found.</Command.Empty>
        {:else}
          <Command.Group>
            {#each filtered as location (location.id)}
              <Command.Item value={String(location.id)} onSelect={() => select(String(location.id))}>
                <IconCheck class="size-4 {value === String(location.id) ? 'opacity-100' : 'opacity-0'}" />
                <span class="grid min-w-0 gap-0.5"><strong class="truncate text-sm">{location.name}</strong><small class="truncate text-xs text-muted-foreground">{location.address}</small></span>
              </Command.Item>
            {/each}
          </Command.Group>
        {/if}
      </Command.List>
    </Command.Root>
  </Popover.Content>
</Popover.Root>
