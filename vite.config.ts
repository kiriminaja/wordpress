import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwind from "@tailwindcss/vite";
import Components from "unplugin-vue-components/vite";
import RekaResolver from "reka-ui/resolver";
import { resolve } from "path";
import { fileURLToPath } from "url";

const __dirname = fileURLToPath(new URL(".", import.meta.url));

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwind(),
    Components({
      dts: true,
      resolvers: [RekaResolver()],
    }),
  ],

  build: {
    // Generate manifest for WordPress to load assets correctly
    manifest: true,

    // Output directory - builds directly to WordPress plugin assets
    outDir: resolve(__dirname, "dist"),

    // Empty the output directory before building
    emptyOutDir: false,

    rollupOptions: {
      // Multiple entry points for admin and frontend
      input: {
        admin: resolve(__dirname, "frontend/src/admin/main.ts"),
      },

      output: {
        // Organize output files
        entryFileNames: (chunkInfo) => {
          // Place admin files in admin/js, wp files in wp/js
          if (chunkInfo.name === "admin") {
            return "admin/js/[name].[hash].js";
          }
          return "wp/js/[name].[hash].js";
        },
        chunkFileNames: (chunkInfo) => {
          // Determine directory based on the entry point
          const isAdmin = chunkInfo.facadeModuleId?.includes("/admin/");
          return isAdmin
            ? "admin/js/[name].[hash].js"
            : "wp/js/[name].[hash].js";
        },
        assetFileNames: (assetInfo) => {
          // Place CSS files in appropriate directories
          if (assetInfo.name && assetInfo.name.endsWith(".css")) {
            if (assetInfo.name.includes("admin")) {
              return "admin/css/[name].[hash][extname]";
            }
            return "wp/css/[name].[hash][extname]";
          }
          // Other assets go to public
          return "public/[name].[hash][extname]";
        },
      },
    },
  },

  server: {
    // Vite dev server configuration
    port: 3000,
    strictPort: true,

    // Enable CORS for WordPress development
    cors: true,

    // HMR configuration
    hmr: {
      host: "localhost",
      protocol: "ws",
    },

    // Watch for changes with polling enabled
    watch: {
      usePolling: true,
      ignored: ["**/inc/**", "**/templates/**"],
    },
  },

  resolve: {
    alias: {
      "@": resolve(__dirname, "frontend/src"),
      "@admin": resolve(__dirname, "frontend/src/admin"),
      "@wp": resolve(__dirname, "frontend/src/wp"),
      "@shared": resolve(__dirname, "frontend/src/shared"),
      "@components": resolve(__dirname, "frontend/src/components"),
    },
  },
});
