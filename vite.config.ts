import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwind from "@tailwindcss/vite";
import ui from "@nuxt/ui/vite";
import { resolve } from "path";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwind(),
    ui({
      colorMode: false,
      ui: {
        dashboardNavbar: {
          slots: {
            root: "h-(--ui-header-height) shrink-0 flex sticky z-100 top-0 bg-white left-0 right-0 items-center justify-between border-b border-default px-4 sm:px-6 gap-1.5",
            left: "flex items-center gap-1.5 min-w-0",
            icon: "shrink-0 size-5 self-center me-1.5",
            title:
              "flex items-center gap-1.5 !text-base !m-0 !font-semibold text-highlighted p-0 truncate",
            center: "hidden lg:flex",
            right: "flex items-center shrink-0 gap-1.5",
            toggle: "",
          },
          variants: {
            toggleSide: {
              left: {
                toggle: "",
              },
              right: {
                toggle: "",
              },
            },
          },
        },
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
      },
    }),
  ],

  build: {
    // Generate manifest for WordPress to load assets correctly
    manifest: true,

    // Output directory - builds directly to WordPress plugin assets
    outDir: resolve(__dirname, "assets"),

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

    // Watch for changes
    watch: {
      usePolling: true,
    },
  },

  resolve: {
    alias: {
      "@": resolve(__dirname, "frontend/src"),
      "@admin": resolve(__dirname, "frontend/src/admin"),
      "@wp": resolve(__dirname, "frontend/src/wp"),
      "@components": resolve(__dirname, "frontend/src/components"),
    },
  },
});
