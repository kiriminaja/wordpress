export function printAsString<T>(value: T | null | undefined, placeholder = ""): T | string {
  if (value == null) {
    return placeholder;
  }

  return value;
}

export function exposePrintAsString(): void {
  window.kiriofPrintAsString = printAsString;
}
