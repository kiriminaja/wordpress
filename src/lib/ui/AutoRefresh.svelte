<script lang="ts" module>
  /**
   * Auto-refresh countdown, mirroring the kaj-shopify-plugin
   * `app/components/transactions/AutoRefresh.tsx` contract:
   * a refresh button showing the live countdown plus a popover to pick
   * the interval (1 / 3 / 5 minutes). The chosen interval persists in
   * localStorage so it survives page reloads.
   */

  export type AutoRefreshInterval = { label: string; value: number };

  export const AUTO_REFRESH_DEFAULT = 60;

  export const AUTO_REFRESH_INTERVALS: AutoRefreshInterval[] = [
    { label: '1 minute', value: 60 },
    { label: '3 minutes', value: 180 },
    { label: '5 minutes', value: 300 },
  ];
</script>

<script lang="ts">
  import { onDestroy } from 'svelte';
  import { IconChevronDown, IconRefresh } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as ButtonGroup from '$lib/components/ui/button-group';
  import * as Popover from '$lib/components/ui/popover';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';

  let {
    storageKey,
    loading = false,
    disabled = false,
    hint,
    options = AUTO_REFRESH_INTERVALS,
    onRefresh,
  }: {
    storageKey: string;
    loading?: boolean;
    disabled?: boolean;
    hint: string;
    options?: AutoRefreshInterval[];
    onRefresh: () => void;
  } = $props();

  function readInterval(): number {
    try {
      const raw = window.localStorage.getItem(storageKey);
      const parsed = raw ? Number.parseInt(raw, 10) : Number.NaN;
      if (Number.isFinite(parsed) && options.some((option) => option.value === parsed)) return parsed;
    } catch {
      // storage unavailable (private mode) — fall through to default.
    }
    return AUTO_REFRESH_DEFAULT;
  }

  let interval = $state(AUTO_REFRESH_DEFAULT);
  let countdown = $state(AUTO_REFRESH_DEFAULT);
  let popoverOpen = $state(false);
  let timer: number | null = null;

  // Read the persisted interval after mount so SSR/hydration stays deterministic.
  $effect(() => {
    const persisted = readInterval();
    interval = persisted;
    countdown = persisted;
  });

  function stop(): void {
    if (timer !== null) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  function start(seconds: number): void {
    stop();
    if (disabled) return;
    timer = window.setInterval(() => {
      if (document.hidden || loading) return;
      countdown -= 1;
      if (countdown <= 0) {
        countdown = interval;
        if (!disabled && !loading) onRefresh();
      }
    }, 1000);
  }

  function pick(seconds: number): void {
    interval = seconds;
    countdown = seconds;
    try {
      window.localStorage.setItem(storageKey, String(seconds));
    } catch {
      // Non-fatal: countdown still works for this page view.
    }
    popoverOpen = false;
    start(seconds);
  }

  // (Re)start when enabled/loading state flips; pause while a modal owns
  // the page or a navigation is already in flight.
  $effect(() => {
    if (disabled || loading) stop();
    else start(interval);
  });

  // Reset the countdown whenever fresh data lands (page reload completes).
  $effect(() => {
    if (loading) return;
    countdown = interval;
  });

  onDestroy(stop);

  const minutes = $derived(Math.floor(countdown / 60));
  const seconds = $derived(countdown % 60);
  const description = $derived(`${minutes ? `${minutes}m ` : ''}${seconds ? `${seconds}s` : ''}`.trim() || '0s');
  const isActive = $derived(options.some((option) => option.value === interval));
</script>

<ActionTooltip label={hint} {disabled}>
  <ButtonGroup.Root class="kiriof-auto-refresh">
    <Button
      variant="outline"
      onclick={onRefresh}
      disabled={loading || disabled}
      aria-label={hint}
    >
      <IconRefresh data-icon="inline-start" />
      <span>{description}</span>
    </Button>
    <Popover.Root bind:open={popoverOpen}>
      <Popover.Trigger aria-label={hint}>
        {#snippet child({ props })}
          <Button {...props} variant="outline" class="kiriof-auto-refresh__chevron" disabled={loading || disabled}>
            <IconChevronDown />
          </Button>
        {/snippet}
      </Popover.Trigger>
      <Popover.Content class="kiriof-shadcn kiriof-auto-refresh__popover" align="end" sideOffset={4}>
        {#each options as option (option.value)}
          <Button
            variant={option.value === interval && isActive ? 'secondary' : 'ghost'}
            onclick={() => pick(option.value)}
            aria-pressed={option.value === interval}
          >
            {option.label}
          </Button>
        {/each}
      </Popover.Content>
    </Popover.Root>
  </ButtonGroup.Root>
</ActionTooltip>
