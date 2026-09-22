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
        'onboarding-progress': 'src/entries/onboarding-progress.ts',
        'payments-list': 'src/entries/payments-list.ts',
        'settings-root': 'src/entries/settings-root.ts',
      },
      output: {
        assetFileNames: 'kiriminaja-[name][extname]',
        entryFileNames: 'kiriminaja-[name].js',
        format: 'es',
      },
    },
  },
});
