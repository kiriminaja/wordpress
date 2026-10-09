import { describe, expect, test } from 'bun:test';
import { buyerRuntimeSource, buyerBrowserContext } from './helpers/buyer-runtime-source';
import { runInNewContext } from 'node:vm';

const source = await buyerRuntimeSource('coupon');
const shipping = { code: 'ongkir', discount_type: 'kiriof_fixed_shipping_discount' };
const native = { code: 'produk', discount_type: 'percent' };
const strings = {
  couponApplied: 'Kupon pengiriman "%s" diterapkan.',
  couponCombined: 'Kupon produk %2$s bersama kupon pengiriman %1$s.',
};
type Notice = { id: string; content: string; status: string; context?: string };

function setup({
  modern = true,
  coupons = [shipping],
  notices = [] as Notice[],
  template = 'Kode kupon «%s» sudah diterapkan (keranjang).',
  i18n = true,
} = {}) {
  const created: Array<{ status: string; content: string; options: any }> = [];
  const removed: any[] = [];
  const subscribers: Array<() => void> = [];
  let filters: any;
  const wp = {
    ...(i18n
      ? {
          i18n: {
            __: (_message: string, domain: string) => {
              expect(domain).toBe('woocommerce');
              return template;
            },
          },
        }
      : {}),
    data: {
      select: (store: string) =>
        store === 'wc/store/cart'
          ? { getCartData: () => ({ coupons }) }
          : { getNotices: () => notices },
      dispatch: () => ({
        createNotice: (status: string, content: string, options: any) =>
          created.push({ status, content, options }),
        removeNotice: (...args: any[]) => removed.push(args),
      }),
      subscribe: (callback: () => void) => subscribers.push(callback),
    },
  };
  runInNewContext(source, {
    window: {
      document: { querySelector: () => null, querySelectorAll: () => [] },
      wp,
      wc: modern
        ? {
            blocksCheckout: {
              registerCheckoutFilters: (_namespace: string, value: any) => {
                filters = value;
              },
            },
          }
        : {},
      kiriofBlockCheckoutStrings: strings,
      setTimeout: () => 0,
    },
    document: { querySelector: () => null, querySelectorAll: () => [] },
    URLSearchParams,
  });
  return {
    filters,
    created,
    removed,
    subscribers,
    notify: () => subscribers.forEach((callback) => callback()),
  };
}

function notice(content: string, status = 'success', id = 'coupon-form'): Notice {
  return { id, content, status, context: 'wc/checkout' };
}

describe('localized Block coupon notices', () => {
  test('modern filter formats localized shipping success and keeps context', () => {
    const runtime = setup();
    expect(
      runtime.filters.showApplyCouponNotice(
        true,
        {},
        { couponCode: 'ONGKIR', context: 'wc/checkout' },
      ),
    ).toBe(false);
    expect(runtime.created).toEqual([
      {
        status: 'info',
        content: 'Kupon pengiriman "ONGKIR" diterapkan.',
        options: { id: 'coupon-form', type: 'snackbar', context: 'wc/checkout' },
      },
    ]);
    expect(runtime.subscribers).toHaveLength(0);
  });

  test('combines native coupons with translated, reordered placeholders', () => {
    const runtime = setup({
      coupons: [shipping, native, { code: 'hemat', discount_type: 'fixed_cart' }],
    });
    expect(runtime.filters.showApplyCouponNotice(true, {}, { couponCode: 'ongkir' })).toBe(false);
    expect(runtime.created[0].content).toBe(
      'Kupon produk produk, hemat bersama kupon pengiriman ongkir.',
    );
  });

  test('non-shipping codes and missing arguments retain the native result', () => {
    const runtime = setup({ coupons: [shipping, native] });
    expect(runtime.filters.showApplyCouponNotice(true, {}, { couponCode: 'produk' })).toBe(true);
    expect(runtime.filters.showApplyCouponNotice(false, {}, { couponCode: 'ong' })).toBe(false);
    expect(runtime.filters.showApplyCouponNotice(true, {}, undefined)).toBe(true);
    expect(runtime.created).toHaveLength(0);
  });

  test('modern API never subscribes to or converts native notices', () => {
    const runtime = setup({
      notices: [notice('Kode kupon «ongkir» sudah diterapkan (keranjang).')],
    });
    runtime.notify();
    expect(runtime.subscribers).toHaveLength(0);
    expect(runtime.created).toHaveLength(0);
    expect(runtime.removed).toHaveLength(0);
  });

  test('legacy fallback matches the entire localized Woo template and exact coupon code', () => {
    const runtime = setup({
      modern: false,
      coupons: [shipping, native],
      notices: [notice('Kode kupon «ongkir» sudah diterapkan (keranjang).')],
    });
    runtime.notify();
    runtime.notify();
    expect(runtime.created).toHaveLength(1);
    expect(runtime.created[0].content).toBe('Kupon produk produk bersama kupon pengiriman ongkir.');
    expect(runtime.removed).toEqual([['coupon-form', 'wc/checkout']]);
  });

  test('legacy leaves notices unchanged without the Woo translation API', () => {
    const runtime = setup({
      modern: false,
      i18n: false,
      notices: [notice('Coupon code "ongkir" has been applied to your cart.')],
    });
    runtime.notify();
    expect(runtime.subscribers).toHaveLength(0);
    expect(runtime.created).toHaveLength(0);
    expect(runtime.removed).toHaveLength(0);
  });

  test.each([
    notice('Kode kupon «ongkir» sudah diterapkan (keranjang).', 'error'),
    notice('Kode kupon «ongkir» sudah diterapkan (keranjang).', 'warning'),
    notice('Kode kupon «ongkir» tidak valid.'),
    notice('Coupon code "ongkir" has been applied to your cart.'),
    notice('Kode kupon «ong» sudah diterapkan (keranjang).'),
    notice('Kode kupon «produk» sudah diterapkan (keranjang).'),
    notice('Kode kupon «ongkir» sudah diterapkan (keranjang). tambahan'),
    notice('Kode kupon «ongkir» sudah diterapkan (keranjang).', 'success', 'other-form'),
  ])('legacy does not rewrite unrelated/error notices: %j', (item) => {
    const runtime = setup({ modern: false, coupons: [shipping, native], notices: [item] });
    runtime.notify();
    expect(runtime.created).toHaveLength(0);
    expect(runtime.removed).toHaveLength(0);
  });

  test('template regex metacharacters are literal, not wildcards', () => {
    const runtime = setup({
      modern: false,
      notices: [notice('Kode kupon «ongkir» sudah diterapkan XkeranjangY!')],
    });
    runtime.notify();
    expect(runtime.created).toHaveLength(0);
  });

  test('localized replacement treats dollar sequences in coupon codes literally', () => {
    const runtime = setup({ coupons: [{ ...shipping, code: 'ship$&' }, native] });
    runtime.filters.showApplyCouponNotice(true, {}, { couponCode: 'ship$&' });
    expect(runtime.created[0].content).toBe('Kupon produk produk bersama kupon pengiriman ship$&.');
  });
});
