import { createAdminOriginMaps, type AdminMapsWindow } from '../admin/origin-maps';

const win = window as unknown as AdminMapsWindow;
if (!win.kiriofAdminMaps) {
  win.kiriofAdminMaps = createAdminOriginMaps(win);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => win.kiriofAdminMaps?.initialize(), {
      once: true,
    });
  } else {
    win.kiriofAdminMaps.initialize();
  }
}
