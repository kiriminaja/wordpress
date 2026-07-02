import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vite";

const rootDir = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  server: {
    host: "localhost",
    port: 5173,
    strictPort: true,
  },
  build: {
    emptyOutDir: false,
    outDir: ".",
    sourcemap: false,
    minify: true,
    cssCodeSplit: false,
    rolldownOptions: {
      input: {
        "assets/wp/js/kj-wp-script": resolve(rootDir, "client/src/storefront-classic/entries/wp-script.ts"),
        "assets/wp/js/kj-tracking": resolve(rootDir, "client/src/storefront-classic/entries/tracking.ts"),
        "assets/wp/js/form-billing-address": resolve(rootDir, "client/src/storefront-classic/entries/form-billing-address.ts"),
        "assets/wp/js/kiriof-block-checkout": resolve(rootDir, "client/src/storefront-block/entries/block-checkout.ts"),
        "assets/admin/js/kj-admin-script": resolve(rootDir, "client/src/admin/entries/admin-script.ts"),
        "assets/admin/js/kj-coupon-admin": resolve(rootDir, "client/src/admin/entries/coupon-admin.ts"),
        "assets/js/kiriof-cod-adjustment": resolve(rootDir, "client/src/admin/entries/cod-adjustment.ts"),
        "assets/js/templates/after-checkout": resolve(rootDir, "client/src/admin/entries/after-checkout.ts"),
      },
      output: {
        entryFileNames: "[name].js",
        chunkFileNames: "assets/js/chunks/[name]-[hash].js",
        assetFileNames: "assets/js/chunks/[name]-[hash][extname]",
      },
    },
  },
});
