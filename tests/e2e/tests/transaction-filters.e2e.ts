import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const rowSelector = '.kiriof-transactions-filterrow';
const longPrinted = 'Printed — shipping labels already prepared for the warehouse packing and dispatch team';
const longCourier = 'GoSend — same-day motorcycle delivery with a very long translated courier service label';
const longStatus = 'Awaiting payment confirmation with a very long translated shipment status description';
const i18n = {
  regularDelivery: 'Regular Delivery', instantDelivery: 'Instant Delivery', orderIssue: 'Order Issue',
  transactionScope: 'Transaction scope', processShipment: 'Process Shipment', requestPickup: 'Request Pickup',
  print: 'Print Labels', pageOf: 'of', search: 'Search order…', allDates: 'All Dates', apply: 'Apply',
  clear: 'Clear filters', clearFilters: 'Clear filters', allPayment: 'All Payment', cod: 'COD', nonCod: 'Non-COD',
  status: 'All Status', allCouriers: 'All Couriers', allPrints: 'All Prints', printed: longPrinted, unprinted: 'Unprinted',
  searchStatuses: 'Search statuses…', searchCouriers: 'Search couriers…', noFilterOptions: 'No matching options.',
  selectedFilters: '%s selected', items: 'items', order: 'Order / Transaction', expedition: 'Expedition & Service',
  airwaybill: 'Airwaybill / Order ID', route: 'Shipment Route', packages: 'Packages & Fee', action: 'Action',
  notFound: 'No transactions found.', autoRefresh: 'Auto Refresh Timer', selectAll: 'Select all orders',
  weight: 'Weight', actualShipping: 'Actual Shipping', shippingCost: 'Shipping', insurance: 'Insurance',
  codFee: 'COD Fee', itemDiscount: 'Item Discount', shippingDiscount: 'Shipping Discount',
  copyAwb: 'Copy AWB', copyKaOrderId: 'Copy KA Order ID', copied: 'Copied',
  refreshLabels: { '60': '1 minute', '180': '3 minutes', '300': '5 minutes' },
  scanToPay: 'Scan to Pay', detail: 'Shipment detail', liveTracking: 'Live tracking', instantRecheck: 'Recheck booking',
  instantCancel: 'Cancel Instant shipment', vehicle: 'Vehicle', vehicleUnavailable: 'Unavailable',
  instantClose: 'Close', instantResult_tracked: 'Tracking available', instantResult_unknown: 'Unknown outcome',
  instantTrackingDescription: 'Follow the courier tracking page for this shipment.',
  instantTrackingUnavailable: 'The courier has not provided a live tracking link for this shipment yet. Check again later.',
  instantTrackingEmbedHint: 'If the courier page does not display here, open it in a new tab.',
  instantTrackingLoading: 'Loading live tracking…', instantTrackingRefresh: 'Refresh tracking',
  instantTrackingUnavailableTitle: 'Live tracking is not available yet', instantTrackingErrorTitle: 'Live tracking could not be loaded',
  instantTrackingError: 'Unable to load live tracking. Refresh tracking to try again.', openInNewTab: 'Open in new tab',
};

// Only the HTTP/WordPress boundary is synthetic. Mount the deployed entry (not
// TransactionsApp in a test wrapper), its shared chunks and all shipped CSS.
// Each response reconstructs server-selected filters from the requested URL.
function html(url: URL): string {
  const params = url.searchParams;
  const bootstrap = {
    toolbar: { rootUrl: '/wp-admin/admin.php?page=kiriminaja-setting', rootLabel: 'KiriminAja', title: 'Transactions', logoUrl: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg"/%3E' },
    filters: {
      delivery_type: params.get('delivery_type') || 'instant', status: params.get('status') || 'all',
      key: params.get('key') || '', courier: params.get('courier') || '', cod: params.get('cod') || '',
      print_status: params.get('print_status') || '', month: params.get('month') || 'all',
      date_from: params.get('date_from') || '', date_to: params.get('date_to') || '',
    },
    deliveryCounts: { regular: 0, instant: 0, issue: 0 },
    statusOptions: [{ value: 'all', label: 'All Status' }, { value: 'unpaid', label: longStatus }, { value: 'new', label: 'New' }, { value: 'order-issue', label: 'Order Issue' }],
    monthOptions: { all: 'All Dates', '2026-05': 'May 2026' },
    couriers: [{ value: 'gosend', label: longCourier }, { value: 'grab', label: 'GrabExpress' }],
    shipmentLocations: [], locationsUrl: '/wp-admin/admin.php?page=kiriminaja-setting&tab=locations',
    pagination: { page: Number(params.get('cpage') || 1), totalPages: 1, total: 0, perPage: Number(params.get('per_page') || 25) },
    rows: params.get('row_actions') === '1' ? [{
      id: 1, kaOrderId: 'KA-1', wcOrderId: 42, wcOrderUrl: '/order/42', detailUrl: '/detail/KA-1', deliveryType: 'instant',
      customer: { name: 'Fixture buyer', phone: '' }, courier: { code: 'fixture', service: 'Fixture courier', paymentLabel: 'NON COD' },
      vehicle: 'motorbike', status: { code: 105, label: 'Waiting for pickup', tone: 'info' }, printStatus: 'printed',
      route: { origin: 'Warehouse', destination: 'Destination', addressLines: [] },
      package: { weight: 1000, quantity: 1, actualShipping: 12000, paidShipping: 12000 },
      selection: { disabled: true, canPickup: false, canPrint: false, title: '' },
      actions: { preview: true, paymentUrl: '/wp-admin/admin.php?page=kiriminaja-request-pickup&instant_payment_id=PAY-1&open_payment=1', print: true, cancel: true, process: true, track: true, reconcile: true },
      actionData: {}, awb: 'AWB-1', createdAt: '2026-01-02',
    }] : [],
    bulk: { showPrint: true, printAction: 'kiriof_print', printNonce: 'isolated', printPreviewNonce: 'isolated', ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'isolated', pickupUrl: '/wp-admin/admin.php?page=kiriminaja-request-pickup' },
    i18n,
  };
  if (params.get('driver_not_found') === '1' && bootstrap.rows.length) {
    const row = JSON.parse(execFileSync('php', [root + 'tests/fixtures/transaction-instant-ui-runtime.php', JSON.stringify({delivery_type:'instant', service:'gosend', status:'request_pickup', order_id:'KA-1', awb:'AWB-1', instant_status_code:101, instant_payment_id:'PAY-1', instant_payment_status:'paid'})], {encoding:'utf8'}));
    Object.assign(bootstrap.rows[0].actions, {cancel:row.actions.cancel});
  }
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Transactions fixture</title>
    <style>body{margin:0;font-family:Arial,sans-serif;background:#f0f0f1}input,button{font:inherit}</style>
    ${['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'].map(name => `<link id="kiriof-${name}" rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}
    </head><body><main class="kiriof-workspace-shell" data-kiriof-transactions-page>
    <div data-kiriof-transactions-root></div><script type="application/json" data-kiriof-transactions-payload>${JSON.stringify(bootstrap).replace(/</g, '\\u003c')}</script>
    </main><script type="module" src="/assets/admin/dist/kiriminaja-admin-workspace.js"></script></body></html>`;
}

async function fixture(browser: any, tracking?: { url: string; blocked?: boolean; failed?: boolean }) {
  const requests: URL[] = [], unexpected: string[] = [];
  const trackingCalls: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test' && url.pathname === '/wp-admin/admin.php' && url.searchParams.get('page') === 'kiriminaja-transaction' && route.request.method === 'GET') {
      requests.push(url);
      return route.fulfill({ contentType: 'text/html', body: html(url) });
    }
    if (url.origin === 'https://fixture.test' && /^\/assets\/admin\/dist\/(?:assets\/)?[\w.-]+\.(?:js|css)$/.test(url.pathname)) {
      return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
    }
    if (tracking && url.origin === 'https://fixture.test' && url.pathname === '/wp-admin/admin-ajax.php' && route.request.method === 'POST') {
      const body = new URLSearchParams(route.request.postData || '');
      if (body.get('action') !== 'kiriof_instant_tracking' || body.get('data[order_ids]') !== '["KA-1"]') {
        unexpected.push('Unexpected admin mutation'); return route.fulfill({ status: 409, body: 'Blocked' });
      }
      trackingCalls.push(body.get('action')!);
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 200, data: { rows: [{ id: 'KA-1', status: tracking.failed ? 'unknown' : tracking.url ? 'tracked' : 'tracking_unavailable', tracking_url: tracking.url, message: '' }] } }) });
    }
    if (tracking && url.origin === 'https://courier.fixture.test' && url.pathname === '/live') {
      return route.fulfill({ contentType: 'text/html', headers: tracking.blocked ? { 'Content-Security-Policy': "frame-ancestors 'none'" } : {}, body: '<!doctype html><title>Fixture courier tracking</title><p>Courier tracking fixture</p><script>let parentAccess=false;try{parentAccess=Boolean(parent.document.body)}catch{}parent.postMessage({type:"tracking-fixture",parentAccess},"*")</script>' });
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(`${route.request.method} ${url.href}`);
    return route.fulfill({ status: 409, body: 'No live API or admin action requests permitted' });
  });
  return { requests, unexpected, trackingCalls };
}

function path(scope: string, active: boolean) {
  const params = new URLSearchParams({ page: 'kiriminaja-transaction', delivery_type: scope === 'instant' ? 'instant' : 'express', status: scope === 'issue' ? 'order-issue' : active ? 'unpaid' : 'all', per_page: '25', cpage: '3' });
  if (active) { params.set('key', 'KA-123 warehouse'); params.set('courier', 'gosend'); params.set('print_status', '1'); }
  return '/wp-admin/admin.php?' + params;
}

async function assertGeometry(browser: any, width: number, scope: string, active: boolean) {
  const result = await browser.evaluate((selector: string) => {
    const row = document.querySelector<HTMLElement>(selector)!;
    const bounds = row.getBoundingClientRect();
    const clear = row.querySelector<HTMLElement>('.kiriof-clear-filters');
    const controls = Array.from(row.querySelectorAll<HTMLElement>(':scope > [data-slot="input-group"], :scope > [data-slot="select-trigger"], :scope > .kiriof-courier-trigger'));
    const rects = controls.map(control => { const rect = control.getBoundingClientRect(); return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, centerY: rect.top + rect.height / 2, width: rect.width }; });
    return {
      instant: row.classList.contains('is-instant'), issue: row.classList.contains('is-order-issue'),
      tracks: getComputedStyle(row).gridTemplateColumns.split(' ').map(Number.parseFloat),
      row: { left: bounds.left, right: bounds.right, width: bounds.width }, rects,
      clear: clear ? { right: clear.getBoundingClientRect().right, width: clear.getBoundingClientRect().width } : null,
      overflow: row.scrollWidth - row.clientWidth,
      labels: Array.from(row.querySelectorAll<HTMLElement>('[data-slot="select-value"], .kiriof-courier-trigger .truncate')).map(label => {
        const css = getComputedStyle(label);
        return { overflow: css.overflow, ellipsis: css.textOverflow, nowrap: css.whiteSpace, clipped: label.scrollWidth > label.clientWidth };
      }),
      selectAlignment: Array.from(row.querySelectorAll<HTMLElement>('[data-slot="select-trigger"]')).map(trigger => {
        const label = trigger.querySelector<HTMLElement>('[data-slot="select-value"]')!;
        const icon = trigger.querySelector<SVGElement>(':scope > svg')!;
        const chevron = trigger.querySelector<SVGElement>('.kiriof-select-chevron')!;
        const text = document.createRange(); text.selectNodeContents(label);
        return { alignment: getComputedStyle(label).textAlign, labelLeft: label.getBoundingClientRect().left,
          textLeft: text.getBoundingClientRect().left, iconRight: icon.getBoundingClientRect().right,
          chevronRight: chevron.getBoundingClientRect().right, triggerRight: trigger.getBoundingClientRect().right };
      }),
    };
  }, rowSelector);
  const count = scope === 'regular' ? 5 : 4;
  expect(result.instant).toBe(scope === 'instant');
  expect(result.issue).toBe(scope === 'issue');
  expect(result.rects).toHaveLength(count);
  expect(Boolean(result.clear)).toBe(active);
  expect(result.tracks).toHaveLength(width > 1200 ? count + Number(active) : width > 782 ? 2 : 1);
  expect(result.overflow).toBeLessThanOrEqual(1);
  // Payment/Print labels inherit centered button text unless explicitly reset.
  // Assert both computed alignment and real text geometry, not only flex layout.
  for (const control of result.selectAlignment) {
    expect(control.alignment).toBe('left');
    expect(Math.abs(control.textLeft - control.labelLeft)).toBeLessThanOrEqual(1);
    expect(control.labelLeft - control.iconRight).toBeGreaterThanOrEqual(4);
    expect(control.labelLeft - control.iconRight).toBeLessThanOrEqual(12);
    expect(control.triggerRight - control.chevronRight).toBeLessThanOrEqual(16);
  }
  for (const rect of result.rects) {
    expect(rect.left).toBeGreaterThanOrEqual(result.row.left - 1);
    expect(rect.right).toBeLessThanOrEqual(result.row.right + 1);
    expect(rect.width).toBeGreaterThan(100);
    // Every control fills a real track; a phantom grid cell cannot pass this.
    expect(Math.min(...result.tracks.slice(0, width > 1200 ? count : undefined).map(track => Math.abs(track - rect.width)))).toBeLessThanOrEqual(2);
  }
  const widths = result.rects.map(rect => rect.width);
  expect(Math.max(...widths) / Math.min(...widths)).toBeLessThanOrEqual(1.22);
  // items-center legitimately gives the 32px search input a different top
  // from the 34px triggers. Check expected grid rows by their vertical centers,
  // not rounded tops, while retaining track widths and column ordering checks.
  const columns = width > 1200 ? count : width > 782 ? 2 : 1;
  for (let start = 0; start < count; start += columns) {
    const controls = result.rects.slice(start, start + columns);
    const centers = controls.map(rect => rect.centerY);
    expect(Math.max(...centers) - Math.min(...centers)).toBeLessThanOrEqual(1);
    for (let column = 1; column < controls.length; column++) {
      expect(controls[column].left).toBeGreaterThanOrEqual(controls[column - 1].right);
    }
    if (start > 0) {
      const previous = result.rects.slice(start - columns, start);
      expect(Math.min(...controls.map(rect => rect.top))).toBeGreaterThan(Math.max(...previous.map(rect => rect.bottom)));
      expect(Math.abs(controls[0].left - result.rects[0].left)).toBeLessThanOrEqual(1);
    }
  }
  if (width > 1200) {
    const lastRight = result.clear ? result.clear.right : result.rects[count - 1].right;
    expect(Math.abs(result.row.right - lastRight)).toBeLessThanOrEqual(2);
  }
  if (active) {
    expect(result.labels.some(label => label.clipped)).toBe(true);
    for (const label of result.labels) {
      expect(label.ellipsis).toBe('ellipsis');
      expect(label.nowrap).toBe('nowrap');
      expect(label.overflow).toBe('hidden');
    }
  }
}

for (const width of [1440, 1100, 390]) {
  test(`Transaction filter production grid and Bits Select navigation at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 1000 });
    const boundary = await fixture(browser);
    for (const scope of ['instant', 'regular', 'issue']) {
      for (const active of [false, true]) {
        await app.open(path(scope, active));
        await expect(browser.locator(rowSelector)).toBeVisible();
        await expect(browser.locator('.kiriof-empty-cell')).toContainText('No transactions found.');
        await assertGeometry(browser, width, scope, active);
      }
    }

    // Start Instant with a forbidden legacy COD parameter. Use actual portaled
    // Bits UI options; allow the production entry to fetch/remount or assign a
    // document, rather than replacing its navigation callback in a test wrapper.
    await app.open(path('instant', true) + '&cod=1&search_by=awb&month=2026-05');
    await expect(browser.locator(rowSelector)).toBeVisible();
    for (const [value, label] of [['0', 'Unprinted'], ['1', longPrinted], ['', 'All Prints']]) {
      const before = boundary.requests.length;
      await browser.locator(rowSelector + ' [data-slot="select-trigger"]').click();
      const option = browser.locator('[role="option"]').filter({ hasText: label });
      await expect(option).toBeVisible();
      await option.click();
      await expect.poll(() => boundary.requests.length).toBe(before + 1);
      await expect.poll(() => browser.evaluate(() => new URL(location.href).searchParams.get('print_status') || '')).toBe(value);
      await expect(browser.locator(rowSelector + ' [data-slot="select-value"]')).toContainText(label);
      const requested = boundary.requests[boundary.requests.length - 1];
      expect(requested.searchParams.get('print_status') || '').toBe(value);
      expect(requested.searchParams.get('delivery_type')).toBe('instant');
      expect(requested.searchParams.get('key')).toBe('KA-123 warehouse');
      expect(requested.searchParams.get('courier')).toBe('gosend');
      expect(requested.searchParams.get('status')).toBe('unpaid');
      expect(requested.searchParams.get('month')).toBe('2026-05');
      expect(requested.searchParams.get('per_page')).toBe('25');
      expect(requested.searchParams.get('cpage')).toBe('1');
      expect(requested.searchParams.has('cod')).toBe(false);
      expect(requested.searchParams.has('search_by')).toBe(false);
      await assertGeometry(browser, width, 'instant', true);
    }
    expect(boundary.unexpected).toEqual([]);
  });
}

for (const width of [390, 1440]) {
  test(`transaction Scan to Pay belongs to a connected accessible action group at ${width}px`, async ({ app, browser }) => {
    const state = await fixture(browser);
    await browser.setViewport({ width, height: 1000 });
    await app.open('/wp-admin/admin.php?page=kiriminaja-transaction&delivery_type=instant&row_actions=1');
    const payment = browser.locator('td.is-actions a[aria-label="Scan to Pay"]');
    await expect(payment).toBeVisible();
    const geometry = await browser.evaluate(() => {
      const row = document.querySelector<HTMLTableRowElement>('.kiriof-transactions-table tbody tr')!;
      const group = row.querySelector<HTMLElement>('[data-slot="button-group"]')!;
      const buttons = [...group.querySelectorAll<HTMLElement>('[data-slot="button"]')];
      const bounds = group.getBoundingClientRect();
      return { role: group.getAttribute('role'), label: group.getAttribute('aria-label'),
        courierHasPayment: Boolean(row.children[2].querySelector('[aria-label="Scan to Pay"]')),
        labels: buttons.map(button => button.getAttribute('aria-label')),
        gaps: buttons.slice(1).map((button, index) => button.getBoundingClientRect().left - buttons[index].getBoundingClientRect().right),
        heights: buttons.map(button => button.getBoundingClientRect().height),
        borderLeft: buttons.slice(1).map(button => getComputedStyle(button).borderLeftWidth),
        innerRadii: buttons.slice(1, -1).map(button => [getComputedStyle(button).borderTopLeftRadius, getComputedStyle(button).borderTopRightRadius]),
        overflow: group.scrollWidth - group.clientWidth,
        contained: buttons.every(button => button.getBoundingClientRect().left >= bounds.left - 1 && button.getBoundingClientRect().right <= bounds.right + 1),
      };
    });
    expect(geometry.role).toBe('group'); expect(geometry.label).toBe('Action #42');
    expect(geometry.courierHasPayment).toBe(false);
    expect(geometry.labels).toEqual(['Shipment detail', 'Scan to Pay', 'Recheck booking', 'Live tracking', 'Cancel Instant shipment', 'Process Shipment', 'Print Labels']);
    expect(geometry.contained).toBe(true); expect(geometry.overflow).toBeLessThanOrEqual(1);
    for (const gap of geometry.gaps) expect(Math.abs(gap)).toBeLessThanOrEqual(1);
    for (const height of geometry.heights) expect(height).toBeGreaterThanOrEqual(28);
    expect(geometry.borderLeft).toEqual(Array(6).fill('0px'));
    expect(geometry.innerRadii).toEqual(Array(5).fill(['0px', '0px']));
    await browser.evaluate(() => document.querySelector<HTMLAnchorElement>('td.is-actions a[aria-label="Shipment detail"]')!.focus());
    await browser.keyboard.press('Tab');
    await expect.poll(() => browser.evaluate(() => document.activeElement?.getAttribute('aria-label'))).toBe('Scan to Pay');
    await expect.poll(() => browser.evaluate(() => {
      const id = document.activeElement?.getAttribute('aria-describedby');
      return id ? document.getElementById(id)?.textContent?.trim() : null;
    })).toBe('Scan to Pay');
    await browser.keyboard.press('Tab');
    await expect.poll(() => browser.evaluate(() => document.activeElement?.getAttribute('aria-label'))).toBe('Recheck booking');
    expect(state.requests).toHaveLength(1); expect(state.unexpected).toEqual([]);
  });
}

for (const width of [390, 1440]) {
  for (const available of [false, true]) {
    test(`Instant tracking dialog ${available ? 'embeds courier page' : 'explains unavailable tracking'} at ${width}px`, async ({ app, browser }) => {
      const url = available ? 'https://courier.fixture.test/live?token=fixture%2Bmap' : '';
      const state = await fixture(browser, { url });
      await browser.setViewport({ width, height: 900 });
      await app.open('/wp-admin/admin.php?page=kiriminaja-transaction&delivery_type=instant&row_actions=1');
      await browser.evaluate(() => {
        (window as any).__trackingFrameMessages = [];
        window.addEventListener('message', event => {
          const frame = document.querySelector<HTMLIFrameElement>('iframe.kiriof-live-tracking-frame');
          if (event.source === frame?.contentWindow && event.data?.type === 'tracking-fixture') {
            (window as any).__trackingFrameMessages.push({ origin: event.origin, parentAccess: event.data.parentAccess });
          }
        });
      });
      await browser.locator('td.is-actions button[aria-label="Live tracking"]').click();
      await expect(browser.locator('[role="dialog"]')).toBeVisible();
      if (available) {
        await expect.poll(() => browser.evaluate(() => (window as any).__trackingFrameMessages)).toEqual([{ origin: 'null', parentAccess: false }]);
        await expect(browser.locator('iframe.kiriof-live-tracking-frame')).toHaveAttribute('src', url);
        await expect(browser.locator('[role="dialog"] a')).toHaveAttribute('href', url);
        await expect(browser.locator('[role="dialog"] a')).toHaveAttribute('target', '_blank');
        await expect(browser.locator('[role="dialog"] a')).toHaveAttribute('rel', 'noopener noreferrer');
        await expect.poll(() => browser.evaluate(() => {
          const frame = document.querySelector<HTMLIFrameElement>('iframe.kiriof-live-tracking-frame')!;
          const dialog = document.querySelector<HTMLElement>('[role="dialog"]')!;
          const f = frame.getBoundingClientRect(), d = dialog.getBoundingClientRect();
          return { title: frame.title, height: f.height, frameInside: f.left >= d.left && f.right <= d.right,
            dialogInside: d.left >= 0 && d.right <= innerWidth && d.height <= innerHeight,
            overflow: dialog.scrollWidth - dialog.clientWidth };
        })).toMatchObject({ title: 'Live tracking', frameInside: true, dialogInside: true, overflow: 0 });
        if (width === 1440) await expect.poll(() => browser.evaluate(() => document.querySelector('[role="dialog"]')!.getBoundingClientRect().width)).toBeGreaterThan(900);
      } else {
        await expect(browser.locator('[role="dialog"]')).toContainText(i18n.instantTrackingUnavailable);
        await expect(browser.locator('[role="dialog"]')).not.toContainText('Tracking available');
        expect(await browser.evaluate(() => document.querySelectorAll('[role="dialog"] iframe').length)).toBe(0);
      }
      expect(state.trackingCalls).toEqual(['kiriof_instant_tracking']);
      await browser.locator('[role="dialog"] button').filter({ hasText: 'Refresh tracking' }).click();
      await expect.poll(() => state.trackingCalls.length).toBe(2);
      await browser.locator('[data-slot="dialog-footer"] button').filter({ hasText: 'Close' }).click();
      await expect(browser.locator('[role="dialog"]')).not.toBeVisible();
      expect(state.unexpected).toEqual([]);
    });
  }
}

test('Instant tracking keeps an external fallback when courier framing is blocked', async ({ app, browser }) => {
  const url = 'https://courier.fixture.test/live';
  const state = await fixture(browser, { url, blocked: true });
  await app.open('/wp-admin/admin.php?page=kiriminaja-transaction&delivery_type=instant&row_actions=1');
  await browser.locator('td.is-actions button[aria-label="Live tracking"]').click();
  await expect(browser.locator('iframe.kiriof-live-tracking-frame')).toHaveAttribute('src', url);
  await expect(browser.locator('[role="dialog"]')).toContainText(i18n.instantTrackingEmbedHint);
  await expect(browser.locator('[role="dialog"] a')).toHaveAttribute('href', url);
  expect(state.trackingCalls).toEqual(['kiriof_instant_tracking']);
  expect(state.unexpected).toEqual([]);
});

for (const width of [390, 1440]) {
  for (const failed of [false, true]) {
    test(`Driver Not Found has no Cancel and tracking uses ${failed ? 'error' : 'unavailable'} Empty with Alert at ${width}px`, async ({ app, browser }) => {
      const state = await fixture(browser, {url:'', failed});
      await browser.setViewport({width, height:900});
      await app.open('/wp-admin/admin.php?page=kiriminaja-transaction&delivery_type=instant&row_actions=1&driver_not_found=1');
      expect(await browser.evaluate(() => document.querySelectorAll('td.is-actions button[aria-label="Cancel Instant shipment"]').length)).toBe(0);
      await browser.locator('td.is-actions button[aria-label="Live tracking"]').click();
      await expect(browser.locator('[role="dialog"] [data-slot="empty"]')).toBeVisible();
      await expect(browser.locator('[data-slot="empty-title"]')).toContainText(failed ? i18n.instantTrackingErrorTitle : i18n.instantTrackingUnavailableTitle);
      await expect(browser.locator('[role="dialog"] [data-slot="alert"]')).toHaveAttribute('role',failed?'alert':'status');
      await expect(browser.locator('[role="dialog"]')).not.toContainText('Unknown outcome');
      await expect.poll(() => browser.evaluate(() => {
        const dialog=document.querySelector<HTMLElement>('[role="dialog"]')!, empty=dialog.querySelector<HTMLElement>('[data-slot="empty"]')!, alert=dialog.querySelector<HTMLElement>('[data-slot="alert"]')!;
        const d=dialog.getBoundingClientRect(), e=empty.getBoundingClientRect(), a=alert.getBoundingClientRect();
        return {compact:d.width<=600,visible:d.left>=0&&d.right<=innerWidth,contained:e.left>=d.left&&e.right<=d.right&&a.left>=d.left&&a.right<=d.right,overflow:dialog.scrollWidth-dialog.clientWidth,frames:dialog.querySelectorAll('iframe').length};
      })).toEqual({compact:true, visible:true, contained:true, overflow:0, frames:0});
      expect(state.trackingCalls).toEqual(['kiriof_instant_tracking']); expect(state.unexpected).toEqual([]);
      await browser.locator('[data-slot="dialog-footer"] button').filter({hasText:'Close'}).click();
    });
  }
}
