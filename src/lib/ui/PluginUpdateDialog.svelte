<script lang="ts">
  import { IconExternalLink, IconPackage } from '@tabler/icons-svelte';
  import { badgeVariants } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';
  import { cn } from '$lib/utils';
  import type { ToolbarUpdate } from './toolbar';

  let {
    update,
  }: {
    update: ToolbarUpdate;
  } = $props();

  const id = $props.id();
  const changelogId = `${id}-changelog`;
</script>

<Dialog.Root>
  <Dialog.Trigger
    class={cn(badgeVariants({ tone: 'warning' }), 'kiriof-plugin-update-trigger min-h-6 min-w-6 max-w-40 cursor-pointer appearance-none border-solid shadow-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:max-w-60')}
    aria-label={update.label}
  >
    <IconPackage data-icon="inline-start" aria-hidden="true" />
    <span class="min-w-0 truncate">{update.label}</span>
  </Dialog.Trigger>

  <Dialog.Content class="kiriof-shadcn kiriof-plugin-update-dialog" closeLabel={update.closeLabel}>
    <Dialog.Header class="pr-6">
      <Dialog.Title>{update.title}</Dialog.Title>
      <Dialog.Description>{update.description}</Dialog.Description>
    </Dialog.Header>

    <section class="grid min-h-0 gap-2" aria-labelledby={changelogId}>
      <h3 id={changelogId} class="text-sm font-medium">{update.changelogLabel}</h3>
      <!-- svelte-ignore a11y_no_noninteractive_tabindex (Keyboard users must be able to scroll long release notes.) -->
      <div
        role="region"
        aria-labelledby={changelogId}
        tabindex="0"
        class="max-h-64 overflow-y-auto whitespace-pre-line break-words rounded-md border bg-muted/30 p-3 text-sm leading-relaxed focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >{update.changelog || update.changelogUnavailable}</div>
    </section>

    <Dialog.Footer>
      <Button href={update.dismissUrl} variant="outline">{update.dismissLabel}</Button>
      <Button
        href={update.primaryUrl}
        target={update.primaryExternal ? '_blank' : undefined}
        rel={update.primaryExternal ? 'noopener noreferrer' : undefined}
      >
        {update.primaryLabel}
        {#if update.primaryExternal}<IconExternalLink data-icon="inline-end" aria-hidden="true" />{/if}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
