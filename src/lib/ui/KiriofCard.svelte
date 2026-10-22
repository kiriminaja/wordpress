<script lang="ts">
  import * as Card from '$lib/components/ui/card';
  import { cn } from '$lib/utils';

  let {
    class: className = '',
    children,
  }: {
    class?: string;
    children?: import('svelte').Snippet;
  } = $props();
</script>

<Card.Root class={cn('kiriof-card', className)}>{@render children?.()}</Card.Root>
