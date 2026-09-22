<script lang="ts">
  import { IconCheck } from '@tabler/icons-svelte';
  import { Button } from 'bits-ui';

  let {
    index,
    label,
    stepKey,
    current = false,
    done = false,
    onclick,
  }: {
    index: number;
    label: string;
    stepKey: string;
    current?: boolean;
    done?: boolean;
    onclick: () => void;
  } = $props();

  const classes = $derived(
    [
      'kiriof-onboarding__progress-step',
      current ? 'is-current' : '',
      done ? 'is-done' : '',
    ]
      .filter(Boolean)
      .join(' '),
  );
</script>

<Button.Root
  type="button"
  class={classes}
  data-step-target={stepKey}
  aria-current={current ? 'step' : undefined}
  {onclick}
>
  <span class="kiriof-onboarding__progress-index" aria-hidden="true">
    {#if done}
      <IconCheck size={16} stroke={2.5} />
    {:else}
      {index}
    {/if}
  </span>
  <span>{label}</span>
</Button.Root>
