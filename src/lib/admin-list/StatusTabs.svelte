<script lang="ts">
  import * as Tabs from '$lib/components/ui/tabs';

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

<Tabs.Root value={value} onValueChange={onChange} aria-label="Status filters">
  <Tabs.List variant="line" class="flex-wrap">
    {#each tabs as tab (tab.value)}
      <Tabs.Trigger value={tab.value}>
        {tab.label} ({tab.count})
      </Tabs.Trigger>
    {/each}
  </Tabs.List>
</Tabs.Root>
