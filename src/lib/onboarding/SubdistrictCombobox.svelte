<script lang="ts">
  import { tick } from 'svelte';
  import type { HTMLButtonAttributes } from 'svelte/elements';
  import { cn } from '$lib/utils';
  import * as Command from '$lib/components/ui/command';
  import * as Popover from '$lib/components/ui/popover';
  import { Button } from '$lib/components/ui/button';
  import { IconChevronDown, IconLoader2 } from '@tabler/icons-svelte';

  type Area = { id: string | number; text?: string; label?: string };

  let {
    id,
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedby,
    value,
    areas,
    loading,
    placeholder,
    loadingText,
    noResultsText,
    typeMoreText,
    onSearch,
    onSelect,
  }: {
    id?: string;
    disabled?: boolean;
    'aria-invalid'?: HTMLButtonAttributes['aria-invalid'];
    'aria-describedby'?: string;
    value: string;
    areas: Area[];
    loading: boolean;
    placeholder: string;
    loadingText: string;
    noResultsText: string;
    typeMoreText: string;
    onSearch: (query: string) => void;
    onSelect: (area: Area) => void;
  } = $props();

  let open = $state(false);
  let query = $state('');
  let triggerRef = $state<HTMLButtonElement>(null!);
  const displayValue = $derived(value || placeholder);

  function closeAndFocusTrigger(): void {
    open = false;
    void tick().then(() => triggerRef?.focus());
  }

  function handleSelect(area: Area): void {
    onSelect(area);
    query = String(area.text ?? area.label ?? '');
    closeAndFocusTrigger();
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger {id} {disabled} bind:ref={triggerRef}>
    {#snippet child({ props })}
      <Button
        {...props}
        variant="outline"
        class="w-full justify-between font-normal"
        aria-invalid={ariaInvalid}
        aria-describedby={ariaDescribedby}
        role="combobox"
        aria-expanded={open}
      >
        <span class={cn('min-w-0 flex-1 truncate text-left', !value && 'text-muted-foreground')}>
          {displayValue}
        </span>
        <IconChevronDown data-icon="inline-end" class="text-muted-foreground" aria-hidden="true" />
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content
    class="kiriof-shadcn kiriof-onboarding-subdistrict-popover w-[var(--bits-popover-anchor-width)] p-0"
    align="start"
  >
    <Command.Root shouldFilter={false}>
      <Command.Input
        value={query}
        placeholder={placeholder}
        aria-label={placeholder}
        oninput={(event: Event) => {
          query = (event.currentTarget as HTMLInputElement).value;
          onSearch(query);
        }}
      />
      <Command.List class="max-h-56" aria-busy={loading}>
        {#if loading}
          <div class="flex items-center gap-2 px-3 py-6 text-sm text-muted-foreground">
            <IconLoader2 class="animate-spin" aria-hidden="true" />
            {loadingText}
          </div>
        {:else if query.trim().length < 3}
          <Command.Empty>{typeMoreText}</Command.Empty>
        {:else if areas.length === 0}
          <Command.Empty>{noResultsText}</Command.Empty>
        {:else}
          <Command.Group>
            {#each areas as area (area.id)}
              {@const label = String(area.text ?? area.label ?? '')}
              <Command.Item
                value={String(area.id)}
                data-checked={value === label}
                onSelect={() => handleSelect(area)}
              >
                <span class="min-w-0 truncate">{label}</span>
              </Command.Item>
            {/each}
          </Command.Group>
        {/if}
      </Command.List>
    </Command.Root>
  </Popover.Content>
</Popover.Root>
