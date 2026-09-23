export function isInternalSettingsUrl(href: string | undefined): boolean {
  if (!href || typeof window === 'undefined') return false;
  const url = new URL(href, window.location.href);
  return (
    url.origin === window.location.origin &&
    url.pathname === window.location.pathname &&
    url.searchParams.get('page') === 'kiriminaja-konfigurasi'
  );
}

export function navigateSettings(href: string): void {
  if (!isInternalSettingsUrl(href)) {
    window.location.assign(href);
    return;
  }
  window.dispatchEvent(new CustomEvent('kiriof:settings-navigate', { detail: { href } }));
}
