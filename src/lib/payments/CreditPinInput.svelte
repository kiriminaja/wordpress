<script lang="ts">
  import { REGEXP_ONLY_DIGITS } from 'bits-ui';
  import * as Field from '$lib/components/ui/field';
  import * as InputOTP from '$lib/components/ui/input-otp';

  let { id, value = $bindable(''), disabled = false, invalid = false, label, description }: {
    id: string; value?: string; disabled?: boolean; invalid?: boolean; label: string; description: string;
  } = $props();

  let focused = $state(false);
  let revealedIndex = $state<number | null>(null);
  let previousValue = '';
  let pasting = false;

  // Reveal only a single newly typed/replaced digit, never a pasted PIN or an
  // existing digit merely selected with the keyboard. The native input remains
  // a password input; only the corresponding presentation slot is unmasked.
  function handleInput(event: Event): void {
    const input = event.currentTarget as HTMLInputElement;
    const next = input.value;
    const caretIndex = Math.max(0, (input.selectionStart ?? next.length) - 1);
    const isInsertion = next.length === previousValue.length + 1;
    const caretMatches = next.slice(0, caretIndex) === previousValue.slice(0, caretIndex) &&
      next.slice(caretIndex + 1) === previousValue.slice(caretIndex + (isInsertion ? 0 : 1));
    let index = caretIndex;
    if (!caretMatches) {
      index = 0;
      while (index < previousValue.length && previousValue[index] === next[index]) index += 1;
    }
    const singleEdit = next.length <= 6 && /^\d+$/.test(next) && (
      (next.length === previousValue.length + 1 && next.slice(index + 1) === previousValue.slice(index)) ||
      (next.length === previousValue.length && next.slice(index + 1) === previousValue.slice(index + 1) && index < next.length)
    );
    revealedIndex = focused && !disabled && !pasting && singleEdit ? index : null;
    pasting = false;
    previousValue = next;
  }

  function handleKeydown(event: KeyboardEvent): void {
    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Tab'].includes(event.key) || event.ctrlKey || event.metaKey) {
      revealedIndex = null;
    }
  }

  $effect(() => {
    // Back/close, quote refresh and externally cleared PINs cannot leave a
    // revealed character behind, even if the input retains focus.
    if (value !== previousValue) { previousValue = value; revealedIndex = null; }
    if (disabled) revealedIndex = null;
  });
</script>

<Field.Group class="kiriof-credit-pin">
  <Field.Field data-invalid={invalid || undefined} data-disabled={disabled || undefined}>
    <Field.Label for={id} class="sr-only">{label}</Field.Label>
    <InputOTP.Root inputId={id} maxlength={6} pattern={REGEXP_ONLY_DIGITS} bind:value {disabled} type="password" autocomplete="off" inputmode="numeric" aria-label={label} aria-describedby={`${id}-help`} aria-invalid={invalid || undefined} pushPasswordManagerStrategy="none" oninput={handleInput} onkeydown={handleKeydown} onfocus={() => { focused = true; revealedIndex = null; }} onblur={() => { focused = false; revealedIndex = null; pasting = false; }} onpointerdown={() => { revealedIndex = null; }} onpaste={() => { pasting = true; revealedIndex = null; }}>
      {#snippet children({ cells })}
        {#each cells as cell, index (cell)}<InputOTP.Slot {cell} mask={!focused || disabled || revealedIndex !== index} aria-invalid={invalid || undefined} />{/each}
      {/snippet}
    </InputOTP.Root>
    <Field.Description id={`${id}-help`} class="sr-only m-0">{description}</Field.Description>
  </Field.Field>
</Field.Group>
