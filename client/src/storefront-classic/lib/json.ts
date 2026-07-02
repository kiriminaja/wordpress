export function kiriofExtractJsonResponseText(raw: unknown): string {
  const text = String(raw || "").trim();
  if (!text) {
    return text;
  }

  try {
    JSON.parse(text);
    return text;
  } catch (e) {}

  const jsonStart = text.indexOf("{");
  const jsonEnd = text.lastIndexOf("}");
  if (jsonStart >= 0 && jsonEnd > jsonStart) {
    const extracted = text.substring(jsonStart, jsonEnd + 1);
    try {
      JSON.parse(extracted);
      return extracted;
    } catch (e) {}
  }

  return text;
}
