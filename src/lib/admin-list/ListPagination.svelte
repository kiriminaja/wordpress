<script lang="ts">
  let { page, totalPages, label, onChange }: { page: number; totalPages: number; label: string; onChange: (page: number) => void } = $props();
</script>

{#if totalPages > 1}
  <div class="kiriof-pagination">
    <button type="button" class="button" disabled={page <= 1} onclick={() => onChange(page - 1)} aria-label="Previous page">‹</button>
    <span>{page} {label} {totalPages}</span>
    <button type="button" class="button" disabled={page >= totalPages} onclick={() => onChange(page + 1)} aria-label="Next page">›</button>
  </div>
{/if}
