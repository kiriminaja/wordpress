<script lang="ts">
  import * as Tabs from '$lib/components/ui/tabs';

  export type WorkspaceTab = {
    value: string;
    label: string;
    count?: number;
    disabled?: boolean;
    title?: string;
  };

  let {
    value,
    tabs,
    onChange,
  }: {
    value: string;
    tabs: WorkspaceTab[];
    onChange: (value: string) => void;
  } = $props();
</script>

<Tabs.Root value={value} onValueChange={onChange} class="kiriof-workspace-tabs">
  <Tabs.List class="kiriof-workspace-tabs__list">
    {#each tabs as tab (tab.value)}
      <Tabs.Trigger value={tab.value} disabled={tab.disabled} title={tab.title} class="kiriof-workspace-tabs__trigger">
        {tab.label}{tab.count && tab.count > 0 ? ` (${tab.count})` : ''}
      </Tabs.Trigger>
    {/each}
  </Tabs.List>
</Tabs.Root>
