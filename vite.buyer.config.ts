import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import path from 'node:path';
import { existsSync } from 'node:fs';

/** Each buyer bridge is a standalone classic script; WordPress owns dependencies. */
export default defineConfig(({ mode }) => {
  if (!/^[a-z][a-z0-9-]*$/.test(mode)) {
    throw new Error(`Invalid buyer entry name: ${mode}`);
  }
  const entry = path.resolve(`src/buyer/entries/${mode}.ts`);
  if (!existsSync(entry)) {
    throw new Error(`Buyer entry does not exist: ${entry}`);
  }
  return {
    plugins: [tailwindcss(), svelte()],
    resolve: {
      alias: {
        $lib: path.resolve('./src/lib'),
        $buyer: path.resolve('./src/buyer'),
      },
    },
    build: {
      // The orchestrator cleans once; independent entries must retain earlier output.
      emptyOutDir: false,
      manifest: false,
      outDir: 'assets/buyer/dist',
      lib: {
        entry,
        name: `KiriminajaBuyer${mode.replace(/(^|-)([a-z])/g, (_match, _prefix, letter: string) => letter.toUpperCase())}`,
        formats: ['iife'],
        fileName: () => `kiriminaja-buyer-${mode}.js`,
        cssFileName: `kiriminaja-buyer-${mode}`,
      },
      rollupOptions: {
        output: {
          inlineDynamicImports: true,
        },
      },
    },
  };
});
