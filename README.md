# KiriminAja WooCommerce Plugin

Official WooCommerce integration for KiriminAja shipping services.

## 📦 Quick Start

```bash
# Install dependencies
./setup-frontend.sh

# Build production assets
make build

# Start development (optional)
make dev
```

## 🚀 Features

- ✅ **Vue 3 + TypeScript** - Modern frontend with full type safety
- ✅ **Opt-in Integration** - Vue apps activate automatically when built
- ✅ **Hot Module Replacement** - Instant updates during development
- ✅ **Backwards Compatible** - Legacy scripts continue working
- ✅ **Zero Configuration** - Just build and go

## 📂 Project Structure

```
/ (root)
├── vite.config.ts          # Build configuration
├── tsconfig.json           # TypeScript settings
├── package.json            # Dependencies & scripts
├── Makefile                # Build commands
│
├── frontend/src/
│   ├── admin/              # Admin dashboard (TypeScript)
│   ├── wp/                 # Frontend public (TypeScript)
│   ├── components/         # Shared Vue components
│   └── composables/        # Reusable logic
│
├── inc/                    # PHP classes
├── assets/                 # Build output & static files
└── templates/              # PHP templates
```

## 🔧 Development

### Install Dependencies

```bash
./setup-frontend.sh
# or
bun install
```

### Development Mode (HMR)

```bash
# Enable WP_DEBUG in wp-config.php
define('WP_DEBUG', true);

# Start dev server
make dev
# or
bun run dev
```

Visit WordPress admin - Vue apps load with instant updates!

### Build for Production

```bash
make build
# or
bun run build
```

Assets are compiled to `assets/` directory with cache-busting hashes.

### Create Distribution Package

```bash
make zip
```

Creates a plugin zip file ready for distribution.

## 🎯 How It Works

### Opt-in Architecture

The plugin automatically detects if Vue assets are available:

**Without build** (manifest.json doesn't exist):

- Uses legacy JavaScript files only
- Everything works as before

**With build** (after `make build`):

- Detects `dist/.vite/manifest.json`
- Loads Vue apps automatically
- Legacy scripts continue working
- Both systems coexist

### TypeScript Integration

All source code uses TypeScript for type safety:

```typescript
// Type-safe WordPress AJAX
interface ShipmentData {
  tracking_number: string;
  status: "pending" | "shipped" | "delivered";
}

const { post } = useAppFetch();
const result = await post<ShipmentData>("get_shipment", { id });

if (result.success && result.data) {
  console.log(result.data.tracking_number); // Fully typed!
}
```

## 📚 Available Commands

| Command              | Description                           |
| -------------------- | ------------------------------------- |
| `make install`       | Install frontend dependencies         |
| `make dev`           | Start development server with HMR     |
| `make build`         | Build production assets               |
| `make clean`         | Remove build artifacts                |
| `make zip`           | Build and create distribution package |
| `bun run type-check` | Run TypeScript type checking          |

## 🐛 Troubleshooting

**Vue app not loading?**

- Run `make build` to create manifest.json
- Check browser console for errors
- Verify `dist/.vite/manifest.json` exists

**TypeScript errors?**

```bash
bun run type-check
```

**HMR not working?**

- Set `WP_DEBUG` to `true` in wp-config.php
- Verify dev server: `curl localhost:3000`
- Check no firewall blocking port 3000

**Build errors?**

```bash
make clean && make build
```

## 📖 Documentation

- `frontend/README.md` - Detailed development guide
- API Reference: https://developer.kiriminaja.com/docs

## 🎓 Development Workflow

### Creating a New Component

```vue
<!-- frontend/src/components/MyComponent.vue -->
<script setup lang="ts">
interface Props {
  title: string;
  count?: number;
}

const props = withDefaults(defineProps<Props>(), {
  count: 0,
});
</script>

<template>
  <div class="my-component">
    <h3>{{ title }}</h3>
    <p>Count: {{ count }}</p>
  </div>
</template>

<style scoped>
.my-component {
  padding: 1rem;
}
</style>
```

### Using Composables

```typescript
import { useAppFetch } from "@/composables/useAppFetch";

const { loading, error, post } = useAppFetch();

async function saveData() {
  const result = await post("my_action", { data: "value" });
  if (result.success) {
    console.log("Saved!");
  }
}
```

## 💡 Key Features

### Type Safety

- Full TypeScript support with strict checking
- Type-safe WordPress AJAX integration
- IntelliSense for all Vue components
- Compile-time error detection

### Modern Tooling

- **Vite** - Lightning-fast build tool
- **Bun** - Fast package manager & runtime
- **Vue 3** - Composition API with `<script setup>`
- **TypeScript** - Type safety throughout

### Developer Experience

- Hot Module Replacement for instant feedback
- Path aliases (`@components`, `@admin`, `@wp`)
- Automatic code splitting
- Source maps for debugging
- Optimized production builds

## 🔄 Migration Path

The plugin supports gradual migration:

1. **Keep legacy code** - Everything continues working
2. **Build Vue assets** - Run `make build`
3. **Both systems coexist** - Legacy + Vue work together
4. **Migrate incrementally** - Move features to Vue over time
5. **Remove legacy** - When ready, clean up old code

No forced migration, no breaking changes!

## 🌟 Benefits

✅ **Opt-in** - Enable when ready, zero breaking changes  
✅ **Type-safe** - Catch errors before runtime  
✅ **Fast** - HMR updates in milliseconds  
✅ **Modern** - Vue 3 + TypeScript best practices  
✅ **Compatible** - Works alongside existing code  
✅ **Production-ready** - Optimized builds with Vite

## 📄 License

GPL-2.0-or-later

## 🔗 Links

- [KiriminAja Website](https://kiriminaja.com)
- [API Documentation](https://developer.kiriminaja.com/docs)
- [Plugin URI](https://developer.kiriminaja.com)

---

Built with ❤️ by KiriminAja
