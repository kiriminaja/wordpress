const regionClass = 'kiriof-funnelkit-region-field';
const widthProperty = '--kiriof-region-width';
const heightProperty = '--kiriof-control-height';

/** Reclaim only the adjacent address thirds occupied by our enhanced state field. */
export function syncFunnelKitLayout(form: HTMLFormElement) {
  const desired = new Map<HTMLElement, string>();
  for (const scope of ['billing', 'shipping']) {
    const state = form.querySelector<HTMLElement>(`#${scope}_state_field`);
    if (!state?.classList.contains('kiriof-funnelkit-select-field')) continue;
    const parent = state.parentElement;
    if (!parent) continue;
    const rows = ['postcode', 'country', 'state'].map((field) =>
      form.querySelector<HTMLElement>(`#${scope}_${field}_field`),
    );
    if (rows.some((row) => row && row.parentElement !== parent)) continue;
    const siblings = rows.filter(
      (row): row is HTMLElement =>
        !!row && row.parentElement === parent && row.classList.contains('wfacp-col-left-third'),
    );
    if (siblings.length < 2 || !siblings.includes(state)) continue;
    const ordered = Array.from(parent.children).filter((child) =>
      siblings.includes(child as HTMLElement),
    );
    const first = ordered[0];
    const last = ordered[ordered.length - 1];
    const children = Array.from(parent.children);
    if (
      children
        .slice(children.indexOf(first), children.indexOf(last) + 1)
        .some((child) => !siblings.includes(child as HTMLElement))
    )
      continue;
    const visible = siblings.filter((row) => {
      const style = form.ownerDocument.defaultView!.getComputedStyle(row);
      return !row.hidden && style.display !== 'none' && style.visibility !== 'hidden';
    });
    if (!visible.length) continue;
    for (const row of siblings) desired.set(row, `${100 / visible.length}%`);
    const postcode = form.querySelector<HTMLInputElement>(`#${scope}_postcode`);
    const height = postcode?.getBoundingClientRect().height || 0;
    if (height >= 44) {
      for (const row of parent.querySelectorAll<HTMLElement>('.kiriof-funnelkit-select-field')) {
        const value = `${height}px`;
        if (row.style.getPropertyValue(heightProperty) !== value)
          row.style.setProperty(heightProperty, value);
      }
    }
  }
  for (const row of form.querySelectorAll<HTMLElement>(`.${regionClass}`)) {
    if (desired.has(row)) continue;
    row.classList.remove(regionClass);
    row.style.removeProperty(widthProperty);
  }
  for (const [row, width] of desired) {
    if (!row.classList.contains(regionClass)) row.classList.add(regionClass);
    if (row.style.getPropertyValue(widthProperty) !== width)
      row.style.setProperty(widthProperty, width);
  }
}

export function clearFunnelKitLayout(form: HTMLFormElement) {
  for (const row of form.querySelectorAll<HTMLElement>(
    `.${regionClass}, [style*="${heightProperty}"]`,
  )) {
    row.classList.remove(regionClass);
    row.style.removeProperty(widthProperty);
    row.style.removeProperty(heightProperty);
  }
}
