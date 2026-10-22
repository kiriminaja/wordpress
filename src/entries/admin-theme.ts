import { syncAdminTheme } from '../lib/ui/admin-theme';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => syncAdminTheme(), { once: true });
} else {
  syncAdminTheme();
}
