<script lang="ts">
  import { IconChevronDown, IconChevronUp, IconSearch, IconTruck, IconCheck, IconX } from '@tabler/icons-svelte';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import { Badge } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import * as InputGroup from '$lib/components/ui/input-group';
  import {
    courierSelection,
    selectedCourierCount,
    setAllServices,
    toggleCourier,
    toggleService,
    type Courier,
    type SelectionState,
  } from '$lib/couriers/selection';

  let {
    couriers,
    state: selectionState,
    disabled = false,
    compact = false,
    onChange,
    i18n,
  }: {
    couriers: Courier[];
    state: SelectionState;
    disabled?: boolean;
    compact?: boolean;
    onChange: (next: SelectionState) => void;
    i18n: Partial<Record<string, string>>;
  } = $props();
  const prefix = $props.id();
  type Filter = 'all' | 'selected' | 'partial' | 'unselected';
  let search = $state('');
  let filter = $state<Filter>('all');
  let expanded = $state<Record<string, boolean>>({});
  const filters: Filter[] = ['all', 'selected', 'partial', 'unselected'];
  const labels = $derived({
    all: i18n.filterAll ?? 'All',
    selected: i18n.filterSelected ?? 'Selected',
    partial: i18n.filterPartial ?? 'Partial',
    unselected: i18n.filterUnselected ?? 'Unselected',
  });
  const rows = $derived(couriers.map((courier, index) => {
    const status = courierSelection(courier, selectionState.selection);
    const category: Filter = status.indeterminate ? 'partial' : status.checked ? 'selected' : 'unselected';
    return { courier, index, status, category };
  }));
  const counts = $derived({
    all: rows.length,
    selected: rows.filter((row) => row.category === 'selected').length,
    partial: rows.filter((row) => row.category === 'partial').length,
    unselected: rows.filter((row) => row.category === 'unselected').length,
  });
  const visibleRows = $derived(rows.filter(({ courier, category }) => {
    const query = search.trim().toLocaleLowerCase();
    const matches = !query || [courier.name, courier.code, ...courier.services.flatMap((service) => [service.name, service.code, ...(service.aliases ?? [])])]
      .some((value) => value.toLocaleLowerCase().includes(query));
    return matches && (filter === 'all' || category === filter);
  }));
  const serviceCount = $derived(Object.values(selectionState.selection).reduce((total, services) => total + services.length, 0));
  const courierCount = $derived(selectedCourierCount(selectionState.selection));
</script>

<div class="grid gap-4" aria-busy={disabled}>
  <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 p-4">
    <div class="flex items-center gap-3">
      <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <IconTruck class="size-5" aria-hidden="true" />
      </div>
      <div>
        <p class="m-0 text-sm font-semibold text-foreground">{i18n.courierPickerTitle ?? 'Courier services'}</p>
        <p class="m-0 mt-1 text-xs text-muted-foreground">{i18n.courierPickerDescription ?? 'Choose the couriers and services available at checkout.'}</p>
      </div>
    </div>
    <div class="flex flex-wrap gap-2" role="status" aria-live="polite" aria-atomic="true">
      <Badge variant="secondary">{(i18n.selectedCouriers ?? '%s couriers selected').replace('%s', String(courierCount))}</Badge>
      <Badge variant="outline">{(i18n.selectedServices ?? '%s services enabled').replace('%s', String(serviceCount))}</Badge>
    </div>
  </div>

  <div class="grid gap-3">
    <InputGroup.Root>
      <InputGroup.Addon align="inline-start"><IconSearch aria-hidden="true" /></InputGroup.Addon>
      <InputGroup.Input
        id={`${prefix}-search`}
        type="search"
        bind:value={search}
        aria-label={i18n.searchCouriers ?? 'Search couriers or services'}
        placeholder={i18n.searchCouriers ?? 'Search couriers or services'}
      />
    </InputGroup.Root>
    <div class="flex flex-wrap gap-1" role="group" aria-label={i18n.filterCouriers ?? 'Filter couriers by selection'}>
      {#each filters as option}
        <Button
          size="sm"
          variant={filter === option ? 'secondary' : 'ghost'}
          aria-pressed={filter === option}
          onclick={() => { filter = option; }}
        >
          {labels[option]}
          <span class="rounded bg-background/70 px-1.5 py-0.5 text-xs tabular-nums text-muted-foreground">{counts[option]}</span>
        </Button>
      {/each}
    </div>
  </div>

  <div class={compact ? 'grid gap-2' : 'grid gap-3'}>
    {#each visibleRows as { courier, index, status, category } (courier.code)}
      {@const open = expanded[courier.code] ?? (status.checked || status.indeterminate)}
      <section class="overflow-hidden rounded-lg border border-border bg-card" aria-labelledby={`${prefix}-courier-${index}`}>
        <div class={compact ? 'flex items-start gap-3 p-3' : 'flex items-start gap-3 p-4'}>
          <Checkbox
            id={`${prefix}-toggle-${index}`}
            class="mt-1"
            checked={status.checked && !status.indeterminate}
            indeterminate={status.indeterminate}
            {disabled}
            aria-label={(i18n.enableCourier ?? 'Enable %s services').replace('%s', courier.name)}
            onCheckedChange={() => {
              if (!disabled) onChange(toggleCourier(selectionState, courier, !status.checked));
            }}
          />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <label id={`${prefix}-courier-${index}`} for={`${prefix}-toggle-${index}`} class="cursor-pointer text-sm font-semibold text-foreground">{courier.name}</label>
              <span class="text-xs text-muted-foreground">{courier.code}</span>
              {#if courier.unavailable}<Badge variant="outline">{i18n.unavailable ?? 'Unavailable'}</Badge>{/if}
              {#if category === 'partial'}<Badge variant="secondary">{labels.partial}</Badge>{/if}
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
              <span>{(i18n.serviceCount ?? '%1$s of %2$s enabled').replace('%1$s', String(status.count)).replace('%2$s', String(courier.services.length))}</span>
              {#if courier.type}<span>{courier.type}</span>{/if}
            </div>
            <div class="mt-2 flex flex-wrap gap-1">
              <Button variant="ghost" size="xs" disabled={disabled || status.count === courier.services.length} aria-label={(i18n.enableAllCourier ?? 'Enable all services for %s').replace('%s', courier.name)} onclick={() => { if (!disabled) onChange(setAllServices(selectionState, [courier], true)); }}>
                <IconCheck aria-hidden="true" />{i18n.enableAll ?? 'Enable all'}
              </Button>
              <Button variant="ghost" size="xs" disabled={disabled || !status.checked} aria-label={(i18n.clearCourier ?? 'Clear services for %s').replace('%s', courier.name)} onclick={() => { if (!disabled) onChange(toggleCourier(selectionState, courier, false)); }}>
                <IconX aria-hidden="true" />{i18n.clear ?? 'Clear'}
              </Button>
            </div>
          </div>
          <Button
            variant="ghost"
            size="icon-sm"
            aria-expanded={open}
            aria-controls={`${prefix}-services-${index}`}
            aria-label={(open ? (i18n.collapseCourier ?? 'Collapse %s services') : (i18n.expandCourier ?? 'Expand %s services')).replace('%s', courier.name)}
            onclick={() => { expanded[courier.code] = !open; }}
          >
            {#if open}<IconChevronUp aria-hidden="true" />{:else}<IconChevronDown aria-hidden="true" />{/if}
          </Button>
        </div>
        <div id={`${prefix}-services-${index}`} hidden={!open}>
          {#if open}
            <div class={compact ? 'grid gap-1 border-t border-border p-3 sm:grid-cols-2' : 'grid gap-2 border-t border-border p-4 sm:grid-cols-2'}>
              {#each courier.services as service, serviceIndex (service.code)}
                {@const selected = (selectionState.selection[courier.code] ?? []).includes(service.code)}
                <div class={selected ? 'flex items-center gap-3 rounded-md border border-primary/20 bg-primary/5 p-3' : 'flex items-center gap-3 rounded-md border border-border bg-background p-3'}>
                  <Checkbox
                    id={`${prefix}-service-${index}-${serviceIndex}`}
                    checked={selected}
                    {disabled}
                    aria-label={(i18n.enableService ?? 'Enable %1$s for %2$s').replace('%1$s', service.name).replace('%2$s', courier.name)}
                    onCheckedChange={(checked) => {
                      if (!disabled) onChange(toggleService(selectionState, courier, service.code, checked));
                    }}
                  />
                  <label for={`${prefix}-service-${index}-${serviceIndex}`} class="flex min-w-0 flex-1 cursor-pointer flex-wrap items-center justify-between gap-2 text-sm text-foreground">
                    <span class="min-w-0">
                      <span class="block break-words font-medium">{service.code === '*' ? (i18n.allServices ?? service.name) : service.name}</span>
                      <span class="block break-all text-xs text-muted-foreground">{service.code}</span>
                    </span>
                    {#if service.unavailable}<Badge variant="outline">{i18n.unavailable ?? 'Unavailable'}</Badge>{/if}
                  </label>
                </div>
              {/each}
            </div>
          {/if}
        </div>
      </section>
    {:else}
      <div class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border bg-muted/20 px-4 py-10 text-center" role="status">
        <IconSearch class="size-6 text-muted-foreground" aria-hidden="true" />
        <p class="m-0 text-sm font-semibold text-foreground">{i18n.noCouriersFound ?? 'No couriers found'}</p>
        <p class="m-0 text-xs text-muted-foreground">{i18n.noCouriersFoundDescription ?? 'Try another courier or service name, or change the selection filter.'}</p>
        {#if search || filter !== 'all'}
          <Button variant="outline" size="sm" class="mt-2" onclick={() => { search = ''; filter = 'all'; }}>{i18n.resetCourierFilters ?? 'Reset filters'}</Button>
        {/if}
      </div>
    {/each}
  </div>
</div>
