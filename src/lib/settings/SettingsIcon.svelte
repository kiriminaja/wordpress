<script lang="ts">
  import {
    IconBox,
    IconBuildingWarehouse,
    IconCash,
    IconDatabase,
    IconLink,
    IconMapPin,
    IconShieldCheck,
    IconTruckDelivery,
    IconUser,
    IconWorldWww,
  } from '@tabler/icons-svelte';
  import type { SettingsIconName } from './types';

  let { name }: { name: SettingsIconName } = $props();
</script>

{#if name === 'account'}
  <IconUser size={22} stroke={1.8} />
{:else if name === 'tracking'}
  <IconWorldWww size={22} stroke={1.8} />
{:else if name === 'product'}
  <IconBox size={22} stroke={1.8} />
{:else if name === 'shipping'}
  <IconMapPin size={22} stroke={1.8} />
{:else if name === 'courier'}
  <IconTruckDelivery size={22} stroke={1.8} />
{:else if name === 'insurance'}
  <IconShieldCheck size={22} stroke={1.8} />
{:else if name === 'cod'}
  <IconCash size={22} stroke={1.8} />
{:else if name === 'location'}
  <IconBuildingWarehouse size={22} stroke={1.8} />
{:else if name === 'webhook'}
  <IconLink size={22} stroke={1.8} />
{:else}
  <IconDatabase size={22} stroke={1.8} />
{/if}
