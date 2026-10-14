<script lang="ts">
  import { Combobox } from 'bits-ui';
  import { tick } from 'svelte';
  import CourierOption from './CourierOption.svelte';
  import type { SelectorState } from '../state/selector-state.svelte';
  let { state: model, district = false, shipping = false, logos = {}, strings = {}, onChoose, onSearch }: {
    state: SelectorState;
    district?: boolean;
    shipping?: boolean;
    logos?: Record<string, string>;
    strings?: Record<string, string>;
    onChoose: (value: string, activated?: boolean) => void;
    onSearch: (term: string) => void;
  } = $props();
  let open = $state(false);
  let input = $state<HTMLInputElement | null>(null);
  let anchor = $state<HTMLButtonElement | null>(null);
  const selected = $derived(model.options.find((option) => option.value === model.value));
  const filtered = $derived(district || !model.term ? model.options : model.options.filter((option) => option.label.toLowerCase().includes(model.term.toLowerCase())));
  async function opened(next: boolean) {
    if (next) { await tick(); input?.focus({ preventScroll: true }); }
    else { model.term = ''; if (district) onSearch(''); }
  }
  // Bits 2.x has no Item onSelect. Same-value activation closes without
  // onValueChange, so supplement only that case (changed values use Bits).
  function reselected(value: string, disabled = false) {
    if (shipping && open && !model.disabled && !disabled && value === model.value)
      onChoose(value, true);
  }
  function keyboardReselected(event: KeyboardEvent) {
    if (event.key !== 'Enter' || event.isComposing || event.defaultPrevented) return;
    const id = (event.currentTarget as HTMLInputElement).getAttribute('aria-activedescendant');
    const item = id ? document.getElementById(id) : null;
    if (item?.matches('[data-combobox-item]:not([data-disabled])'))
      reselected(item.getAttribute('data-value') || '');
  }
  async function escaped() {
    // Bits closes after this callback. Its content intentionally suppresses
    // close autofocus, so restore the trigger only for a keyboard dismissal.
    await tick();
    if (!open && anchor?.isConnected) anchor.focus({ preventScroll: true });
  }
</script>

<Combobox.Root type="single" allowDeselect={false} bind:value={model.value} bind:open onValueChange={onChoose} onOpenChange={opened} disabled={model.disabled} items={model.options}>
  <div class="kiriof-buyer-combobox" class:is-open={open} class:is-disabled={model.disabled}>
    <Combobox.Trigger bind:ref={anchor} class="kiriof-buyer-combobox-trigger" aria-labelledby={model.labelId} aria-label={model.labelId ? undefined : model.label} aria-required={model.required as 'true' | 'false' | undefined} aria-invalid={model.invalid as 'true' | 'false' | undefined} aria-describedby={model.describedBy}>
      {#if shipping && selected && selected.value}<CourierOption option={selected} {logos} />{:else}<span>{selected?.label || strings.selectOption || 'Select Option'}</span>{/if}
      <span class="kiriof-buyer-combobox-arrow" aria-hidden="true"></span>
    </Combobox.Trigger>
    {#if district && model.value}
      <button type="button" class="kiriof-buyer-combobox-clear" disabled={model.disabled} aria-label={strings.clearSelection || 'Clear selection'} onclick={() => onChoose('')}>×</button>
    {/if}
    <div class="kiriof-buyer-combobox-status" role="status" aria-live="polite">{model.status}</div>
  </div>
  <Combobox.Portal>
    <Combobox.Content customAnchor={anchor} align="start" sideOffset={0} strategy="fixed" sticky="always" onEscapeKeydown={escaped} class="kiriof-buyer-combobox-content">
      <Combobox.Input bind:ref={input} class="kiriof-buyer-combobox-input" aria-labelledby={model.labelId} aria-label={model.labelId ? undefined : model.label} aria-required={model.required as 'true' | 'false' | undefined} aria-invalid={model.invalid as 'true' | 'false' | undefined} aria-describedby={model.describedBy} aria-busy={model.loading} placeholder={strings.search || 'Search'} onkeydown={keyboardReselected} oninput={(event) => { model.term = event.currentTarget.value; onSearch(model.term); }} />
      <div class="kiriof-buyer-combobox-results">
        {#each filtered.filter((option) => option.value !== '') as option (option.value)}
          <Combobox.Item value={option.value} label={option.label} disabled={option.disabled} class="kiriof-buyer-combobox-option" onpointerup={(event) => { if (!event.defaultPrevented && event.pointerType !== 'touch') reselected(option.value, option.disabled); }} onclick={(event) => { if (!event.defaultPrevented) reselected(option.value, option.disabled); }}>
            {#if shipping}<CourierOption {option} {logos} />{:else}{option.label}{/if}
          </Combobox.Item>
        {:else}<div class="kiriof-buyer-combobox-empty">{model.status || strings.noResults || 'No results found'}</div>{/each}
        {#if model.error}<button type="button" class="kiriof-buyer-combobox-retry" onclick={() => onSearch(model.term)}>{strings.retry || 'Try again'}</button>{/if}
      </div>
    </Combobox.Content>
  </Combobox.Portal>
</Combobox.Root>
