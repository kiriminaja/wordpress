<script lang="ts">
  import type { TrackingState } from '../tracking/types';
  let { state }: { state: TrackingState } = $props();
</script>

{#each state.data.histories as history}
  <tr><td>{history.created_at}</td><td>{history.status}</td></tr>
{/each}
