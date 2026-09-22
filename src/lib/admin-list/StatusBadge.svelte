<script lang="ts">
  let { label, tone = 'neutral' }: { label: string; tone?: 'success' | 'warning' | 'info' | 'neutral' } = $props();
</script>

<span class={`kiriof-status-badge ${tone}`}>{label}</span>
