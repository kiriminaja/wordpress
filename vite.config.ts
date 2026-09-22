import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [svelte()],
  build: {
    emptyOutDir: false,
    manifest: false,
    outDir: 'assets/admin/dist',
    rollupOptions: {
      input: 'src/entries/onboarding-progress.ts',
      output: {
        assetFileNames: (assetInfo) =>
          assetInfo.name?.endsWith('.css')
            ? 'kiriminaja-onboarding-progress.css'
            : 'kiriminaja-[name][extname]',
        entryFileNames: 'kiriminaja-[name].js',
        format: 'es',
      },
    },
  },
});
