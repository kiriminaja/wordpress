type FormatNumberOptions = {
  currency?: boolean;
};

export function formatNumber(
  value: string | number,
  options: FormatNumberOptions = {},
): string {
  const amount = Number(value) || 0;
  const prefix = options.currency ? "Rp" : "";
  const sign = amount < 0 ? "-" : "";
  const formatted = Math.abs(amount).toLocaleString("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });

  return `${sign}${prefix}${formatted}`;
}

export function moneyFormat(value: string | number, prefix?: string): string {
  const rupiah = formatNumber(value);
  return prefix === undefined ? rupiah : rupiah ? "Rp. " + rupiah : "";
}

export function exposeMoneyFormat(): void {
  window.kiriofMoneyFormat = moneyFormat;
}

export function fallbackMoneyFormat(amount: number): string {
  return formatNumber(amount);
}
