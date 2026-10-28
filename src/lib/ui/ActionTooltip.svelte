<script lang="ts">
  import type { Snippet } from 'svelte';
  import * as Tooltip from '$lib/components/ui/tooltip';

  let {
    label,
    disabled = false,
    side = 'top',
    focusable = false,
    children,
  }: {
    label: string;
    disabled?: boolean;
    side?: 'top' | 'right' | 'bottom' | 'left';
    focusable?: boolean;
    children: Snippet;
  } = $props();
</script>

<Tooltip.Provider delayDuration={300}>
  <Tooltip.Root {disabled}>
    <Tooltip.Trigger>
      {#snippet child({ props })}
        {#if focusable}
          <button {...props} type="button" class="kiriof-tooltip-trigger !border-0 !bg-transparent !p-0 !text-left">{@render children()}</button>
        {:else}
          <span {...props} class="kiriof-tooltip-trigger">{@render children()}</span>
        {/if}
      {/snippet}
    </Tooltip.Trigger>
    <Tooltip.Content {side}>{label}</Tooltip.Content>
  </Tooltip.Root>
</Tooltip.Provider>
