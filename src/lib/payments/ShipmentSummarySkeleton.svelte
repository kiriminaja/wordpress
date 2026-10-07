<script lang="ts">
  import { Skeleton } from '$lib/components/ui/skeleton';

  let { variant, label }: { variant: 'instant' | 'express'; label: string } = $props();
</script>

<div class="kiriof-shipment-summary-skeleton flex flex-col gap-4" data-loading-layout={variant} aria-busy="true" role="status" aria-label={label}>
  <span class="sr-only">{label}</span>
  <div class="flex flex-col gap-4" aria-hidden="true">
    {#if variant === 'instant'}
      <div class="flex items-center gap-3 rounded-lg border p-3" data-loading-section="notice">
        <Skeleton class="size-5 shrink-0" />
        <div class="flex flex-1 flex-col gap-2"><Skeleton class="h-4 w-24" /><Skeleton class="h-3 w-3/4" /></div>
      </div>
      <div class="flex items-center justify-between gap-3 rounded-lg border p-3" data-loading-section="order-trigger"><Skeleton class="h-4 w-40" /><Skeleton class="size-4" /></div>
    {/if}
    <div class="flex flex-col gap-3 rounded-lg border p-3" data-loading-section="totals">
      {#each Array.from({ length: variant === 'express' ? 3 : 2 }) as _, index (index)}
        <div class="flex items-center justify-between gap-4"><Skeleton class="h-4 w-1/2" /><Skeleton class="h-4 w-20" /></div>
      {/each}
    </div>
    {#if variant === 'express'}
      <div class="grid grid-cols-2 gap-3" data-loading-section="schedule">
        {#each [0, 1] as index (index)}<div class="flex flex-col gap-2"><Skeleton class="h-4 w-24" /><Skeleton class="h-9 w-full" /></div>{/each}
      </div>
    {/if}
    <div class="flex flex-col gap-2.5" data-loading-section="payment-methods">
      <Skeleton class="h-4 w-32" />
      {#each [0, 1] as index (index)}
        <div class="grid grid-cols-[40px_minmax(0,1fr)_18px] items-center gap-3 rounded-lg border p-3">
          <Skeleton class="size-10" /><div class="flex flex-col gap-2"><Skeleton class="h-4 w-24" /><Skeleton class="h-3 w-3/4" /></div><Skeleton class="size-4 rounded-full" />
        </div>
      {/each}
    </div>
  </div>
</div>
