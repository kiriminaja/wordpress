<script lang="ts">
  let {
    tabs,
    value,
    onChange,
  }: {
    tabs: Array<{ value: string; label: string; count: number }>;
    value: string;
    onChange: (value: string) => void;
  } = $props();
</script>

<nav class="kiriof-status-tabs" aria-label="Status filters">
  {#each tabs as tab}
    <button type="button" class:current={tab.value === value} aria-current={tab.value === value ? 'page' : undefined} onclick={() => onChange(tab.value)}>
      {tab.label} <span>({tab.count})</span>
    </button>
  {/each}
</nav>
