<script lang="ts">
  import { IconCreditCard, IconQrcode } from '@tabler/icons-svelte';
  import * as Field from '$lib/components/ui/field';
  import * as RadioGroup from '$lib/components/ui/radio-group';
  import { cn } from '$lib/utils';
  import type { PaymentMethodOption } from './types';

  let { idPrefix, value = $bindable(''), options, label, balanceLabel, disabled = false, invalid = false, required = false, onValueChange }: {
    idPrefix: string; value?: string; options: PaymentMethodOption[]; label: string; balanceLabel: string;
    disabled?: boolean; invalid?: boolean; required?: boolean; onValueChange?: (value: string) => void;
  } = $props();
  function money(amount: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount)}`;
  }
  const orderedOptions = $derived([...options].sort((a, b) => (a.value === 'credit' ? 0 : 1) - (b.value === 'credit' ? 0 : 1)));
</script>

<Field.Group>
  <Field.Set data-disabled={disabled || undefined} data-invalid={invalid || undefined}>
    <Field.Legend id={`${idPrefix}-label`} variant="label">{label}{#if required}<span aria-hidden="true"> *</span>{/if}</Field.Legend>
    <RadioGroup.Root bind:value {onValueChange} {disabled} {required} aria-labelledby={`${idPrefix}-label`} aria-invalid={invalid || undefined} class="kiriof-payment-methods">
      {#each orderedOptions as option (option.value)}
        {@const unavailable = disabled || Boolean(option.disabled)}
        <Field.Label for={`${idPrefix}-${option.value}`} class={cn('kiriof-payment-method-card', value === option.value && 'is-selected', unavailable && 'is-disabled')} aria-disabled={unavailable || undefined}>
          <Field.Field orientation="horizontal" class="kiriof-payment-method-option" data-disabled={unavailable || undefined} data-invalid={invalid || undefined}>
            <span class="kiriof-payment-method-card__icon" aria-hidden="true">{#if option.value === 'credit'}<IconCreditCard />{:else}<IconQrcode />{/if}</span>
            <Field.Content class="kiriof-payment-method-card__copy">
              <Field.Title>{option.title}</Field.Title>
              {#if option.value === 'credit' && typeof option.balance === 'number' && Number.isFinite(option.balance) && option.balance >= 0}
                <Field.Description class="m-0">{balanceLabel} {money(option.balance)}</Field.Description>
              {/if}
              {#if option.description}<Field.Description class="m-0">{option.description}</Field.Description>{/if}
            </Field.Content>
            <RadioGroup.Item id={`${idPrefix}-${option.value}`} value={option.value} disabled={unavailable} aria-label={option.title} aria-invalid={invalid || undefined} />
          </Field.Field>
        </Field.Label>
      {/each}
    </RadioGroup.Root>
  </Field.Set>
</Field.Group>
