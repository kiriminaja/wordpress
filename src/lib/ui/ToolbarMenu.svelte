<script lang="ts">
  import { IconDotsVertical, IconExternalLink } from '@tabler/icons-svelte';
  import type { ToolbarMenu } from './toolbar';

  let { menu }: { menu: ToolbarMenu } = $props();

  let open = $state(false);
  let container: HTMLDivElement | null = $state(null);

  function toggle(): void {
    open = !open;
  }

  function close(): void {
    open = false;
  }

  function onDocumentClick(event: MouseEvent): void {
    if (container && !container.contains(event.target as Node)) close();
  }

  function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') close();
  }

  $effect(() => {
    if (!open) return;
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    return () => {
      document.removeEventListener('click', onDocumentClick);
      document.removeEventListener('keydown', onKeydown);
    };
  });
</script>

<div class="kiriof-toolbar-menu" bind:this={container}>
  <button
    type="button"
    class="kiriof-toolbar-menu__trigger"
    aria-label={menu.label}
    aria-haspopup="menu"
    aria-expanded={open}
    onclick={toggle}
  >
    <IconDotsVertical size={18} aria-hidden="true" />
  </button>
  {#if open}
    <div class="kiriof-toolbar-menu__dropdown" role="menu">
      {#each menu.items as item}
        <a
          href={item.href}
          target="_blank"
          rel="noopener noreferrer"
          role="menuitem"
          onclick={close}
        >
          <span>{item.label}</span>
          <IconExternalLink size={14} aria-hidden="true" />
        </a>
      {/each}
    </div>
  {/if}
</div>
