<script lang="ts">
  import type { Readable } from 'svelte/store';
  import type { MapInformationModel } from '../blocks/svelte-bridge';
  let { model }: { model: Readable<MapInformationModel> } = $props();
</script>

{#if $model.hasCoverage}
  <span class="kiriof-buyer-map__coverage" title={$model.coverage} aria-label={$model.coverage}>
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 10 18H2L12 3Z" /><path d="M12 9v4m0 3h.01" /></svg>
    {$model.badge || 'Instant ≤ 40 km'}
  </span>
{/if}
