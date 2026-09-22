import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [svelte()],
  build: {
    emptyOutDir: true,
    manifest: false,
    outDir: 'assets/admin/dist',
    rollupOptions: {
      input: {
        'coupon-panels': 'src/entries/coupon-panels.ts',
        'onboarding-progress': 'src/entries/onboarding-progress.ts',
        'order-metabox': 'src/entries/order-metabox.ts',
        'payments-list': 'src/entries/payments-list.ts',
        'pickup-detail': 'src/entries/pickup-detail.ts',
        'settings-root': 'src/entries/settings-root.ts',
        'transactions-filters': 'src/entries/transactions-filters.ts',
      },
      output: {
        assetFileNames: 'kiriminaja-[name][extname]',
        entryFileNames: 'kiriminaja-[name].js',
        format: 'es',
      },
    },
  },
});
