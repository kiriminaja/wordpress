<script lang="ts">
  import { REGEXP_ONLY_DIGITS } from 'bits-ui';
  import * as Field from '$lib/components/ui/field';
  import * as InputOTP from '$lib/components/ui/input-otp';

  let { id, value = $bindable(''), disabled = false, invalid = false, label, description }: {
    id: string; value?: string; disabled?: boolean; invalid?: boolean; label: string; description: string;
  } = $props();
</script>

<Field.Group class="kiriof-credit-pin">
  <Field.Field data-invalid={invalid || undefined} data-disabled={disabled || undefined}>
    <Field.Label for={id}>{label}</Field.Label>
    <InputOTP.Root inputId={id} maxlength={6} pattern={REGEXP_ONLY_DIGITS} bind:value {disabled} type="password" autocomplete="off" inputmode="numeric" aria-label={label} aria-describedby={`${id}-help`} aria-invalid={invalid || undefined} pushPasswordManagerStrategy="none">
      {#snippet children({ cells })}
        <InputOTP.Group>
          {#each cells.slice(0, 3) as cell (cell)}<InputOTP.Slot {cell} mask aria-invalid={invalid || undefined} />{/each}
        </InputOTP.Group>
        <InputOTP.Separator />
        <InputOTP.Group>
          {#each cells.slice(3, 6) as cell (cell)}<InputOTP.Slot {cell} mask aria-invalid={invalid || undefined} />{/each}
        </InputOTP.Group>
      {/snippet}
    </InputOTP.Root>
    <Field.Description id={`${id}-help`}>{description}</Field.Description>
  </Field.Field>
</Field.Group>
