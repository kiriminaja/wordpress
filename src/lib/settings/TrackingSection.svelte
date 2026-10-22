<script lang="ts">
  import { IconExternalLink, IconFileDescription } from '@tabler/icons-svelte';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import * as Empty from '$lib/components/ui/empty';
  import type { TrackingBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';

  let { bootstrap }: { bootstrap: TrackingBootstrap } = $props();
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings} />
<div class="kiriof-shadcn flex flex-col gap-4 kiriof-settings-content">
  <Card.Root class="border border-border">
    <Card.Header>
      <Card.Title>{bootstrap.i18n.guideTitle}</Card.Title>
    </Card.Header>
    <Card.Content class="flex flex-col gap-4">
      <ol class="flex list-decimal flex-col gap-2 pl-5 marker:text-muted-foreground">
        {#each bootstrap.i18n.guideSteps as step}<li>{step}</li>{/each}
      </ol>
      <code class="rounded-md bg-muted px-3 py-2 text-muted-foreground">[kiriminaja-tracking-front-page]</code>
    </Card.Content>
  </Card.Root>

  <Card.Root class="border border-border">
    <Card.Header>
      <Card.Title>{bootstrap.i18n.pagesTitle}</Card.Title>
    </Card.Header>
    <Card.Content>
      {#if bootstrap.pages.length === 0}
        <Empty.Root>
          <Empty.Header>
            <Empty.Media variant="icon"><IconFileDescription aria-hidden="true" /></Empty.Media>
            <Empty.Title>{bootstrap.i18n.emptyTitle}</Empty.Title>
            <Empty.Description>{bootstrap.i18n.emptyDescription}</Empty.Description>
          </Empty.Header>
        </Empty.Root>
      {:else}
        <div class="flex flex-col gap-3">
          {#each bootstrap.pages as page (page.id)}
            <article>
              <Card.Root size="sm" class="border border-border">
                <Card.Content class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div class="flex min-w-0 flex-col gap-1">
                    <Card.Title>{page.title}</Card.Title>
                    <Card.Description class="break-all">{page.url}</Card.Description>
                  </div>
                  <div class="flex shrink-0 flex-wrap gap-2">
                    <Button variant="outline" size="sm" href={page.url} target="_blank" rel="noopener noreferrer">
                      {bootstrap.i18n.view}<IconExternalLink data-icon="inline-end" aria-hidden="true" />
                    </Button>
                    <Button variant="outline" size="sm" href={page.editUrl}>{bootstrap.i18n.edit}</Button>
                  </div>
                </Card.Content>
              </Card.Root>
            </article>
          {/each}
        </div>
      {/if}
    </Card.Content>
  </Card.Root>
</div>
