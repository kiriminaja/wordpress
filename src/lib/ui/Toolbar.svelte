<script lang="ts">
  import { IconChevronRight } from '@tabler/icons-svelte';
  import type { ToolbarConfig } from './toolbar';

  let {
    toolbar,
    onNavigate,
    children,
  }: {
    toolbar: ToolbarConfig;
    onNavigate?: (href: string) => void;
    children?: import('svelte').Snippet;
  } = $props();

  function navigate(event: MouseEvent, href: string): void {
    if (!onNavigate) return;
    event.preventDefault();
    onNavigate(href);
  }
</script>

<header class="kiriof-app-toolbar">
  <nav class="kiriof-app-toolbar__breadcrumbs" aria-label="Breadcrumb">
    <a class="kiriof-app-toolbar__home" href={toolbar.rootUrl} aria-label={toolbar.rootLabel} onclick={(event) => navigate(event, toolbar.rootUrl)}>
      <img src={toolbar.logoUrl} alt="" />
    </a>
    {#if toolbar.title !== toolbar.rootLabel}
      <a href={toolbar.rootUrl} onclick={(event) => navigate(event, toolbar.rootUrl)}>{toolbar.rootLabel}</a>
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
