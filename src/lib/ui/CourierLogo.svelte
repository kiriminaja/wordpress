<script lang="ts">
  import { IconTruck } from '@tabler/icons-svelte';
  import { courierImage } from '$lib/transactions/courier-images';
  import { cn } from '$lib/utils';

  let {
    code,
    name = '',
    service = '',
    class: className = '',
  }: {
    code: string;
    name?: string;
    service?: string;
    class?: string;
  } = $props();
  const source = $derived(courierImage(code, name || service));
</script>

{#if source}
  <img
    src={source}
    alt={name}
    class={cn(
      'inline-flex h-9 aspect-video shrink-0 box-border rounded border border-border bg-white object-contain p-1',
      className,
    )}
  />
{:else}
  <span
    class={cn(
      'inline-flex h-9 aspect-video shrink-0 items-center justify-center rounded border border-border bg-muted text-muted-foreground',
      className,
    )}
    aria-label={name || code}
  >
    <IconTruck class="size-5" aria-hidden="true" />
  </span>
{/if}
