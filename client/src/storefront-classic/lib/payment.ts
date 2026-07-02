export function kiriofNormalizePaymentMethod(paymentMethod: any): string {
  if (!paymentMethod) {
    return "";
  }
  if (typeof paymentMethod === "string") {
    return paymentMethod;
  }
  if (typeof paymentMethod === "object") {
    return (
      paymentMethod.paymentMethodSlug ||
      paymentMethod.name ||
      paymentMethod.id ||
      paymentMethod.value ||
      ""
    );
  }
  return "";
}
