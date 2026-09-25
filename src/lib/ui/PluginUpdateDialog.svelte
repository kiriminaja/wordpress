<script lang="ts">
  import { IconExternalLink, IconPackage } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';
  import type { ToolbarUpdate } from './toolbar';

  let {
    update,
  }: {
    update: ToolbarUpdate;
  } = $props();

  let open = $state(false);
</script>

<Dialog.Root bind:open>
  <Dialog.Trigger class="kiriof-plugin-update-trigger" aria-label={`${update.label}: ${update.version}`}>
    <IconPackage size={14} aria-hidden="true" />
    <span>{update.label}</span>
  </Dialog.Trigger>

  <Dialog.Content class="kiriof-shadcn kiriof-plugin-update-dialog">
    <Dialog.Header>
      <div class="kiriof-plugin-update-dialog__heading">
        <span class="kiriof-plugin-update-dialog__icon" aria-hidden="true"><IconPackage size={20} /></span>
        <div>
          <span class="kiriof-plugin-update-dialog__eyebrow">{update.label}</span>
          <Dialog.Title>{update.title}</Dialog.Title>
        </div>
      </div>
      <Dialog.Description>{update.description}</Dialog.Description>
    </Dialog.Header>

    {#if update.details.length > 0}
      <div class="kiriof-plugin-update-dialog__details">
        {#each update.details as detail}
          <p>{detail}</p>
        {/each}
      </div>
    {/if}

    <Dialog.Footer>
      <Button variant="ghost" onclick={() => (open = false)}>{update.closeLabel}</Button>
      <a
        class="kiriof-plugin-update-dialog__secondary"
        href={update.secondaryUrl}
        target={update.secondaryExternal ? '_blank' : undefined}
        rel={update.secondaryExternal ? 'noopener noreferrer' : undefined}
      >{update.secondaryLabel}</a>
      <a
        class="kiriof-plugin-update-dialog__primary"
        href={update.primaryUrl}
        target={update.primaryExternal ? '_blank' : undefined}
        rel={update.primaryExternal ? 'noopener noreferrer' : undefined}
      >{update.primaryLabel}{#if update.primaryExternal}<IconExternalLink size={14} aria-hidden="true" />{/if}</a>
      <a class="kiriof-plugin-update-dialog__dismiss" href={update.dismissUrl}>{update.dismissLabel}</a>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
