<script lang="ts">
  import { IconDownload, IconRefresh } from '@tabler/icons-svelte';
  import * as Card from '$lib/components/ui/card';
  import * as Field from '$lib/components/ui/field';
  import * as Alert from '$lib/components/ui/alert';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import { Spinner } from '$lib/components/ui/spinner';
  import { Button } from '$lib/components/ui/button';
  import { getWordPressAction, postWordPressRawAction } from '../wordpress/ajax';
  import type { TechnicalBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';
  import GoogleMapsIntegration from './GoogleMapsIntegration.svelte';

  type RegionStatus = {
    status: {
      state: string;
      last_error?: string;
      last_completed_at?: string;
    };
    province_count: number;
    city_count: number;
  };

  type CourierRefresh = { count: number };

  let { bootstrap }: { bootstrap: TechnicalBootstrap } = $props();
  function initialRegion(): TechnicalBootstrap['region'] {
    return { ...bootstrap.region };
  }
  function initialCouriers(): TechnicalBootstrap['couriers'] {
    return { ...bootstrap.couriers };
  }
  let region = $state(initialRegion());
  let couriers = $state(initialCouriers());
  let regionBusy = $state(false);
  let courierBusy = $state(false);
  let regionMessage = $state('');
  let courierMessage = $state('');
  let regionError = $state(false);
  let courierError = $state(false);

  function formatDate(date: Date): string {
    return date.toLocaleString('sv-SE');
  }

  async function pollRegionStatus(): Promise<void> {
    for (let attempt = 0; attempt < 20; attempt += 1) {
      const data = await getWordPressAction<RegionStatus>('kiriof_get_coupon_region_status');
      region.state = data.status.state;
      region.lastError = data.status.last_error ?? '';
      region.provinceCount = data.province_count;
      region.cityCount = data.city_count;
      region.updated = data.status.last_completed_at ?? region.updated;

      if (data.status.state === 'ready') {
        region.validUntil = bootstrap.region.validUntil;
        regionMessage = bootstrap.i18n.cacheUpdated;
        return;
      }
      if (data.status.state === 'error') {
        throw new Error(data.status.last_error ?? bootstrap.i18n.refreshFailed);
      }

      regionMessage = bootstrap.i18n.refreshing;
      await new Promise((resolve) => window.setTimeout(resolve, 3000));
    }

    throw new Error(bootstrap.i18n.refreshFailed);
  }

  async function refreshRegion(): Promise<void> {
    regionBusy = true;
    regionError = false;
    regionMessage = bootstrap.i18n.scheduling;

    try {
      await postWordPressRawAction<{ state: string }>('kiriof_refresh_coupon_regions');
      await pollRegionStatus();
    } catch (requestError) {
      regionError = true;
      regionMessage = requestError instanceof Error ? requestError.message : bootstrap.i18n.refreshFailed;
    } finally {
      regionBusy = false;
    }
  }

  async function refreshCouriers(): Promise<void> {
    courierBusy = true;
    courierError = false;
    courierMessage = '';

    try {
      const data = await postWordPressRawAction<CourierRefresh>('kiriof_flush_couriers_cache');
      const now = new Date();
      const tomorrow = new Date(now);
      tomorrow.setDate(tomorrow.getDate() + 1);
      couriers.cached = true;
      couriers.count = data.count;
      couriers.updated = formatDate(now);
      couriers.validUntil = formatDate(tomorrow);
      courierMessage = `${bootstrap.i18n.cacheRefreshed} (${data.count} ${bootstrap.i18n.couriers})`;
    } catch (requestError) {
      courierError = true;
      courierMessage = requestError instanceof Error ? requestError.message : bootstrap.i18n.flushFailed;
    } finally {
      courierBusy = false;
    }
  }
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings} />
<div class="kiriof-shadcn kiriof-technical kiriof-settings-content flex flex-col gap-4">
  {#if bootstrap.googleMaps}
    <GoogleMapsIntegration bootstrap={bootstrap.googleMaps} />
  {/if}
  <Card.Root aria-labelledby="kiriof-region-cache-title">
    <Card.Header>
      <Card.Title><h2 id="kiriof-region-cache-title">{bootstrap.i18n.regionTitle}</h2></Card.Title>
      <Card.Description>{bootstrap.i18n.regionDescription}</Card.Description>
    </Card.Header>
    <Card.Content class="flex flex-col gap-4">
      <dl class="kiriof-diagnostics">
        <div><dt>{bootstrap.i18n.status}</dt><dd><Badge tone={region.state === 'error' ? 'critical' : region.state === 'ready' ? 'success' : 'warning'}>{region.state}</Badge></dd></div>
        <div><dt>{bootstrap.i18n.provinces}</dt><dd>{region.provinceCount}</dd></div>
        <div><dt>{bootstrap.i18n.cities}</dt><dd>{region.cityCount}</dd></div>
        <div><dt>{bootstrap.i18n.lastUpdated}</dt><dd>{region.updated}</dd></div>
        <div><dt>{bootstrap.i18n.validUntil}</dt><dd>{region.validUntil}</dd></div>
      </dl>
      <div class="flex flex-col items-start gap-3">
        <Button disabled={regionBusy} onclick={refreshRegion}>
          {#if regionBusy}<Spinner data-icon="inline-start" aria-hidden="true" />{:else}<IconRefresh data-icon="inline-start" aria-hidden="true" />{/if}
          <span>{regionBusy ? bootstrap.i18n.refreshing : bootstrap.i18n.refreshRegion}</span>
        </Button>
        {#if regionMessage}<Alert.Root variant={regionError ? 'destructive' : 'default'} role={regionError ? 'alert' : 'status'}><Alert.Description>{regionMessage}</Alert.Description></Alert.Root>{/if}
      </div>
    </Card.Content>
  </Card.Root>

  <Card.Root aria-labelledby="kiriof-courier-cache-title">
    <Card.Header>
      <Card.Title><h2 id="kiriof-courier-cache-title">{bootstrap.i18n.courierTitle}</h2></Card.Title>
      <Card.Description>{bootstrap.i18n.courierDescription}</Card.Description>
    </Card.Header>
    <Card.Content class="flex flex-col gap-4">
      <dl class="kiriof-diagnostics">
        <div><dt>{bootstrap.i18n.status}</dt><dd><Badge tone={couriers.cached ? 'success' : 'warning'}>{couriers.cached ? bootstrap.i18n.cached : bootstrap.i18n.notCached}</Badge></dd></div>
        <div><dt>{bootstrap.i18n.couriers}</dt><dd>{couriers.count}</dd></div>
        <div><dt>{bootstrap.i18n.lastUpdated}</dt><dd>{couriers.updated}</dd></div>
        <div><dt>{bootstrap.i18n.validUntil}</dt><dd>{couriers.validUntil}</dd></div>
      </dl>
      <div class="flex flex-col items-start gap-3">
        <Button disabled={courierBusy} onclick={refreshCouriers}>
          {#if courierBusy}<Spinner data-icon="inline-start" aria-hidden="true" />{:else}<IconRefresh data-icon="inline-start" aria-hidden="true" />{/if}
          <span>{courierBusy ? bootstrap.i18n.flushing : bootstrap.i18n.flushCouriers}</span>
        </Button>
        {#if courierMessage}<Alert.Root variant={courierError ? 'destructive' : 'default'} role={courierError ? 'alert' : 'status'}><Alert.Description>{courierMessage}</Alert.Description></Alert.Root>{/if}
      </div>
    </Card.Content>
  </Card.Root>

  <Card.Root aria-labelledby="kiriof-callbacks-title">
    <Card.Header>
      <Card.Title><h2 id="kiriof-callbacks-title">{bootstrap.i18n.callbacksTitle}</h2></Card.Title>
      <Card.Description>{bootstrap.i18n.callbacksDescription}</Card.Description>
    </Card.Header>
    <Card.Content class="flex flex-col gap-4">
      {#if bootstrap.callbacks.length}
        <Field.Group>
          {#each bootstrap.callbacks as callback, index}
            <Field.Field>
              <Field.Label for={`kiriof-callback-${index}`}>{bootstrap.i18n.callbacksTitle} {index + 1}</Field.Label>
              <Input id={`kiriof-callback-${index}`} value={callback} readonly aria-describedby={`kiriof-callback-description-${index}`} />
              <Field.Description id={`kiriof-callback-description-${index}`}>{bootstrap.i18n.callbacksDescription}</Field.Description>
            </Field.Field>
          {/each}
        </Field.Group>
      {:else}
        <p class="kiriof-section-message">{bootstrap.i18n.noCallbacks}</p>
      {/if}
    </Card.Content>
  </Card.Root>

  <Card.Root aria-labelledby="kiriof-logs-title">
    <Card.Header>
      <Card.Title><h2 id="kiriof-logs-title">{bootstrap.i18n.logsTitle}</h2></Card.Title>
      <Card.Description>{bootstrap.i18n.logsDescription}</Card.Description>
    </Card.Header>
    <Card.Content>
      <p class="text-sm text-muted-foreground">{bootstrap.i18n.logsPrivacy}</p>
    </Card.Content>
    <Card.Footer>
      <Button href={bootstrap.downloadLogUrl}>
        <IconDownload data-icon="inline-start" aria-hidden="true" />
        <span>{bootstrap.i18n.downloadLog}</span>
      </Button>
    </Card.Footer>
  </Card.Root>
</div>
