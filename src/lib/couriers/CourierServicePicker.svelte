<script lang="ts">
  import { Checkbox } from '$lib/components/ui/checkbox';
  import { Badge } from '$lib/components/ui/badge';
  import {
    courierSelection,
    toggleCourier,
    toggleService,
    type Courier,
    type SelectionState,
  } from './selection';

  let {
    couriers,
    state,
    disabled = false,
    onChange,
    i18n,
  }: {
    couriers: Courier[];
    state: SelectionState;
    disabled?: boolean;
    onChange: (next: SelectionState) => void;
    i18n: Partial<Record<string, string>>;
  } = $props();
  const prefix = $props.id();
</script>

<div class="grid gap-3" aria-busy={disabled}>
  {#each couriers as courier, index (courier.code)}
    {@const status = courierSelection(courier, state.selection)}
    <section
      class="rounded-lg border border-border bg-card p-4"
      aria-labelledby={`${prefix}-courier-${index}`}
    >
      <div class="mb-3 flex items-center justify-between gap-3">
        <div class="space-y-1">
          <label
            id={`${prefix}-courier-${index}`}
            for={`${prefix}-toggle-${index}`}
            class="cursor-pointer text-sm font-semibold text-foreground">{courier.name}</label
          >
          <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span>{(i18n.serviceCount ?? '%1$s of %2$s enabled').replace('%1$s', String(status.count)).replace('%2$s', String(courier.services.length))}</span>
            {#if courier.type}<span>{courier.type}</span>{/if}
            {#if courier.unavailable}<Badge variant="outline">{i18n.unavailable ?? 'Unavailable'}</Badge>{/if}
          </div>
        </div>
        <Checkbox
          id={`${prefix}-toggle-${index}`}
          checked={status.checked && !status.indeterminate}
          indeterminate={status.indeterminate}
          {disabled}
          aria-label={(i18n.enableCourier ?? 'Enable %s services').replace('%s', courier.name)}
          onCheckedChange={() => {
            if (!disabled) onChange(toggleCourier(state, courier, !status.checked));
          }}
        />
      </div>
      <div class="grid gap-2 border-t border-border pt-3 sm:grid-cols-2">
        {#each courier.services as service, serviceIndex (service.code)}
          <div class="flex items-center gap-2 rounded-md p-1">
            <Checkbox
              id={`${prefix}-service-${index}-${serviceIndex}`}
              checked={(state.selection[courier.code] ?? []).includes(service.code)}
              {disabled}
              aria-label={(i18n.enableService ?? 'Enable %1$s for %2$s').replace('%1$s', service.name).replace('%2$s', courier.name)}
              onCheckedChange={(checked) => {
                if (!disabled) onChange(toggleService(state, courier, service.code, checked));
              }}
            />
            <label
              for={`${prefix}-service-${index}-${serviceIndex}`}
              class="flex cursor-pointer flex-wrap items-center gap-x-2 text-sm text-foreground"
            >
              <span>{service.code === '*' ? (i18n.allServices ?? service.name) : service.name}</span><span class="text-xs text-muted-foreground"
                >{service.code}</span
              >
              {#if service.unavailable}<span class="text-xs text-muted-foreground">{i18n.unavailable ?? 'Unavailable'}</span
                >{/if}
            </label>
          </div>
        {/each}
      </div>
    </section>
  {/each}
</div>
