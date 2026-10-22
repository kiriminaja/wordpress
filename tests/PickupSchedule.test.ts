import { describe, expect, test } from 'bun:test';
import {
  createPickupDates,
  pickupFlag,
  requiresPickupPayment,
} from '../src/lib/transactions/pickup-schedule';

describe('pickup schedule', () => {
  test('offers exactly 08, 11, 14 and 17 for a full day', () => {
    const dates = createPickupDates(new Date(2026, 8, 30, 6));
    expect(dates[0].value).toBe('2026-09-30');
    expect(dates[0].times.map((slot) => slot.value)).toEqual(['08:00', '11:00', '14:00', '17:00']);
    expect(dates[0].times.every((slot) => slot.label === slot.value)).toBe(true);
  });
  test('removes slots with less than one hour lead time', () => {
    expect(createPickupDates(new Date(2026, 8, 30, 10))[0].times.map((slot) => slot.value)).toEqual(
      ['11:00', '14:00', '17:00'],
    );
    expect(
      createPickupDates(new Date(2026, 8, 30, 10, 1))[0].times.map((slot) => slot.value),
    ).toEqual(['14:00', '17:00']);
  });
  test('late-afternoon pickup starts tomorrow at 08', () => {
    const dates = createPickupDates(new Date(2026, 8, 30, 16, 1));
    expect(dates[0].value).toBe('2026-10-01');
    expect(dates[0].times[0].value).toBe('08:00');
  });
  test('does not offer slots beyond seven days and handles month rollover', () => {
    const now = new Date(2026, 11, 31, 10);
    const latest = new Date(2027, 0, 7, 10);
    const dates = createPickupDates(now);
    expect(dates.at(-1)?.value).toBe('2027-01-07');
    expect(dates.at(-1)?.times.map((slot) => slot.value)).toEqual(['08:00']);
    for (const day of dates) {
      for (const time of day.times) {
        expect(new Date(`${day.value}T${time.value}:00`).getTime()).toBeLessThanOrEqual(
          latest.getTime(),
        );
      }
    }
  });
});

describe('pickup payment eligibility', () => {
  test('non-TOP merchants see payment methods for non-COD packages including free shipping', () => {
    expect(requiresPickupPayment({ count_non_cod: 1, sum_fee_non_cod: 0 }, false)).toBe(true);
    expect(requiresPickupPayment({ sum_fee_non_cod: '378100' }, false)).toBe(true);
    expect(requiresPickupPayment({ count_non_cod: '1' }, 'no')).toBe(true);
  });
  test('TOP merchants and COD-only batches require no payment selection', () => {
    for (const isTop of [true, 1, '1', 'yes', 'true']) {
      expect(requiresPickupPayment({ count_non_cod: 2, sum_fee_non_cod: 378100 }, isTop)).toBe(
        false,
      );
    }
    expect(requiresPickupPayment({ count_non_cod: 0, sum_fee_non_cod: 0 }, false)).toBe(false);
  });
  test('normalizes backend feature flags without treating false strings as true', () => {
    for (const enabled of [true, 1, '1', 'yes', 'true', ' YES '])
      expect(pickupFlag(enabled)).toBe(true);
    for (const disabled of [false, 0, '0', 'no', 'false', undefined, null])
      expect(pickupFlag(disabled)).toBe(false);
  });
});
