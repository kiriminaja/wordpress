export type FilterOption = { value: string; label: string; count?: number };

/** Empty selection is the All sentinel; the last individual selection also becomes All. */
export function normalizeFilterSelection(
  values: readonly string[],
  options: readonly FilterOption[],
): string[] {
  const selected = options
    .filter((option) => values.includes(option.value))
    .map((option) => option.value);
  return selected.length === options.length ? [] : [...new Set(selected)];
}

export function parseFilterSelection(
  value: string,
  options: readonly FilterOption[],
  allValue = '',
): string[] {
  if (!value || value === allValue || value === 'all') return [];
  return normalizeFilterSelection(
    value.split(',').map((part) => part.trim()),
    options,
  );
}

export function toggleFilterOption(
  values: readonly string[],
  optionValue: string,
  checked: boolean,
  options: readonly FilterOption[],
): string[] {
  // All is a distinct reset choice. Starting an individual selection from All
  // picks that option, rather than silently excluding every other option.
  const next = checked ? [...values, optionValue] : values.filter((value) => value !== optionValue);
  return normalizeFilterSelection(next, options);
}

export function serializeFilterSelection(
  values: readonly string[],
  options: readonly FilterOption[],
  allValue = '',
): string {
  return normalizeFilterSelection(values, options).join(',') || allValue;
}
