export type CourierService = {
  code: string;
  name: string;
  aliases?: string[];
  unavailable?: boolean;
};
export type Courier = {
  code: string;
  name: string;
  type?: string;
  region?: string;
  services: CourierService[];
  unavailable?: boolean;
};
export type ServiceSelection = Record<string, string[]>;
export type CourierPayload = {
  couriers: Courier[];
  whitelist_ids: string[];
  service_selection: ServiceSelection | null;
};
export type SelectionState = { selection: ServiceSelection; remembered: ServiceSelection };

const normalized = (value: string): string => value.trim().toLowerCase();
export function matchesCourierSearch(courier: Courier, query: string): boolean {
  const term = normalized(query);
  return (
    !term ||
    [
      courier.name,
      courier.code,
      ...courier.services.flatMap((service) => [
        service.name,
        service.code,
        ...(service.aliases ?? []),
      ]),
    ].some((value) => normalized(value).includes(term))
  );
}
export function supportedCourier(courier: Pick<Courier, 'code' | 'type' | 'region'>): boolean {
  return (
    Boolean(normalized(courier.code)) &&
    !['ninja_inter', 'gosend', 'grab_express', 'borzo'].includes(normalized(courier.code)) &&
    !['instant', 'international'].includes(normalized(courier.type ?? '')) &&
    normalized(courier.region ?? '') !== 'international'
  );
}

/** Materialize legacy allowlists and retain historical choices as visible, editable fallbacks. */
export function initializeSelection(payload: CourierPayload): {
  couriers: Courier[];
  state: SelectionState;
} {
  const couriers = (payload.couriers ?? []).filter(supportedCourier).map((courier) => ({
    ...courier,
    services: (courier.services?.length
      ? courier.services
      : [{ code: '*', name: 'All services' }]
    ).map((service) => ({ ...service })),
  }));
  const legacy = payload.service_selection === null || payload.service_selection === undefined;
  const selection: ServiceSelection = {};
  const saved = legacy
    ? Object.fromEntries((payload.whitelist_ids ?? []).map((code) => [code, ['*']]))
    : payload.service_selection!;
  for (const [rawCode, values] of Object.entries(saved)) {
    if (!Array.isArray(values) || !supportedCourier({ code: rawCode })) continue;
    const original = (payload.couriers ?? []).find(
      (row) => normalized(row.code) === normalized(rawCode),
    );
    if (original && !supportedCourier(original)) continue;
    let courier = couriers.find((row) => normalized(row.code) === normalized(rawCode));
    if (!courier) {
      courier = {
        code: rawCode,
        name: rawCode,
        unavailable: true,
        services: [{ code: '*', name: 'All services', unavailable: true }],
      };
      couriers.push(courier);
    }
    const expanded = values.includes('*')
      ? [
          ...courier.services.map((service) => service.code),
          ...values.filter((code) => code !== '*'),
        ]
      : values;
    selection[courier.code] = [
      ...new Set(
        expanded.map((code) => {
          const service = courier!.services.find(
            (row) =>
              normalized(row.code) === normalized(code) ||
              row.aliases?.some((alias) => normalized(alias) === normalized(code)),
          );
          return service?.code ?? code;
        }),
      ),
    ];
    for (const code of selection[courier.code]) {
      if (!courier.services.some((service) => service.code === code))
        courier.services.push({ code, name: code, unavailable: true });
    }
  }
  // An empty legacy allowlist meant unrestricted shipping, not an explicit deny-all.
  if (legacy && !(payload.whitelist_ids ?? []).length) {
    for (const courier of couriers)
      selection[courier.code] = courier.services.map((service) => service.code);
  }
  return { couriers, state: { selection, remembered: {} } };
}

export function selectedCourierCount(selection: ServiceSelection): number {
  return Object.values(selection).filter((values) => values.length > 0).length;
}
export function hasSelection(selection: ServiceSelection): boolean {
  return selectedCourierCount(selection) > 0;
}
export function courierSelection(
  courier: Courier,
  selection: ServiceSelection,
): { checked: boolean; indeterminate: boolean; count: number } {
  const values = selection[courier.code] ?? [];
  const count = courier.services.filter((service) => values.includes(service.code)).length;
  return {
    checked: values.length > 0,
    indeterminate: count > 0 && count < courier.services.length,
    count,
  };
}
export function toggleCourier(
  state: SelectionState,
  courier: Courier,
  enabled: boolean,
): SelectionState {
  const selection = { ...state.selection };
  const remembered = { ...state.remembered };
  if (enabled)
    selection[courier.code] = [
      ...(remembered[courier.code]?.length
        ? remembered[courier.code]
        : courier.services.map((service) => service.code)),
    ];
  else {
    if (selection[courier.code]?.length) remembered[courier.code] = [...selection[courier.code]];
    delete selection[courier.code];
  }
  return { selection, remembered };
}
export function toggleService(
  state: SelectionState,
  courier: Courier,
  code: string,
  enabled: boolean,
): SelectionState {
  const previous = state.selection[courier.code] ?? [];
  const values = previous.filter((value) => value !== code);
  if (enabled) values.push(code);
  const selection = { ...state.selection };
  const remembered = { ...state.remembered };
  if (values.length) {
    selection[courier.code] = values;
    remembered[courier.code] = [...values];
  } else {
    delete selection[courier.code];
    if (previous.length) remembered[courier.code] = [...previous];
  }
  return { selection, remembered };
}
export function setAllServices(
  state: SelectionState,
  couriers: Courier[],
  enabled: boolean,
): SelectionState {
  if (!enabled) return { selection: {}, remembered: { ...state.remembered, ...state.selection } };
  return {
    selection: {
      ...state.selection,
      ...Object.fromEntries(
        couriers.map((courier) => [courier.code, courier.services.map((service) => service.code)]),
      ),
    },
    remembered: { ...state.remembered },
  };
}
export function selectionPayload(
  selection: ServiceSelection,
  couriers: Courier[],
): Record<string, string> {
  const ids = Object.keys(selection).filter((code) => selection[code].length > 0);
  return {
    service_selection: JSON.stringify(selection),
    whitelist_ids: ids.join(','),
    whitelist_names: ids
      .map((code) => couriers.find((courier) => courier.code === code)?.name ?? code)
      .join(','),
  };
}
