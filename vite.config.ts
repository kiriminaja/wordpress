import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwind from "@tailwindcss/vite";
import ui from "@nuxt/ui/vite";
import { resolve } from "path";
import { fileURLToPath } from "url";

const __dirname = fileURLToPath(new URL(".", import.meta.url));

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwind(),
    ui({
      colorMode: false,
      ui: {
        breadcrumb: {
          slots: {
            root: "relative min-w-0",
            list: "flex items-center gap-1.5 !m-0",
            item: "flex min-w-0 !m-0",
            link: "group relative flex items-center gap-1.5 text-sm min-w-0 focus-visible:outline-primary",
            linkLeadingIcon: "shrink-0 size-5",
            linkLeadingAvatar: "shrink-0",
            linkLeadingAvatarSize: "2xs",
            linkLabel: "truncate",
            separator: "flex !m-0",
            separatorIcon: "shrink-0 size-5 text-muted",
          },
          variants: {
            active: {
              true: {
                link: "text-primary font-semibold",
              },
              false: {
                link: "text-muted font-medium",
              },
            },
            disabled: {
              true: {
                link: "cursor-not-allowed opacity-75",
              },
            },
            to: {
              true: "",
            },
          },
          compoundVariants: [
            {
              disabled: false,
              active: false,
              to: true,
              class: {
                link: ["hover:text-default", "transition-colors"],
              },
            },
          ],
        },
        card: {
          slots: {
            root: "rounded-lg overflow-hidden",
            header: "p-4 sm:px-6",
            body: "p-4 sm:p-6",
            footer: "p-4 sm:px-6",
          },
          variants: {
            variant: {
              solid: {
                root: "bg-inverted text-inverted",
              },
              outline: {
                root: "bg-default ring ring-default divide-y divide-default",
              },
              soft: {
                root: "bg-elevated/50 divide-y divide-default",
              },
              subtle: {
                root: "bg-elevated/50 ring ring-default divide-y divide-default",
              },
            },
          },
          defaultVariants: {
            variant: "outline",
          },
        },
      },
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
