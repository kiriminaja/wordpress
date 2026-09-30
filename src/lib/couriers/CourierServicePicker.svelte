<script lang="ts">
  import { IconSearch } from '@tabler/icons-svelte';
  import { Badge } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Alert, AlertDescription } from '$lib/components/ui/alert';
  import CourierLogo from '$lib/ui/CourierLogo.svelte';
  import SettingSwitch from '$lib/ui/SettingSwitch.svelte';
  import {
    courierSelection,
    courierDeliveryType,
    toggleCourier,
    toggleService,
    matchesCourierSearch,
    sortCouriersByName,
    type Courier,
    type DeliveryType,
    type SelectionState,
  } from '$lib/couriers/selection';

  let {
    couriers,
    state: selectionState,
    disabled = false,
    compact = false,
    deliveryType = $bindable<DeliveryType>('express'),
    onChange,
    i18n,
  }: {
    couriers: Courier[];
    state: SelectionState;
    disabled?: boolean;
    compact?: boolean;
    deliveryType?: DeliveryType;
    onChange: (next: SelectionState) => void;
    i18n: Partial<Record<string, string>>;
  } = $props();
  const prefix = $props.id();
  let search = $state('');
  const rows = $derived(
    sortCouriersByName(couriers).map((courier, index) => ({
      courier,
      index,
      status: courierSelection(courier, selectionState.selection),
    })),
  );
  const deliveryTypes: DeliveryType[] = ['express', 'instant'];
</script>

<Tabs.Root
  bind:value={deliveryType}
  class="kiriof-shadcn !grid min-w-0 gap-3"
  aria-busy={disabled}
>
  <div
    class="!flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-3 sm:p-4"
  >
    <Tabs.List
      class="!h-auto !max-w-full !flex-wrap !bg-transparent !p-0"
      aria-label={i18n.deliveryType ?? 'Delivery type'}
    >
      <Tabs.Trigger
        value="express"
        class="!h-9 !px-3 data-active:!bg-muted data-active:!shadow-none"
        >{i18n.expressDelivery ?? 'Express Delivery'}</Tabs.Trigger
      >
      <Tabs.Trigger
        value="instant"
        class="!h-9 !px-3 data-active:!bg-muted data-active:!shadow-none"
        >{i18n.instantDelivery ?? 'Instant Delivery'}</Tabs.Trigger
      >
      <Tabs.Trigger value="international" disabled title={i18n.comingSoon ?? 'Coming soon'}>
        {i18n.internationalDelivery ?? 'International'} · {i18n.comingSoon ?? 'Coming soon'}
      </Tabs.Trigger>
    </Tabs.List>
    <div
      class="!flex w-full min-w-0 flex-wrap items-center justify-end gap-3 sm:w-auto sm:flex-1"
    >
      <InputGroup.Root class="!h-9 !w-full !shrink-0 !bg-background sm:!w-72">
        <InputGroup.Addon align="inline-start"
          ><IconSearch class="size-4" aria-hidden="true" /></InputGroup.Addon
        >
        <InputGroup.Input
          id={`${prefix}-search`}
          type="search"
          bind:value={search}
          aria-label={i18n.searchCouriers ?? 'Search couriers or services'}
          placeholder={i18n.searchCouriers ?? 'Search couriers or services'}
        />
      </InputGroup.Root>
    </div>
  </div>

  {#each deliveryTypes as tabType (tabType)}
  {@const tabRows = rows.filter(({ courier }) => courierDeliveryType(courier) === tabType)}
  {@const visibleRows = tabRows.filter(({ courier }) => matchesCourierSearch(courier, search))}
  {@const courierCount = tabRows.filter(({ status }) => status.checked).length}
  {@const serviceCount = tabRows.reduce((total, { status }) => total + status.count, 0)}
  {@const hasUnavailable = tabRows.some(({ courier }) => courier.unavailable || courier.services.some((service) => service.unavailable))}
  <Tabs.Content
    value={tabType}
    class="!mt-0 !grid min-w-0 gap-4 rounded-xl border border-border bg-card p-3 sm:p-4"
  >
    <div class="!flex flex-wrap items-start justify-between gap-3">
      <div class="!grid gap-1">
        <h2 class="!m-0 !text-sm font-semibold text-foreground">
          {tabType === 'instant' ? (i18n.instantDelivery ?? 'Instant Delivery') : (i18n.domesticDelivery ?? 'Domestic Delivery')}
        </h2>
        <p class="!m-0 text-xs text-muted-foreground">
          {(i18n.activeTotal ?? '%1$s Active / %2$s Total')
            .replace('%1$s', String(courierCount))
            .replace('%2$s', String(tabRows.length))}
        </p>
      </div>
      <span
        class="text-xs text-muted-foreground"
        role="status"
        aria-live="polite"
        >{(i18n.selectedServices ?? '%s services enabled').replace(
          '%s',
          String(serviceCount),
        )}</span
      >
    </div>
    {#if tabType === 'instant'}
      <Alert><AlertDescription>{i18n.instantSetupHint ?? 'Instant preferences are saved here. Instant checkout and shipment processing are not available yet.'}</AlertDescription></Alert>
    {/if}
    {#if courierCount === 0 && tabType === 'express'}
      <p
        class="!m-0 rounded-lg border border-warning-border bg-warning-background p-3 text-xs text-warning-foreground"
        role="status"
      >
        {i18n.noSelectionHint ??
          'No courier services are enabled. Customers cannot use KiriminAja shipping until you enable a service.'}
      </p>
    {/if}
    <div
      class={compact
        ? '!grid min-w-0 grid-cols-1 items-stretch gap-3 sm:grid-cols-2'
        : '!grid min-w-0 grid-cols-1 items-stretch gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4'}
    >
      {#each visibleRows as { courier, index, status } (courier.code)}
        <section
          class="min-w-0 rounded-xl border border-border bg-background p-4"
          aria-labelledby={`${prefix}-courier-${index}`}
        >
          <div class="!flex min-w-0 items-center justify-between gap-3">
            <div class="!flex min-w-0 items-center gap-3">
              <CourierLogo code={courier.code} name={courier.name} />
              <div class="min-w-0">
                <h3
                  id={`${prefix}-courier-${index}`}
                  class="!m-0 break-words !text-sm font-semibold text-foreground"
                >
                  <label
                    for={`${prefix}-toggle-${index}`}
                    class="cursor-pointer">{courier.name}</label
                  >
                </h3>
                <p class="!m-0 mt-1 text-xs text-muted-foreground">
                  {(i18n.serviceCount ?? '%1$s of %2$s enabled')
                    .replace('%1$s', String(status.count))
                    .replace('%2$s', String(courier.services.length))}
                </p>
              </div>
          {#if courier.services.length === 0}
            <p class="!m-0 mt-3 text-xs text-muted-foreground">{i18n.instantServicesMissing ?? 'Service details are unavailable for this courier. Refresh courier data or contact support before enabling it.'}</p>
          {/if}
            </div>
            <SettingSwitch
              id={`${prefix}-toggle-${index}`}
              checked={status.checked}
              disabled={disabled || (!courier.services.some((service) => !service.unavailable) && !status.checked)}
              label={(i18n.enableCourier ?? 'Enable %s services').replace(
                '%s',
                courier.name,
              )}
              onCheckedChange={(checked) => {
                if (!disabled)
                  onChange(toggleCourier(selectionState, courier, checked));
              }}
            />
          </div>
          {#if status.indeterminate || courier.unavailable}
            <div class="mt-2 !flex flex-wrap gap-1.5">
              {#if status.indeterminate}<Badge variant="secondary"
                  >{i18n.filterPartial ?? 'Partially enabled'}</Badge
                >{/if}
              {#if courier.unavailable}<Badge variant="outline"
                  >{i18n.unavailable ?? 'Unavailable'}</Badge
                >{/if}
            </div>
          {/if}
          <div class="mt-4 !grid gap-3">
            {#each courier.services as service, serviceIndex (service.code)}
              {@const selected = (
                selectionState.selection[courier.code] ?? []
              ).includes(service.code)}
              <div class="!flex min-w-0 items-center justify-between gap-3">
                <label
                  for={`${prefix}-service-${index}-${serviceIndex}`}
                  class="!flex min-w-0 flex-1 cursor-pointer flex-wrap items-center gap-x-2 gap-y-1 text-sm text-foreground"
                >
                  <span class="break-words"
                    >{service.code === '*'
                      ? (i18n.allServices ?? service.name)
                      : service.name}</span
                  >
                  {#if service.code !== '*'}<Badge
                      variant="secondary"
                      class="!px-1.5 !py-0 !text-xs !font-normal"
                      >{service.code}</Badge
                    >{/if}
                  {#if service.unavailable}<Badge variant="outline"
                      >{i18n.unavailable ?? 'Unavailable'}</Badge
                    >{/if}
                </label>
                <SettingSwitch
                  id={`${prefix}-service-${index}-${serviceIndex}`}
                  checked={selected}
                  disabled={disabled || (service.unavailable && !selected)}
                  label={(i18n.enableService ?? 'Enable %1$s for %2$s')
                    .replace('%1$s', service.name)
                    .replace('%2$s', courier.name)}
                  onCheckedChange={(checked) => {
                    if (!disabled)
                      onChange(
                        toggleService(
                          selectionState,
                          courier,
                          service.code,
                          checked,
                        ),
                      );
                  }}
                />
              </div>
            {/each}
          </div>
        </section>
      {:else}
        <div
          class="col-span-full !grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-4 py-10 text-center"
          role="status"
        >
          <IconSearch class="size-6 text-muted-foreground" aria-hidden="true" />
          <p class="!m-0 text-sm font-semibold text-foreground">
            {tabRows.length === 0 && tabType === 'instant' ? (i18n.noInstantCouriers ?? 'No Instant couriers are available for this account.') : (i18n.noCouriersFound ?? 'No couriers found')}
          </p>
          <p class="!m-0 text-xs text-muted-foreground">
            {tabRows.length === 0 && tabType === 'instant' ? (i18n.noInstantCouriersHint ?? 'Check Instant eligibility for your KiriminAja account, then refresh the courier data.') : (i18n.noCouriersFoundDescription ?? 'Try another courier or service name, or change the selection filter.')}
          </p>
          {#if search}
            <Button
              variant="outline"
              size="sm"
              onclick={() => {
                search = '';
              }}>{i18n.resetCourierFilters ?? 'Reset filters'}</Button
            >
          {/if}
        </div>
      {/each}
    </div>
    {#if hasUnavailable}
      <p class="!m-0 text-xs text-muted-foreground">
        {i18n.unavailableHint ??
          'Unavailable services are saved choices no longer listed by the courier. You can keep or remove them; they do not guarantee a shipping rate.'}
      </p>
    {/if}
  </Tabs.Content>
  {/each}
</Tabs.Root>
