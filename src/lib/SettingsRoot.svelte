<script lang="ts">
  import {
    IconChevronRight,
    IconExternalLink,
    IconLink,
  } from '@tabler/icons-svelte';
  import { Button } from 'bits-ui';
  import SettingsIcon from './settings/SettingsIcon.svelte';
  import Toolbar from './ui/Toolbar.svelte';
  import type { SettingsBootstrap } from './settings/types';
  import { postWordPressAction } from './wordpress/ajax';
  import SettingSwitch from './ui/SettingSwitch.svelte';
  import { isInternalSettingsUrl, navigateSettings } from './settings/navigation';

  let { bootstrap }: { bootstrap: SettingsBootstrap } = $props();
  function initialToggle(kind: 'insurance' | 'cod'): boolean {
    return bootstrap.toggles?.[kind] ?? false;
  }
  let setupKey = $state('');
  let connecting = $state(false);
  let error = $state('');
  let insurance = $state(initialToggle('insurance'));
  let cod = $state(initialToggle('cod'));
  let saving = $state<'insurance' | 'cod' | null>(null);

  async function connect(): Promise<void> {
    const key = setupKey.trim();
    if (!key) {
      error = bootstrap.i18n.enterSetupKey;
      return;
    }

    connecting = true;
    error = '';

    try {
      await postWordPressAction('kiriof_store_integration_data', { setup_key: key });
      window.location.reload();
    } catch (requestError) {
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.connectionFailed;
      connecting = false;
    }
  }

  async function saveToggle(kind: 'insurance' | 'cod', checked: boolean): Promise<void> {
    const previous = kind === 'insurance' ? insurance : cod;
    if (kind === 'insurance') {
      insurance = checked;
    } else {
      cod = checked;
    }
    saving = kind;
    error = '';

    try {
      await postWordPressAction(
        kind === 'insurance' ? 'kiriof_store_insurance_data' : 'kiriof_store_config_data',
        kind === 'insurance'
          ? { enable_insurance: checked ? 'yes' : 'no' }
          : { enable_cod: checked ? 'yes' : 'no' },
      );
    } catch (requestError) {
      if (kind === 'insurance') {
        insurance = previous;
      } else {
        cod = previous;
      }
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed;
    } finally {
      saving = null;
    }
  }
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings} />

{#if bootstrap.mode === 'unconfigured'}
  <section class="kiriof-connect kiriof-settings-content" aria-labelledby="kiriof-connect-title">
    <div class="kiriof-connect__identity" aria-hidden="true">
      <span>W</span>
      <span class="kiriof-connect__link"><IconLink size={18} stroke={2} /></span>
      <span class="kiriof-connect__brand">K</span>
    </div>
    <h2 id="kiriof-connect-title">KiriminAja</h2>
    <label class="kiriof-connect__label" for="kiriof-setup-key">{bootstrap.i18n.setupKey}</label>
    <input
      id="kiriof-setup-key"
      class="kiriof-connect__input"
      type="text"
      bind:value={setupKey}
      placeholder={bootstrap.i18n.setupKeyPlaceholder}
      autocomplete="off"
      onkeydown={(event) => event.key === 'Enter' && connect()}
    />
    {#if error}
      <p class="kiriof-settings-root__message is-error" role="alert">{error}</p>
    {/if}
    <div class="kiriof-connect__actions">
      <Button.Root class="button button-primary" disabled={connecting} onclick={connect}>
        {connecting ? bootstrap.i18n.connecting : bootstrap.i18n.connect}
      </Button.Root>
      <Button.Root class="button" href={bootstrap.helpUrl} target="_blank" rel="noopener noreferrer">
        {bootstrap.i18n.howToConnect}
        <IconExternalLink size={15} stroke={2} aria-hidden="true" />
      </Button.Root>
    </div>
  </section>
{:else}
  <div class="kiriof-settings-root kiriof-settings-content">
    {#if error}
      <p class="kiriof-settings-root__message is-error" role="alert">{error}</p>
    {/if}
    {#each bootstrap.groups ?? [] as group, groupIndex}
      <section class="kiriof-settings-group" aria-labelledby={`kiriof-group-${groupIndex}`}>
        <h2 id={`kiriof-group-${groupIndex}`} class="kiriof-settings-group__title">{group.label}</h2>
        <div class="kiriof-settings-group__items">
          {#each group.items as item}
            {#if item.toggle}
              <div class="kiriof-setting-item">
                <span class="kiriof-setting-item__icon"><SettingsIcon name={item.icon} /></span>
                <span class="kiriof-setting-item__copy">
                  <strong>{item.label}</strong>
                  <span>{item.description}</span>
                </span>
                <SettingSwitch
                  label={item.label}
                  checked={item.toggle === 'insurance' ? insurance : cod}
                  disabled={saving === item.toggle}
                  onCheckedChange={(checked) => saveToggle(item.toggle!, checked)}
                />
              </div>
            {:else}
              <a
                class="kiriof-setting-item"
                href={item.href}
                target={item.external ? '_blank' : undefined}
                rel={item.external ? 'noopener noreferrer' : undefined}
                onclick={(event) => {
                  if (isInternalSettingsUrl(item.href)) {
                    event.preventDefault();
                    navigateSettings(item.href!);
                  }
                }}
              >
                <span class="kiriof-setting-item__icon"><SettingsIcon name={item.icon} /></span>
                <span class="kiriof-setting-item__copy">
                  <strong>{item.label}</strong>
                  <span>{item.description}</span>
                </span>
                {#if item.status}
                  <span class:warning={item.statusTone === 'warning'} class="kiriof-setting-item__status">
                    {item.status}
                  </span>
                {/if}
                <IconChevronRight class="kiriof-setting-item__chevron" size={18} stroke={2} aria-hidden="true" />
              </a>
            {/if}
          {/each}
        </div>
      </section>
    {/each}
  </div>
{/if}
