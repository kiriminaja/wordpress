import { describe, expect, test } from 'bun:test';
import {
  initializeSelection,
  matchesCourierSearch,
  sortCouriersByName,
  selectionPayload,
  toggleCourier,
  toggleService,
  setAllServices,
  courierSelection,
  hasSelection,
  type Courier,
  type CourierPayload,
} from '../src/lib/couriers/selection';

const catalog: Courier[] = [
  {
    code: 'jne',
    name: 'JNE',
    type: 'regular',
    services: [
      { code: 'REG', name: 'Regular', aliases: ['regular'] },
      { code: 'YES', name: 'Express' },
    ],
  },
];
const fuzzyCatalog: Courier[] = [
  { code: 'jne', name: 'JNE Express', services: [] },
  { code: 'jntcargo', name: 'J&T Cargo', services: [] },
  { code: 'jnt', name: 'J&T Express', services: [] },
];
function load(
  service_selection: CourierPayload['service_selection'],
  whitelist_ids: string[] = [],
) {
  return initializeSelection({ couriers: catalog, whitelist_ids, service_selection });
}

describe('courier service selection', () => {
  test('couriers sort by ascending name without mutating the catalog or service order', () => {
    const original: Courier[] = [
      { code: 'tiki', name: 'Tiki', services: [] },
      { code: 'jne', name: 'JNE Express', services: [] },
      {
        code: 'anteraja',
        name: 'anterAja',
        services: [
          { code: 'ND', name: 'Next Day' },
          { code: 'REG', name: 'Regular' },
        ],
      },
      { code: 'jnt', name: 'J&T Express', services: [] },
      { code: 'jntcargo', name: 'J&T Cargo', services: [] },
    ];
    const sorted = sortCouriersByName(original);
    expect(sorted.map((courier) => courier.code)).toEqual([
      'anteraja',
      'jntcargo',
      'jnt',
      'jne',
      'tiki',
    ]);
    expect(original.map((courier) => courier.code)).toEqual([
      'tiki',
      'jne',
      'anteraja',
      'jnt',
      'jntcargo',
    ]);
    expect(sorted[0].services.map((service) => service.code)).toEqual(['ND', 'REG']);
    expect(
      sorted
        .filter((courier) => matchesCourierSearch(courier, 'jt'))
        .map((courier) => courier.name),
    ).toEqual(['J&T Cargo', 'J&T Express']);
  });
  test('equal courier names sort consistently by courier code', () => {
    expect(
      sortCouriersByName([
        { code: 'second', name: ' Same Courier ', services: [] },
        { code: 'first', name: 'same courier', services: [] },
      ]).map((courier) => courier.code),
    ).toEqual(['first', 'second']);
  });
  test('search matches names, codes and aliases without changing selection', () => {
    const courier = catalog[0];
    expect(matchesCourierSearch(courier, ' JNE ')).toBe(true);
    expect(matchesCourierSearch(courier, 'regular')).toBe(true);
    expect(matchesCourierSearch(courier, 'YeS')).toBe(true);
    expect(matchesCourierSearch(courier, 'Express')).toBe(true);
    expect(matchesCourierSearch(courier, '')).toBe(true);
    expect(matchesCourierSearch(courier, 'missing')).toBe(false);
  });
  test('search supports punctuation-free and ordered fuzzy courier matches', () => {
    expect(matchesCourierSearch(fuzzyCatalog[1], 'jt')).toBe(true);
    expect(matchesCourierSearch(fuzzyCatalog[2], 'jt')).toBe(true);
    expect(matchesCourierSearch(fuzzyCatalog[0], 'je')).toBe(true);
    expect(matchesCourierSearch(fuzzyCatalog[0], 'jn')).toBe(true);
    expect(matchesCourierSearch(fuzzyCatalog[1], 'jx')).toBe(false);
  });
  test('explicit empty selection denies all, legacy allowlists expand available services', () => {
    expect(load({}, ['jne']).state.selection).toEqual({});
    expect(load(null, ['jne']).state.selection).toEqual({ jne: ['REG', 'YES'] });
    expect(load(null).state.selection).toEqual({ jne: ['REG', 'YES'] });
    expect(hasSelection(load({ jne: [] }).state.selection)).toBe(false);
  });
  test('case insensitive aliases become canonical codes and deduplicate', () => {
    expect(load({ JNE: ['rEgUlAr', 'reg', 'yes'] }).state.selection).toEqual({
      jne: ['REG', 'YES'],
    });
  });
  test('unavailable couriers and services remain editable and survive unrelated changes', () => {
    const { couriers, state } = load({ jne: ['OLD'], historic: ['old-service'] });
    expect(couriers[0].services.at(-1)?.unavailable).toBe(true);
    expect(couriers[1].unavailable).toBe(true);
    const changed = toggleService(state, couriers[0], 'REG', true);
    expect(changed.selection).toEqual({ jne: ['OLD', 'REG'], historic: ['old-service'] });
    expect(selectionPayload(changed.selection, couriers)).toEqual({
      service_selection: '{"jne":["OLD","REG"],"historic":["old-service"]}',
      whitelist_ids: 'jne,historic',
      whitelist_names: 'JNE,historic',
    });
    expect(load(null, ['historic']).state.selection).toEqual({ historic: ['*'] });
  });
  test('subsets are indeterminate and survive courier off/on and bulk none', () => {
    const { couriers, state } = load({ jne: ['REG'] });
    const courier = couriers[0];
    expect(courierSelection(courier, state.selection)).toEqual({
      checked: true,
      indeterminate: true,
      count: 1,
    });
    const disabled = toggleCourier(state, courier, false);
    expect(disabled.selection).toEqual({});
    expect(state.selection).toEqual({ jne: ['REG'] });
    expect(toggleCourier(disabled, courier, true).selection).toEqual(state.selection);
    expect(toggleCourier(setAllServices(state, couriers, false), courier, true).selection).toEqual(
      state.selection,
    );
    expect(setAllServices(state, couriers, true).selection).toEqual({ jne: ['REG', 'YES'] });
  });
  test('last service can be disabled and restored without mutating rollback snapshots', () => {
    const { couriers, state } = load({ jne: ['REG'] });
    const disabled = toggleService(state, couriers[0], 'REG', false);
    expect(hasSelection(disabled.selection)).toBe(false);
    expect(toggleCourier(disabled, couriers[0], true).selection).toEqual(state.selection);
    expect(selectionPayload(disabled.selection, couriers).service_selection).toBe('{}');
  });
  test('wildcard expands and unsupported instant/international couriers are excluded', () => {
    expect(load({ jne: ['*', 'OLD'] }).state.selection).toEqual({ jne: ['REG', 'YES', 'OLD'] });
    const result = initializeSelection({
      couriers: [
        ...catalog,
        { code: 'other', name: 'International', type: 'international', services: [] },
      ],
      whitelist_ids: [],
      service_selection: { jne: ['REG'], gosend: ['*'], other: ['*'] },
    });
    expect(result.state.selection).toEqual({ jne: ['REG'] });
    expect(result.couriers).toHaveLength(1);
  });
});
