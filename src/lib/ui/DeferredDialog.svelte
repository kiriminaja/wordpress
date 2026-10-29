<script lang="ts" generics="T">
  import { onDestroy, untrack, type Snippet } from 'svelte';
  import KiriofDialog from './KiriofDialog.svelte';

  let { active, load, i18n, onClose, children }: {
    active: boolean;
    load: () => Promise<T>;
    i18n: Record<string, string>;
    onClose: () => void;
    children: Snippet<[T]>;
  } = $props();
  let loaded = $state.raw<T | undefined>();
  let failed = $state(false);
  let pending = false;
  let generation = 0;
  let disposed = false;

  async function request(): Promise<void> {
    if (pending || disposed || loaded !== undefined) return;
    const current = ++generation;
    pending = true;
    failed = false;
    try {
      const result = await untrack(load);
      if (!disposed && current === generation) loaded = result;
    } catch {
      if (!disposed && current === generation) failed = true;
    } finally {
      if (current === generation) pending = false;
    }
  }

  $effect(() => {
    if (active) untrack(() => { void request(); });
  });
  onDestroy(() => { disposed = true; generation++; });
</script>

{#if loaded !== undefined}
  {@render children(loaded)}
{:else if active}
  <KiriofDialog
    open={active}
    title={failed ? (i18n.actionError || 'Unable to load the workspace.') : (i18n.loading || 'Loading…')}
    primaryLabel={failed ? (i18n.retry || 'Retry') : (i18n.loading || 'Loading…')}
    primaryDisabled={!failed}
    secondaryLabel={i18n.close || 'Close'}
    onPrimary={request}
    onSecondary={onClose}
    onOpenChange={(open) => { if (!open) onClose(); }}
  />
{/if}
