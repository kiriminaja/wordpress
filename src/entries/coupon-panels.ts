import { mount } from 'svelte';
import CouponPanel from '../lib/coupons/CouponPanel.svelte';

for (const host of document.querySelectorAll<HTMLElement>('[data-kiriof-coupon-panel-host]')) {
  const key = host.dataset.kiriofCouponPanelHost;
  const fallback = document.querySelector<HTMLElement>(
    `[data-kiriof-coupon-panel-fallback="${key}"]`,
  );
  if (fallback) mount(CouponPanel, { target: host, props: { fallback } });
}
