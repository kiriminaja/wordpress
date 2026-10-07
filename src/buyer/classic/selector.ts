import { mount, unmount } from 'svelte';
import BuyerCombobox from '../components/BuyerCombobox.svelte';
import { SelectorState } from '../state/selector-state.svelte';
import { searchSubdistrict } from '../api/subdistrict';
import type { SelectorBridge, SelectorConfig, SelectorOption } from '../types/selector';

type JQueryField = {
  data: (key: string, value?: unknown) => unknown;
  selectWoo?: (action: string) => void;
  select2?: (action: string) => void;
  on: (events: string, callback: () => void) => void;
  off: (events: string) => void;
};
type ClassicWindow = Window & {
  kiriofBillingAddressConfig?: SelectorConfig;
  kiriofAjax?: { nonce?: string; ajaxurl?: string };
  kiriofBuyerSelectors?: SelectorBridge;
  kiriofClassicChoices?: SelectorBridge;
  jQuery?: (node: Element) => JQueryField;
  kiriofSetClassicDistrictLabel?: (field: JQueryField, label: string, separate: number) => void;
};
type RecordEntry = {
  select: HTMLSelectElement;
  wrapper: HTMLDivElement;
  component: ReturnType<typeof mount>;
  state: SelectorState;
  signature: string;
  scope: string;
  generation: number;
  debounce?: ReturnType<typeof setTimeout>;
  deadline?: ReturnType<typeof setTimeout>;
  controller?: AbortController;
  change: EventListener;
  tabindex: string | null;
  ariaHidden: string | null;
};
const metadata = [
  'data-courier',
  'data-label',
  'data-price',
  'data-original-price',
  'data-savings',
  'data-note',
];

/** Minimal DOM bridge for Classic Woo; component state never owns checkout writes. */
export function startClassicSelectors(
  root: ClassicWindow = window as ClassicWindow,
): SelectorBridge {
  if (root.kiriofBuyerSelectors) return root.kiriofBuyerSelectors;
  const document = root.document;
  const config = root.kiriofBillingAddressConfig || {};
  const strings = config.i18n || {};
  const districtKey = config.fieldKey || 'kiriof_destination_area';
  const records = new Map<HTMLSelectElement, RecordEntry>();
  const displays = new WeakMap<HTMLSelectElement, Map<string, (string | null)[]>>();
  let stopped = false;
  let queued = false;
  let observer: MutationObserver | undefined;
  const active = () =>
    !stopped &&
    !!document.querySelector('form.checkout, form.woocommerce-checkout') &&
    !document.querySelector('.wc-block-checkout');
  const district = (select: HTMLSelectElement) =>
    select.name === districtKey || select.name === 'kiriof_shipping_destination_area';
  const shipping = (select: HTMLSelectElement) =>
    select.classList.contains('kiriof-classic-shipping-method-select');
  const eligible = (select: HTMLSelectElement) =>
    !!select.closest('form.checkout, form.woocommerce-checkout') &&
    (district(select) ||
      shipping(select) ||
      ['billing_state', 'shipping_state'].includes(select.id));
  function addressScope(select: HTMLSelectElement) {
    const type = select.name === 'kiriof_shipping_destination_area' ? 'shipping' : 'billing';
    const country = document.getElementById(`${type}_country`) as HTMLSelectElement | null;
    const state = document.getElementById(`${type}_state`) as HTMLSelectElement | null;
    const separate = document.querySelector<HTMLInputElement>('[name="ship_to_different_address"]');
    return JSON.stringify([
      country?.value ?? config[`${type}Country`],
      state?.value ?? '',
      !!separate?.checked,
    ]);
  }
  function preserve(select: HTMLSelectElement) {
    if (!shipping(select)) return;
    const saved = displays.get(select) || new Map();
    for (const option of select.options) {
      if (option.hasAttribute('data-label'))
        saved.set(
          option.value,
          metadata.map((key) => option.getAttribute(key)),
        );
      else if (saved.has(option.value))
        metadata.forEach((key, i) => {
          const value = saved.get(option.value)?.[i];
          if (value !== null && value !== undefined && option.getAttribute(key) !== value)
            option.setAttribute(key, value);
        });
    }
    displays.set(select, saved);
  }
  function options(select: HTMLSelectElement): SelectorOption[] {
    return Array.from(select.options, (option) => ({
      value: option.value,
      label: option.getAttribute('data-label') || option.text,
      disabled:
        option.disabled ||
        (option.parentElement?.tagName === 'OPTGROUP' &&
          (option.parentElement as HTMLOptGroupElement).disabled),
      courier: option.dataset.courier,
      price: option.dataset.price,
      originalPrice: option.dataset.originalPrice,
      savings: option.dataset.savings,
      note: option.dataset.note,
    }));
  }
  function destroyWoo(select: HTMLSelectElement) {
    const field = root.jQuery?.(select);
    const instance = field?.data('select2') || field?.data('selectWoo');
    if (field && instance) {
      // Invoke the plugin on this field, never on a global selector (countries
      // remain Woo-owned). A legacy initializer can run after our first mount.
      const destroy = field.data('select2')
        ? field.select2 || field.selectWoo
        : field.selectWoo || field.select2;
      destroy?.call(field, 'destroy');
    }
    // An old plugin may have lost its jQuery data before leaving its sibling
    // behind. Only remove a container whose ARIA IDs identify this exact select.
    const containerId = `select2-${select.id}-container`;
    const resultsId = `select2-${select.id}-results`;
    if (select.id) {
      const siblings = [select.previousElementSibling, select.nextElementSibling];
      if (select.parentElement?.classList.contains('kiriof-buyer-selector'))
        siblings.push(...select.parentElement.querySelectorAll('.select2-container'));
      for (const sibling of siblings) {
        if (!sibling?.classList.contains('select2-container')) continue;
        const owned = Array.from(
          sibling.querySelectorAll('[id], [aria-labelledby], [aria-controls], [aria-owns]'),
        ).some(
          (node) =>
            node.id === containerId ||
            ['aria-labelledby', 'aria-controls', 'aria-owns'].some((attribute) =>
              (node.getAttribute(attribute) || '')
                .split(/\s+/)
                .some((id) => id === containerId || id === resultsId),
            ),
        );
        if (owned) sibling.remove();
      }
    }
    select.classList.remove('select2-hidden-accessible');
    if (records.has(select)) {
      if (select.getAttribute('tabindex') !== '-1') select.setAttribute('tabindex', '-1');
      if (select.getAttribute('aria-hidden') !== 'true') select.setAttribute('aria-hidden', 'true');
    }
  }
  function signature(select: HTMLSelectElement) {
    return JSON.stringify([
      select.value,
      select.disabled,
      select.required,
      select.getAttribute('aria-required'),
      select.getAttribute('aria-invalid'),
      select.getAttribute('aria-describedby'),
      options(select),
    ]);
  }
  function synchronize(record: RecordEntry) {
    const { select, state } = record;
    preserve(select);
    const next = signature(select);
    if (next === record.signature) return;
    state.value = select.value;
    state.options = options(select);
    state.disabled = select.disabled;
    state.required = select.getAttribute('aria-required') ?? (select.required ? 'true' : undefined);
    state.invalid = select.getAttribute('aria-invalid') ?? undefined;
    state.describedBy = select.getAttribute('aria-describedby') ?? undefined;
    record.signature = next;
  }
  function cancel(record: RecordEntry) {
    record.generation++;
    clearTimeout(record.debounce);
    clearTimeout(record.deadline);
    record.controller?.abort();
    record.controller = undefined;
    record.state.loading = false;
  }
  function syncLabel(record: RecordEntry) {
    if (!district(record.select)) return;
    const label = record.select.value ? record.select.selectedOptions[0]?.text || '' : '';
    const field = root.jQuery?.(record.select);
    if (field) {
      field.data('kiriofSelectedDistrictText', label);
      root.kiriofSetClassicDistrictLabel?.(
        field,
        label,
        document.querySelectorAll('[name="ship_to_different_address"]:checked').length,
      );
    }
  }
  function search(record: RecordEntry, value: string) {
    if (!district(record.select)) return;
    cancel(record);
    const term = value.trim();
    const state = record.state;
    state.term = term;
    state.error = false;
    // Lookup results are UI-only until the user explicitly chooses one.
    state.options = options(record.select).filter((option) => option.value === record.select.value);
    if (term.length < 3) {
      state.status = term ? strings.searchMinChars || 'Enter at least 3 characters' : '';
      return;
    }
    const generation = record.generation;
    const scope = addressScope(record.select);
    const current = () =>
      active() &&
      record.select.isConnected &&
      generation === record.generation &&
      scope === addressScope(record.select) &&
      term === state.term;
    const failed = () => {
      state.loading = false;
      state.error = true;
      state.status =
        strings.searchError ||
        strings.lookupError ||
        'Could not search subdistricts. Please try again.';
    };
    state.loading = true;
    state.status = strings.searching || 'Searching…';
    record.debounce = setTimeout(() => {
      if (!current()) return;
      const controller = new AbortController();
      record.controller = controller;
      record.deadline = setTimeout(() => {
        if (current()) {
          cancel(record);
          failed();
        }
      }, 10000);
      const ajax = root.kiriofAjax || {};
      searchSubdistrict(
        ajax.ajaxurl || config.ajaxUrl || '',
        ajax.nonce || config.nonce || '',
        term,
        controller.signal,
      )
        .then((rows) => {
          if (!current()) return;
          const results = rows.filter((row) => row.value !== record.select.value);
          state.options = [
            ...options(record.select).filter((option) => option.value === record.select.value),
            ...results,
          ];
          state.status = results.length ? '' : strings.noResults || 'No results found';
        })
        .catch(() => {
          if (current()) failed();
        })
        .finally(() => {
          if (generation === record.generation) {
            clearTimeout(record.deadline);
            record.controller = undefined;
            state.loading = false;
          }
        });
    }, 250);
  }
  function choose(record: RecordEntry, value: string, activated = false) {
    const select = record.select;
    if (!active() || !select.isConnected || select.disabled) return;
    if (select.value === value && !(activated && shipping(select))) return;
    const option = record.state.options.find((entry) => entry.value === value);
    if (value && (!option || option.disabled)) {
      synchronize(record);
      return;
    }
    if (!Array.from(select.options).some((entry) => entry.value === value)) {
      const native = document.createElement('option');
      native.value = value;
      native.text = option?.label || strings.selectOption || 'Select Option';
      select.append(native);
    }
    cancel(record);
    select.value = value;
    record.state.value = value;
    record.state.term = '';
    record.state.status = '';
    record.state.error = false;
    syncLabel(record);
    record.signature = '';
    synchronize(record);
    // Exactly one explicit native change: neither bind:value nor refresh emits writes.
    select.dispatchEvent(
      new CustomEvent('change', {
        bubbles: true,
        detail: { value, reviewedShipping: shipping(select) },
      }),
    );
  }
  function init(select: HTMLSelectElement) {
    if (!active() || !eligible(select)) return null;
    const existing = records.get(select);
    if (existing) return existing.state;
    preserve(select);
    destroyWoo(select);
    const label = Array.from(document.querySelectorAll<HTMLLabelElement>('label[for]')).find(
      (item) => item.htmlFor === select.id,
    );
    if (label && !label.id) label.id = `${select.id}-buyer-selector-label`;
    const state = new SelectorState();
    state.labelId = label?.id;
    state.label =
      select.getAttribute('aria-label') ||
      label?.textContent?.trim() ||
      strings.selectOption ||
      'Select Option';
    const wrapper = document.createElement('div');
    wrapper.className = 'kiriof-buyer-selector';
    select.before(wrapper);
    wrapper.append(select);
    const target = document.createElement('div');
    target.className = 'kiriof-buyer-selector-ui';
    wrapper.append(target);
    const record: RecordEntry = {
      select,
      wrapper,
      state,
      component: {} as ReturnType<typeof mount>,
      signature: '',
      scope: addressScope(select),
      generation: 0,
      tabindex: select.getAttribute('tabindex'),
      ariaHidden: select.getAttribute('aria-hidden'),
      change: () => {
        cancel(record);
        state.term = '';
        state.status = '';
        state.error = false;
        syncLabel(record);
        record.signature = '';
        synchronize(record);
        queueRefresh();
      },
    };
    records.set(select, record);
    synchronize(record);
    select.classList.add('kiriof-buyer-native-select');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    select.addEventListener('change', record.change, true);
    record.component = mount(BuyerCombobox, {
      target,
      props: {
        state,
        district: district(select),
        shipping: shipping(select),
        logos: config.courierLogos,
        strings,
        onChoose: (value: string, activated?: boolean) => choose(record, value, activated),
        onSearch: (term: string) => search(record, term),
      },
    });
    if (shipping(select)) {
      select.classList.add('kiriof-classic-shipping-method-select--enhanced');
      select
        .closest('.kiriof-classic-shipping-package, td')
        ?.classList.add('kiriof-shipping-methods-ready');
    }
    return state;
  }
  function destroy(record: RecordEntry) {
    cancel(record);
    record.select.removeEventListener('change', record.change, true);
    void unmount(record.component);
    const connected = record.select.isConnected;
    // Woo may replace the original select with a select or a plain state input inside our wrapper.
    if (record.wrapper.parentNode) {
      for (const node of record.wrapper.querySelectorAll('input, select')) {
        if (node === record.select || (record.select.id && node.id === record.select.id))
          record.wrapper.before(node);
      }
    }
    record.wrapper.remove();
    record.select.classList.remove(
      'kiriof-buyer-native-select',
      'kiriof-classic-shipping-method-select--enhanced',
    );
    for (const [name, value] of [
      ['tabindex', record.tabindex],
      ['aria-hidden', record.ariaHidden],
    ]) {
      if (value === null) record.select.removeAttribute(name as string);
      else record.select.setAttribute(name as string, value as string);
    }
    if (!connected) record.select.remove();
    records.delete(record.select);
  }
  function refresh() {
    if (stopped) return;
    for (const record of records.values()) {
      if (!active() || !record.select.isConnected || !eligible(record.select)) {
        destroy(record);
        continue;
      }
      destroyWoo(record.select);
      const scope = addressScope(record.select);
      if (scope !== record.scope) {
        cancel(record);
        record.state.term = '';
        record.state.status = '';
        record.state.error = false;
        record.signature = '';
        record.scope = scope;
      }
      synchronize(record);
    }
    if (active())
      document
        .querySelectorAll<HTMLSelectElement>(
          'form.checkout select, form.woocommerce-checkout select',
        )
        .forEach(init);
  }
  function queueRefresh() {
    if (queued || stopped) return;
    queued = true;
    queueMicrotask(() => {
      queued = false;
      refresh();
    });
  }
  function addressChange(event: Event) {
    if (
      (event.target as Element)?.matches?.(
        '#billing_country, #shipping_country, #billing_state, #shipping_state, [name="ship_to_different_address"]',
      )
    ) {
      for (const record of records.values()) if (district(record.select)) cancel(record);
    }
    queueRefresh();
  }
  function start() {
    if (stopped) return;
    refresh();
    observer = new MutationObserver((mutations) => {
      const relevant = mutations.some((mutation) => {
        const target =
          mutation.target instanceof Element ? mutation.target : mutation.target.parentElement;
        if (target?.closest('select')) return true;
        if (mutation.type !== 'childList') return false;
        return [...mutation.addedNodes, ...mutation.removedNodes].some(
          (node) =>
            node instanceof Element &&
            (node.matches('select, input, form, .wc-block-checkout, .select2-container') ||
              !!node.querySelector('select, form, .wc-block-checkout')),
        );
      });
      if (relevant) queueRefresh();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      characterData: true,
      attributeFilter: [
        'disabled',
        'required',
        'selected',
        'value',
        'aria-required',
        'aria-invalid',
        'aria-describedby',
        ...metadata,
      ],
    });
    document.addEventListener('change', addressChange, true);
    document.body.addEventListener('updated_checkout', queueRefresh);
    document.body.addEventListener('country_to_state_changed', queueRefresh);
    root
      .jQuery?.(document.body)
      .on(
        'updated_checkout.kiriofBuyerSelectors country_to_state_changed.kiriofBuyerSelectors',
        queueRefresh,
      );
  }
  const bridge: SelectorBridge = {
    active,
    refresh,
    initDistrict: (select) => (district(select) ? init(select) : null),
    initShipping: (select) => (shipping(select) ? init(select) : null),
  };
  root.kiriofBuyerSelectors = bridge;
  root.kiriofClassicChoices = bridge; // Compatibility for guarded legacy Classic handlers only.
  root.addEventListener(
    'pagehide',
    () => {
      stopped = true;
      observer?.disconnect();
      document.removeEventListener('DOMContentLoaded', start);
      document.removeEventListener('change', addressChange, true);
      document.body.removeEventListener('updated_checkout', queueRefresh);
      document.body.removeEventListener('country_to_state_changed', queueRefresh);
      root.jQuery?.(document.body).off('.kiriofBuyerSelectors');
      records.forEach(destroy);
    },
    { once: true },
  );
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
  return bridge;
}
