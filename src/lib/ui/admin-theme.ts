type Rgb = readonly [number, number, number];

function parseColor(value: string): Rgb | null {
  const hex = /^#([\da-f]{3}|[\da-f]{6})$/i.exec(value.trim());
  if (hex) {
    const digits =
      hex[1].length === 3 ? [...hex[1]].map((digit) => digit + digit).join('') : hex[1];
    return [0, 2, 4].map(
      (index) => parseInt(digits.slice(index, index + 2), 16) / 255,
    ) as unknown as Rgb;
  }
  const rgb =
    /^rgb\(\s*(\d+(?:\.\d+)?)\s*[, ]\s*(\d+(?:\.\d+)?)\s*[, ]\s*(\d+(?:\.\d+)?)\s*\)$/i.exec(
      value.trim(),
    );
  if (!rgb) return null;
  const channels = rgb.slice(1).map(Number);
  return channels.every((channel) => channel >= 0 && channel <= 255)
    ? (channels.map((channel) => channel / 255) as unknown as Rgb)
    : null;
}

function linear(channel: number): number {
  return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
}

/** Convert opaque WordPress sRGB colors to D65 OKLCH without changing their appearance. */
export function adminThemeTokens(
  value: string,
): { primary: string; foreground: string; contrast: number } | null {
  const rgb = parseColor(value);
  if (!rgb) return null;
  const [r, g, b] = rgb.map(linear);
  const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
  const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
  const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
  const lightness = 0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s;
  const a = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
  const axisB = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;
  const chroma = Math.hypot(a, axisB);
  const hue = chroma < 0.000001 ? 0 : ((Math.atan2(axisB, a) * 180) / Math.PI + 360) % 360;
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
  const whiteContrast = 1.05 / (luminance + 0.05);
  const blackContrast = (luminance + 0.05) / 0.05;
  return {
    primary: `oklch(${lightness.toFixed(6)} ${chroma.toFixed(6)} ${hue.toFixed(4)})`,
    foreground: whiteContrast >= blackContrast ? 'oklch(1 0 0)' : 'oklch(0 0 0)',
    contrast: Math.max(whiteContrast, blackContrast),
  };
}

/** Only plugin tokens are written; WordPress and storefront variables remain untouched. */
export function syncAdminTheme(doc: Document = document): () => void {
  const view = doc.defaultView;
  const body = doc.body;
  if (!view || !body?.classList.contains('wp-admin')) return () => {};
  const root = doc.documentElement;
  let active = true;
  let lastColor: string | undefined;
  const refresh = () => {
    if (!active) return;
    const value = view.getComputedStyle(body).getPropertyValue('--wp-admin-theme-color').trim();
    if (value === lastColor) return;
    lastColor = value;
    const tokens = adminThemeTokens(value);
    if (tokens) {
      root.style.setProperty('--kiriof-admin-primary', tokens.primary);
      root.style.setProperty('--kiriof-admin-primary-foreground', tokens.foreground);
    } else {
      root.style.removeProperty('--kiriof-admin-primary');
      root.style.removeProperty('--kiriof-admin-primary-foreground');
    }
  };
  refresh();
  const observer = new view.MutationObserver(refresh);
  observer.observe(root, { attributes: true, attributeFilter: ['class', 'style'] });
  observer.observe(body, { attributes: true, attributeFilter: ['class', 'style'] });
  if (doc.head)
    observer.observe(doc.head, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ['href', 'media', 'disabled'],
    });
  const loaded = (event: Event) => {
    if ((event.target as Element | null)?.tagName === 'LINK') refresh();
  };
  doc.addEventListener('load', loaded, true);
  const stop = () => {
    active = false;
    observer.disconnect();
    doc.removeEventListener('load', loaded, true);
    view.removeEventListener('pagehide', hidden);
  };
  const hidden = (event: PageTransitionEvent) => {
    if (!event.persisted) stop();
  };
  view.addEventListener('pagehide', hidden);
  return stop;
}
