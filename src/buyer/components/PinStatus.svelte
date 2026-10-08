<script lang="ts">
  import type { Readable } from 'svelte/store';
  import type { PinStatusModel } from '../blocks/svelte-bridge';
  let { model }: { model: Readable<PinStatusModel> } = $props();
</script>

<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
  <path d={$model.complete ? 'm5 12 4 4 10-10' : 'M5 5h14v14H5Z'} />
</svg><span class="kiriof-map-screen-reader">{$model.text}</span>
