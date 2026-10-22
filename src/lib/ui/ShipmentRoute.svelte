<script lang="ts">
  import { IconArrowRight } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Popover from '$lib/components/ui/popover';

  let {
    origin = '',
    recipient = '',
    addressLines = [],
    title,
    detailsLabel,
  }: {
    origin?: string | null;
    recipient?: string | null;
    addressLines?: (string | null | undefined)[] | null;
    title: string;
    detailsLabel: string;
  } = $props();

  const id = $props.id();
  const titleId = `${id}-route-title`;
  const lines = $derived((addressLines ?? []).filter((line): line is string => typeof line === 'string' && line.trim().length > 0));
  const hasRoute = $derived(Boolean(origin?.trim() || recipient?.trim() || lines.length));
</script>

<div class="w-60 max-w-60 min-w-0" data-shipment-route>
  {#if hasRoute}
    <Popover.Root>
      <Popover.Trigger>
        {#snippet child({ props })}
          <Button {...props} variant="ghost" class="h-auto w-full min-w-0 flex-col gap-1 px-2 py-2 text-left" aria-label={detailsLabel}>
            <span class="flex w-full min-w-0 items-center gap-1.5">
              <span class="min-w-0 flex-1 truncate">{origin || '—'}</span>
              <IconArrowRight aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate">{recipient || '—'}</span>
            </span>
            {#if lines.length}<span class="block w-full min-w-0 truncate text-muted-foreground">{lines[0]}</span>{/if}
          </Button>
        {/snippet}
      </Popover.Trigger>
      <Popover.Content role="dialog" align="start" aria-labelledby={titleId} class="w-80 max-w-[calc(100vw-2rem)] max-h-[min(28rem,70dvh)] overflow-y-auto whitespace-normal [overflow-wrap:anywhere]">
        <Popover.Header>
          <Popover.Title id={titleId} role="heading" aria-level={3}>{title}</Popover.Title>
        </Popover.Header>
        <ol class="m-0 flex list-none flex-col gap-1 p-0" aria-label={title}>
          <li>{origin || '—'}</li>
          <li class="flex min-w-0 items-start gap-2"><IconArrowRight class="mt-0.5 size-4 shrink-0" aria-hidden="true" /><span class="min-w-0">{recipient || '—'}</span></li>
        </ol>
        {#each lines as line}<p class="m-0">{line}</p>{/each}
      </Popover.Content>
    </Popover.Root>
  {:else}
    <span>—</span>
  {/if}
</div>
