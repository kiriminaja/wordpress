<script lang="ts">
  import { IconChevronRight } from '@tabler/icons-svelte';
  import type { SettingsToolbar } from './types';

  let {
    toolbar,
    children,
  }: {
    toolbar: SettingsToolbar;
    children?: import('svelte').Snippet;
  } = $props();
</script>

<header class="kiriof-app-toolbar">
  <nav class="kiriof-app-toolbar__breadcrumbs" aria-label="Breadcrumb">
    <a class="kiriof-app-toolbar__home" href={toolbar.rootUrl} aria-label={toolbar.rootLabel}>
      <img src={toolbar.logoUrl} alt="" />
    </a>
    {#if toolbar.title !== toolbar.rootLabel}
      <a href={toolbar.rootUrl}>{toolbar.rootLabel}</a>
      <IconChevronRight size={14} stroke={2} aria-hidden="true" />
    {/if}
    <strong>{toolbar.title}</strong>
  </nav>
  {#if children}
    <div class="kiriof-app-toolbar__actions">
      {@render children()}
    </div>
  {/if}
</header>
