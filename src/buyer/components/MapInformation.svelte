<script lang="ts">
  import type { Readable } from 'svelte/store';
  import type { MapInformationModel } from '../blocks/svelte-bridge';
  let { model }: { model: Readable<MapInformationModel> } = $props();
</script>

<p class="kiriof-buyer-map__optional">{$model.optional}</p>
{#if $model.hasCoverage}
  <p class="kiriof-buyer-map__coverage">{$model.coverage}</p>
{/if}
