<script lang="ts" generics="T">
  import { onDestroy, onMount, tick, untrack, type Snippet } from 'svelte';

  let {
    load,
    i18n,
    children,
    compact = false,
  }: {
    load: () => Promise<T>;
    i18n: Record<string, string | undefined>;
    children: Snippet<[T]>;
    compact?: boolean;
  } = $props();

  let loaded = $state.raw<T | undefined>();
  let failed = $state(false);
  let pending = $state(true);
  let generation = 0;
  let disposed = false;
  let container: HTMLDivElement;

  async function request(): Promise<void> {
    const restoreFocus = container?.contains(document.activeElement);
    const current = ++generation;
    pending = true;
    failed = false;
    try {
      const result = await untrack(load);
      if (!disposed && current === generation) {
        loaded = result;
        await tick();
        if (restoreFocus && !disposed && current === generation &&
          (document.activeElement === document.body || container?.contains(document.activeElement))) {
          container?.querySelector<HTMLElement>('button, input, [tabindex="0"]')?.focus();
        }
      }
    } catch {
      if (!disposed && current === generation) failed = true;
    } finally {
      if (!disposed && current === generation) pending = false;
    }
  }

  onMount(() => { void request(); });
  onDestroy(() => {
    disposed = true;
    generation++;
  });
</script>

<div bind:this={container}>
{#if loaded !== undefined}
  {@render children(loaded)}
{:else if pending}
  <div class="flex items-center justify-center text-sm text-muted-foreground {compact ? 'min-h-9' : 'min-h-40'}" role="status" aria-live="polite">
    {i18n.loading || 'Loading…'}
  </div>
{:else if failed}
  <div class="flex flex-col items-center justify-center gap-3 text-sm text-muted-foreground {compact ? 'min-h-9' : 'min-h-40'}" role="alert">
    <span>{i18n.actionError || 'Unable to load this section.'}</span>
    <button class="rounded-md border border-border px-3 py-1.5 text-sm hover:bg-muted" type="button" onclick={request}>
      {i18n.retry || 'Retry'}
    </button>
  </div>
{/if}
</div>
