<script lang="ts">
  import { onMount } from 'svelte';

  let { fallback }: { fallback: HTMLElement } = $props();
  let container: HTMLDivElement;

  onMount(() => {
    container.append(...Array.from(fallback.childNodes));
    fallback.remove();
  });
</script>

<div bind:this={container} class="kiriof-coupon-panel-svelte"></div>
