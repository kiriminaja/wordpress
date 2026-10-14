export type PickupDate = {
  value: string;
  label: string;
  times: Array<{ value: string; label: string }>;
};

export const PICKUP_HOURS = [8, 11, 14, 17] as const;

export function createPickupDates(now = new Date()): PickupDate[] {
  const earliest = new Date(now.getTime() + 60 * 60 * 1000);
  const latest = new Date(now);
  latest.setDate(latest.getDate() + 7);
  const dates: PickupDate[] = [];

  for (let offset = 0; offset <= 7; offset += 1) {
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
    const times = PICKUP_HOURS.filter((hour) => {
      const slot = new Date(date.getFullYear(), date.getMonth(), date.getDate(), hour);
      return slot >= earliest && slot <= latest;
    }).map((hour) => {
      const value = `${String(hour).padStart(2, '0')}:00`;
      return { value, label: value };
    });
    if (!times.length) continue;
    dates.push({
      value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`,
      label: new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      }).format(date),
      times,
    });
  }
  return dates;
}

export function pickupFlag(value: unknown): boolean {
  return (
    value === true ||
    value === 1 ||
    ['true', 'yes', '1'].includes(
      String(value ?? '')
        .trim()
        .toLowerCase(),
    )
  );
}

export function requiresPickupPayment(
  summary: { count_non_cod?: number | string; sum_fee_non_cod?: number | string },
  isTop: unknown,
): boolean {
  return (
    !pickupFlag(isTop) &&
    (Number(summary.count_non_cod ?? 0) > 0 || Number(summary.sum_fee_non_cod ?? 0) > 0)
  );
}
