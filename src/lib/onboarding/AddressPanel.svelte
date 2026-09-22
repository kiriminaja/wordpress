<script lang="ts">
  import { onMount } from 'svelte';
  import type { OnboardingBootstrap } from './types';

  let { config }: { config: OnboardingBootstrap['address'] } = $props();

  onMount(() => {
    window.dispatchEvent(new CustomEvent('kiriof:onboarding-address-mounted'));
  });
</script>

<p class="kiriof-onboarding__step-number">Step 2 of 4</p>
<h2>{config.title}</h2>
<p>{config.description}</p>
<div class="kiriof-onboarding__address-grid">
  <label><span>{config.i18n.senderName}</span><input id="kiriof-origin-name" class="regular-text kiriof-onboarding__field" name="origin_name" type="text" value={config.values.origin_name ?? ''} /></label>
  <label><span>{config.i18n.senderPhone}</span><input id="kiriof-origin-phone" class="regular-text kiriof-onboarding__field" name="origin_phone" type="text" value={config.values.origin_phone ?? ''} /></label>
  <label class="kiriof-onboarding__address-wide"><span>{config.i18n.address}</span><textarea id="kiriof-origin-address" class="large-text kiriof-onboarding__field" name="origin_address" rows="3">{config.values.origin_address ?? ''}</textarea></label>
  <label><span>{config.i18n.zipcode}</span><input id="kiriof-origin-zip-code" class="regular-text kiriof-onboarding__field" name="origin_zip_code" type="text" value={config.values.origin_zip_code ?? ''} /></label>
  <label><span>{config.i18n.subdistrict}</span><select id="kiriof-origin-sub-district-id" name="origin_sub_district_id" class="kiriof-onboarding-subdistrict kiriof-onboarding__field" data-placeholder={config.i18n.searchSubdistrict}><option value="">{config.i18n.searchSubdistrict}</option>{#if config.values.origin_sub_district_id}<option selected value={config.values.origin_sub_district_id}>{config.values.origin_sub_district_name ?? ''}</option>{/if}</select></label>
</div>
<input name="origin_latitude" type="hidden" value={config.values.origin_latitude ?? ''} />
<input name="origin_longitude" type="hidden" value={config.values.origin_longitude ?? ''} />
<div id="kiriof-onboarding-map" class="kiriof-onboarding__map"></div>
<p class="description">{config.i18n.mapHelp}</p>
<div class="kiriof-onboarding__message" data-step-message="address" role="alert"></div>
