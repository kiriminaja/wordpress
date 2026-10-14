<script lang="ts">
  import { Checkbox as CheckboxPrimitive } from 'bits-ui';
  import { IconCheck, IconMinus } from '@tabler/icons-svelte';
  import { cn, type WithoutChildrenOrChild } from '$lib/utils';

  let {
    ref = $bindable(null),
    checked = $bindable(false),
    indeterminate = $bindable(false),
    class: className,
    ...restProps
  }: WithoutChildrenOrChild<CheckboxPrimitive.RootProps> = $props();
</script>

<CheckboxPrimitive.Root
  bind:ref
  bind:checked
  bind:indeterminate
  {...restProps}
  data-slot="checkbox"
  data-kiriof-checkbox
  class={cn(
    'peer relative !m-0 !inline-flex !size-4 !min-h-4 !min-w-4 shrink-0 !items-center !justify-center !rounded !border !border-border !bg-background !p-0 !text-primary-foreground !appearance-none outline-none transition-colors duration-150 ease-out data-[state=checked]:!border-primary data-[state=checked]:!bg-primary data-[state=indeterminate]:!border-primary data-[state=indeterminate]:!bg-primary focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:!border-destructive aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50 after:absolute after:-inset-x-3 after:-inset-y-2 motion-reduce:transition-none',
    className,
  )}
>
  {#snippet children({ checked, indeterminate })}
    <span data-slot="checkbox-indicator" class="pointer-events-none relative grid size-3.5 place-items-center" aria-hidden="true">
      <IconCheck
        stroke={3}
        class={cn(
          'absolute block size-3 origin-center transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-none',
          checked && !indeterminate ? 'scale-100 opacity-100' : 'scale-75 opacity-0',
        )}
      />
      <IconMinus
        stroke={3}
        class={cn(
          'absolute block size-3 origin-center transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-none',
          indeterminate ? 'scale-100 opacity-100' : 'scale-75 opacity-0',
        )}
      />
    </span>
  {/snippet}
</CheckboxPrimitive.Root>
