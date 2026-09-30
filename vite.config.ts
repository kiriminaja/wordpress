import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  plugins: [tailwindcss(), svelte()],
  resolve: {
    alias: {
      $lib: path.resolve('./src/lib'),
    },
  },
  build: {
    emptyOutDir: true,
    manifest: false,
    outDir: 'assets/admin/dist',
    rollupOptions: {
      input: {
        'coupon-panels': 'src/entries/coupon-panels.ts',
        'onboarding-progress': 'src/entries/onboarding-progress.ts',
        'order-metabox': 'src/entries/order-metabox.ts',
        'admin-workspace': 'src/entries/admin-workspace.ts',
        'kiriof-var': 'src/entries/kiriof-var.css',
        'kiriof-component': 'src/entries/kiriof-component.css',
      },
      output: {
        assetFileNames: 'kiriminaja-[name][extname]',
        entryFileNames: 'kiriminaja-[name].js',
        format: 'es',
      },
    },
  },
});
