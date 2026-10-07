import { lookupKey, readAddress } from './address';
import type { AccountRoot, AccountConfig, AccountView, District } from './types';
/** Native select remains server-authoritative; transport failures retain posted identity. */
export function bindDistrict(
  root: AccountRoot,
  form: HTMLFormElement,
  wrapper: HTMLElement,
  state: AccountView,
  config: AccountConfig,
  publish: () => void,
  changed: () => void,
) {
  const district = wrapper.querySelector<HTMLSelectElement>('#kiriof-account-district')!;
  const districtStatus = wrapper.querySelector<HTMLElement>('#kiriof-account-district-status');
  const districtRetry = wrapper.querySelector<HTMLButtonElement>('#kiriof-account-district-retry');
  const strings = config.i18n || {};
  let options: District[] = [],
    disposed = false,
    generation = 0;
  let timer: number | undefined,
    networkTimer: number | undefined,
    controller: AbortController | null = null;
  const read = () => readAddress(form);
  function renderDistrict() {
    changed();
    let required = 'ID' === state.address.country;
    district.required = required;
    district.disabled = !required || state.address.postcode.length < 3 || state.loading;
    if (districtRetry) {
      districtRetry.hidden = !state.failed;
    }
    district.setAttribute('aria-required', String(required));
    district.setAttribute(
      'aria-invalid',
      String(required && (state.failed || (!state.loading && !state.selection))),
    );
    district.replaceChildren();
    let placeholder = root.document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = strings.selectDistrict || '';
    district.appendChild(placeholder);
    options.forEach(function (row) {
      let option = root.document.createElement('option');
      option.value = row.id;
      option.textContent = row.label;
      district.appendChild(option);
    });
    // Keep a remembered district visible while awaiting canonical confirmation.
    if (
      state.selection &&
      (state.loading || state.failed) &&
      !options.some(function (row) {
        return row.id === state.selection!.id;
      })
    ) {
      let remembered = root.document.createElement('option');
      remembered.value = state.selection!.id;
      remembered.textContent = state.selection.label;
      district.appendChild(remembered);
    }
    district.value = state.selection ? state.selection!.id : '';
    if (districtStatus) {
      districtStatus.textContent = !required
        ? ''
        : (state.address.postcode.length < 3
            ? strings.postcodeRequired
            : state.loading
              ? strings.loading
              : state.failed
                ? strings.lookupFailed
                : !options.length
                  ? strings.empty
                  : state.selection
                    ? ''
                    : strings.districtRequired) || '';
    }
  }
  function cancelLookup() {
    generation++;
    root.clearTimeout(timer);
    root.clearTimeout(networkTimer);
    if (controller) {
      controller.abort();
      controller = null;
    }
  }
  function lookup() {
    cancelLookup();
    let currentGeneration = generation;
    let key = lookupKey(state.address);
    let postcode = state.address.postcode;
    options = [];
    state.failed = false;
    state.loading = 'ID' === state.address.country && postcode.length >= 3;
    renderDistrict();
    if (!state.loading) {
      return;
    }
    controller = new root.AbortController();
    let requestController = controller;
    function current() {
      return (
        !disposed &&
        currentGeneration === generation &&
        key === lookupKey(read()) &&
        !requestController.signal.aborted
      );
    }
    function fail() {
      if (!current()) {
        return;
      }
      root.clearTimeout(networkTimer);
      state.loading = false;
      state.failed = true;
      options = [];
      // Preserve posted identity on transport errors: only the server can validate it.
      renderDistrict();
    }
    timer = root.setTimeout(function () {
      if (!current()) {
        return;
      }
      networkTimer = root.setTimeout(function () {
        if (!current()) {
          return;
        }
        // Set failure before aborting: aborted promise handlers must remain inert.
        fail();
        requestController.abort();
      }, 10000);
      let body = new root.URLSearchParams({
        action: 'kiriminaja_subdistrict_search',
        nonce: config.nonce || '',
        term: postcode,
      });
      root
        .fetch(config.ajaxUrl || '', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
          signal: requestController.signal,
        })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('District lookup failed');
          }
          return response.json();
        })
        .then(function (response) {
          if (!current()) {
            return;
          }
          if (!response.success || !Array.isArray(response.data)) {
            throw new Error('District lookup failed');
          }
          root.clearTimeout(networkTimer);
          options = response.data
            .filter(function (row: { id: unknown; text: string }) {
              return (
                row &&
                /^[1-9][0-9]*$/.test(String(row.id)) &&
                'string' === typeof row.text &&
                row.text.trim()
              );
            })
            .map(function (row: { id: unknown; text: string }) {
              return { id: String(row.id), label: row.text.trim() };
            });
          // A remembered identity is not trusted until this postcode lookup confirms it.
          state.selection = state.selection
            ? options.find(function (row) {
                return row.id === state.selection!.id;
              }) || null
            : null;
          state.loading = false;
          renderDistrict();
          publish();
        })
        .catch(fail);
    }, 250);
  }

  const retry = (event: Event) => {
    event.preventDefault();
    if (!disposed && state.failed) lookup();
  };
  districtRetry?.addEventListener('click', retry);
  return {
    lookup,
    choose() {
      if (state.loading || state.failed || district.disabled || !options.length) return;
      state.selection = options.find((row) => row.id === district.value) || null;
      publish();
      renderDistrict();
    },
    dispose() {
      disposed = true;
      cancelLookup();
      districtRetry?.removeEventListener('click', retry);
    },
  };
}
