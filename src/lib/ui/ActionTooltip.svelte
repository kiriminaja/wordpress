<script lang="ts">
  import type { Snippet } from 'svelte';
  import * as Tooltip from '$lib/components/ui/tooltip';

  let {
    label,
    disabled = false,
    side = 'top',
    children,
  }: {
    label: string;
    disabled?: boolean;
    side?: 'top' | 'right' | 'bottom' | 'left';
    children: Snippet;
  } = $props();
</script>

<Tooltip.Provider delayDuration={300}>
  <Tooltip.Root {disabled}>
    <Tooltip.Trigger>
      {#snippet child({ props })}
        <span {...props} class="kiriof-tooltip-trigger">{@render children()}</span>
      {/snippet}
    </Tooltip.Trigger>
    <Tooltip.Content {side}>{label}</Tooltip.Content>
  </Tooltip.Root>
</Tooltip.Provider>
