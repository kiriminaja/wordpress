<script lang="ts">
  import { mergeProps } from 'bits-ui';
  import { Button, type ButtonProps } from '$lib/components/ui/button';
  import * as Tooltip from '$lib/components/ui/tooltip';

  let { label, children, ...buttonProps }: ButtonProps & { label: string } = $props();
</script>

<Tooltip.Provider delayDuration={300}>
  <Tooltip.Root>
    <Tooltip.Trigger>
      {#snippet child({ props })}
        <Button {...mergeProps(props, buttonProps)} aria-label={label}>
          {@render children?.()}
        </Button>
      {/snippet}
    </Tooltip.Trigger>
    <Tooltip.Content>{label}</Tooltip.Content>
  </Tooltip.Root>
</Tooltip.Provider>
