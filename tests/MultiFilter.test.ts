import { describe, expect, test } from 'bun:test';
import {
  normalizeFilterSelection,
  parseFilterSelection,
  serializeFilterSelection,
  toggleFilterOption,
} from '../src/lib/ui/multi-filter';
const options = [
  { value: 'a', label: 'A' },
  { value: 'b', label: 'B' },
  { value: 'c', label: 'C' },
];
describe('transaction multi filters', () => {
  test('empty and all values represent All', () => {
    expect(parseFilterSelection('', options, 'all')).toEqual([]);
    expect(parseFilterSelection('all', options, 'all')).toEqual([]);
    expect(serializeFilterSelection([], options, 'all')).toBe('all');
  });
  test('checking every option returns the All sentinel', () => {
    expect(toggleFilterOption(['a', 'b'], 'c', true, options)).toEqual([]);
    expect(normalizeFilterSelection(['a', 'b', 'c'], options)).toEqual([]);
    expect(serializeFilterSelection(['a', 'b', 'c'], options, 'all')).toBe('all');
  });
  test('unchecking an option retains the remaining selection', () => {
    expect(toggleFilterOption([], 'a', true, options)).toEqual(['a']);
    expect(toggleFilterOption(['a', 'b'], 'a', false, options)).toEqual(['b']);
  });
  test('unknown values are dropped', () => {
    expect(parseFilterSelection('a,unknown,b', options)).toEqual(['a', 'b']);
  });
});
