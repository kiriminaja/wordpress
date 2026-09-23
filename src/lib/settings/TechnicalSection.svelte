<script lang="ts">
  import { IconDownload, IconRefresh } from '@tabler/icons-svelte';
  import { Button } from 'bits-ui';
  import { getWordPressAction, postWordPressRawAction } from '../wordpress/ajax';
  import type { TechnicalBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';

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
<div class="kiriof-technical kiriof-settings-content">
  <section class="kiriof-section-card" aria-labelledby="kiriof-region-cache-title">
    <h2 id="kiriof-region-cache-title">{bootstrap.i18n.regionTitle}</h2>
    <p>{bootstrap.i18n.regionDescription}</p>
    <dl class="kiriof-diagnostics">
      <div><dt>{bootstrap.i18n.status}</dt><dd><span class:warning={region.state !== 'ready'} class:error={region.state === 'error'} class="kiriof-diagnostics__badge">{region.state}</span></dd></div>
      <div><dt>{bootstrap.i18n.provinces}</dt><dd>{region.provinceCount}</dd></div>
      <div><dt>{bootstrap.i18n.cities}</dt><dd>{region.cityCount}</dd></div>
      <div><dt>{bootstrap.i18n.lastUpdated}</dt><dd>{region.updated}</dd></div>
      <div><dt>{bootstrap.i18n.validUntil}</dt><dd>{region.validUntil}</dd></div>
    </dl>
    <div class="kiriof-section-actions">
      <Button.Root class="button button-primary" disabled={regionBusy} onclick={refreshRegion}>
        <IconRefresh size={16} stroke={2} aria-hidden="true" />
        {regionBusy ? bootstrap.i18n.refreshing : bootstrap.i18n.refreshRegion}
      </Button.Root>
      {#if regionMessage}<span class:error={regionError} class="kiriof-section-message" role={regionError ? 'alert' : 'status'}>{regionMessage}</span>{/if}
    </div>
  </section>

  <section class="kiriof-section-card" aria-labelledby="kiriof-courier-cache-title">
    <h2 id="kiriof-courier-cache-title">{bootstrap.i18n.courierTitle}</h2>
    <p>{bootstrap.i18n.courierDescription}</p>
    <dl class="kiriof-diagnostics">
      <div><dt>{bootstrap.i18n.status}</dt><dd><span class:warning={!couriers.cached} class="kiriof-diagnostics__badge">{couriers.cached ? bootstrap.i18n.cached : bootstrap.i18n.notCached}</span></dd></div>
      <div><dt>{bootstrap.i18n.couriers}</dt><dd>{couriers.count}</dd></div>
      <div><dt>{bootstrap.i18n.lastUpdated}</dt><dd>{couriers.updated}</dd></div>
      <div><dt>{bootstrap.i18n.validUntil}</dt><dd>{couriers.validUntil}</dd></div>
    </dl>
    <div class="kiriof-section-actions">
      <Button.Root class="button button-primary" disabled={courierBusy} onclick={refreshCouriers}>
        <IconRefresh size={16} stroke={2} aria-hidden="true" />
        {courierBusy ? bootstrap.i18n.flushing : bootstrap.i18n.flushCouriers}
      </Button.Root>
      {#if courierMessage}<span class:error={courierError} class="kiriof-section-message" role={courierError ? 'alert' : 'status'}>{courierMessage}</span>{/if}
    </div>
  </section>

  <section class="kiriof-section-card" aria-labelledby="kiriof-logs-title">
    <h2 id="kiriof-logs-title">{bootstrap.i18n.logsTitle}</h2>
    <p>{bootstrap.i18n.logsDescription}</p>
    <p>{bootstrap.i18n.logsPrivacy}</p>
    <Button.Root class="button button-primary" href={bootstrap.downloadLogUrl}>
      <IconDownload size={16} stroke={2} aria-hidden="true" />
      {bootstrap.i18n.downloadLog}
    </Button.Root>
  </section>
</div>
