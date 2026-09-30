<script lang="ts">
  import type { HTMLInputTypeAttribute } from 'svelte/elements';
  import { Input } from '$lib/components/ui/input';

  type InputType = Exclude<HTMLInputTypeAttribute, 'file'>;

  let {
    value = $bindable(''),
    type = 'text',
    formatNumber = false,
    prefix,
    suffix,
    class: className = '',
    id,
    name,
    placeholder,
    disabled = false,
    min,
    max,
    step,
    inputmode,
  }: {
    value?: string;
    type?: Exclude<HTMLInputTypeAttribute, 'file'>;
    formatNumber?: boolean;
    prefix?: import('svelte').Snippet;
    suffix?: import('svelte').Snippet;
    class?: string;
    id?: string;
    name?: string;
    placeholder?: string;
    disabled?: boolean;
    min?: number | string;
    max?: number | string;
    step?: number | string;
    inputmode?: 'numeric' | 'decimal' | 'text' | 'tel' | 'email' | 'url' | 'search' | 'none';
  } = $props();

  let displayValue = $state('');

  function format(value: string): string {
    const digits = value.replace(/\D/g, '');
    return digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
  }

  $effect(() => {
    displayValue = formatNumber ? format(value) : value;
  });

  function update(event: Event): void {
    const nextValue = (event.currentTarget as HTMLInputElement).value;
    value = formatNumber ? nextValue.replace(/\D/g, '') : nextValue;
  }
</script>

<div class={`kiriof-input flex items-center rounded-lg border border-border bg-background focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 ${className}`}>
  {#if prefix}<span class="flex shrink-0 items-center pl-2 text-muted-foreground">{@render prefix()}</span>{/if}
  <Input {id} {name} {type} {placeholder} {disabled} {min} {max} {step} {inputmode} value={displayValue} oninput={update} class="!border-0 !bg-transparent !shadow-none focus-visible:!border-0 focus-visible:!ring-0" />
  {#if suffix}<span class="flex shrink-0 items-center pr-2 text-muted-foreground">{@render suffix()}</span>{/if}
</div>
