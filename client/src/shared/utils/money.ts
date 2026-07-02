export function moneyFormat(value: string | number, prefix?: string): string {
  const numberString = value.toString();
  const split = numberString.split(",");
  const integerPart = split[0] || "";
  const remainder = integerPart.length % 3;
  let rupiah = integerPart.substring(0, remainder);
  const thousands = integerPart.substring(remainder).match(/\d{3}/gi);

  if (thousands) {
    const separator = remainder ? "." : "";
    rupiah += separator + thousands.join(".");
  }

  rupiah = split[1] !== undefined ? rupiah + "," + split[1] : rupiah;
  return prefix === undefined ? rupiah : rupiah ? "Rp. " + rupiah : "";
}

export function exposeMoneyFormat(): void {
  window.kiriofMoneyFormat = moneyFormat;
}

export function fallbackMoneyFormat(amount: number): string {
  return Number(amount).toLocaleString("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}
