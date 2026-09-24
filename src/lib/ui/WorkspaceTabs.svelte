<script lang="ts">
  import * as Tabs from '$lib/components/ui/tabs';
  import ActionTooltip from './ActionTooltip.svelte';

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
      {#if tab.title}
        <ActionTooltip label={tab.title}>
          <Tabs.Trigger value={tab.value} disabled={tab.disabled} class="kiriof-workspace-tabs__trigger">{tab.label}{tab.count && tab.count > 0 ? ` (${tab.count})` : ''}</Tabs.Trigger>
        </ActionTooltip>
      {:else}
        <Tabs.Trigger value={tab.value} disabled={tab.disabled} class="kiriof-workspace-tabs__trigger">{tab.label}{tab.count && tab.count > 0 ? ` (${tab.count})` : ''}</Tabs.Trigger>
      {/if}
    {/each}
  </Tabs.List>
</Tabs.Root>
