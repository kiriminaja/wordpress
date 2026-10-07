export interface ShippingPackage {
  package_id: string;
  rate_id: string;
}
export interface ShippingSelectionSnapshot {
  version: 1;
  packages: ShippingPackage[];
}
export interface ShippingSelectionOptions {
  onChange?(snapshot: ShippingSelectionSnapshot): void;
}
export interface ShippingSelection {
  seed(packages: unknown): boolean;
  review(packages: unknown): boolean;
  reconcile(packages: unknown): boolean;
  choose(packageId: unknown, rateId: unknown, packages?: unknown): boolean;
  snapshot(): ShippingSelectionSnapshot;
  matches(packages: unknown): boolean;
}
// Keep Woo's opaque identifiers exact. Never trim or sanitize into another ID.
export function identifier(value: unknown, packageId: boolean): string | null {
  if (packageId && 'number' === typeof value) {
    return Number.isSafeInteger(value) && value >= 0 ? String(value) : null;
  }
  if ('string' !== typeof value || !value.length || value.length > 256) {
    return null;
  }
  for (var index = 0; index < value.length; index++) {
    var code = value.charCodeAt(index);
    if (code <= 31 || (code >= 127 && code <= 159)) {
      return null;
    }
  }
  return value;
}

export function normalize(packages: unknown): ShippingPackage[] | null {
  var result: ShippingPackage[] = [];
  var seen: Record<string, boolean> = Object.create(null);
  if (!Array.isArray(packages) || !packages.length) {
    return null;
  }
  for (var index = 0; index < packages.length; index++) {
    var entry = packages[index] as Partial<ShippingPackage> | null;
    var packageId = entry ? identifier(entry.package_id, true) : null;
    var rateId = entry ? identifier(entry.rate_id, false) : null;
    if (null === packageId || null === rateId || !entry || seen[packageId]) {
      return null;
    }
    seen[packageId] = true;
    result.push({ package_id: packageId, rate_id: rateId });
  }
  return result;
}

function copy(packages: ShippingPackage[]): ShippingPackage[] {
  return packages.map(function (entry: ShippingPackage) {
    return { package_id: entry.package_id, rate_id: entry.rate_id };
  });
}

function equal(reviewed: ShippingPackage[], packages: ShippingPackage[] | null): boolean {
  if (!reviewed.length || !packages || reviewed.length !== packages.length) {
    return false;
  }
  return reviewed.every(function (entry: ShippingPackage) {
    return packages.some(function (candidate) {
      return entry.package_id === candidate.package_id && entry.rate_id === candidate.rate_id;
    });
  });
}

/**
 * Pure buyer intent, not a quote cache. Adapters supply complete selected
 * package/rate pairs, never prices or labels. Seed only after a stable initial
 * render. Reconcile on native updates; review/choose only on user interaction.
 * Missing quotes and checkout errors must not clear the previous review.
 */
export function create(options?: ShippingSelectionOptions): ShippingSelection {
  var reviewed: ShippingPackage[] = [];
  var current: ShippingPackage[] | null = null;
  var seeded = false;
  var onChange =
    options && 'function' === typeof options.onChange ? options.onChange : function () {};

  function snapshot(): ShippingSelectionSnapshot {
    return { version: 1, packages: copy(reviewed) };
  }

  function accept(packages: ShippingPackage[]) {
    var changed = !equal(reviewed, packages);
    reviewed = copy(packages);
    current = copy(packages);
    seeded = true;
    if (changed) {
      onChange(snapshot());
    }
    return true;
  }

  function seed(packages: unknown) {
    var normalized = normalize(packages);
    if (seeded || !normalized) {
      return false;
    }
    return accept(normalized);
  }

  function review(packages: unknown) {
    var normalized = normalize(packages);
    return normalized ? accept(normalized) : false;
  }

  function reconcile(packages: unknown) {
    current = normalize(packages);
    return equal(reviewed, current);
  }

  /**
   * Pass the complete next selected set from the native user event, or first
   * reconcile it. The exact chosen pair must be present; no synthetic rate
   * is trusted. An explicit choice reviews the whole visible set, including
   * intentional package additions/removals, without a second confirmation.
   */
  function choose(packageId: unknown, rateId: unknown, packages?: unknown) {
    var id = identifier(packageId, true);
    var rate = identifier(rateId, false);
    var next = undefined === packages ? current : normalize(packages);
    if (
      null === id ||
      null === rate ||
      !next ||
      !next.some(function (entry: ShippingPackage) {
        return entry.package_id === id && entry.rate_id === rate;
      })
    ) {
      return false;
    }
    return accept(next);
  }

  return {
    seed: seed,
    choose: choose,
    review: review,
    reconcile: reconcile,
    snapshot: snapshot,
    matches: function (packages: unknown) {
      return equal(reviewed, normalize(packages));
    },
  };
}
