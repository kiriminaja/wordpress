<script lang="ts">
  import { IconExternalLink, IconFileDescription } from '@tabler/icons-svelte';
  import { Button } from 'bits-ui';
  import type { TrackingBootstrap } from './types';
  import SettingsToolbar from './SettingsToolbar.svelte';

  let { bootstrap }: { bootstrap: TrackingBootstrap } = $props();
</script>

<SettingsToolbar toolbar={bootstrap.toolbar} />
<div class="kiriof-technical kiriof-settings-content">
  <section class="kiriof-section-card">
    <h2>{bootstrap.i18n.guideTitle}</h2>
    <ol class="kiriof-guide-list">
      {#each bootstrap.i18n.guideSteps as step}<li>{step}</li>{/each}
    </ol>
    <code class="kiriof-shortcode">[kiriminaja-tracking-front-page]</code>
  </section>

  <section class="kiriof-section-card">
    <h2>{bootstrap.i18n.pagesTitle}</h2>
    {#if bootstrap.pages.length === 0}
      <div class="kiriof-empty-state">
        <IconFileDescription size={34} stroke={1.5} aria-hidden="true" />
        <strong>{bootstrap.i18n.emptyTitle}</strong>
        <span>{bootstrap.i18n.emptyDescription}</span>
      </div>
    {:else}
      <div class="kiriof-tracking-pages">
        {#each bootstrap.pages as page}
          <article>
            <div><strong>{page.title}</strong><span>{page.url}</span></div>
            <div class="kiriof-section-actions">
              <Button.Root class="button button-small" href={page.url} target="_blank" rel="noopener noreferrer">{bootstrap.i18n.view}<IconExternalLink size={14} /></Button.Root>
              <Button.Root class="button button-small" href={page.editUrl}>{bootstrap.i18n.edit}</Button.Root>
            </div>
          </article>
        {/each}
      </div>
    {/if}
  </section>
</div>
