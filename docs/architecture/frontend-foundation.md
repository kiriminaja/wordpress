# Frontend foundation

## Recommendation

Use **Svelte 5 + Vite + TypeScript**, with **Bits UI** for headless interactive primitives and **Tabler Icons Svelte** for icons, for new interactive admin islands.

The plugin is a PHP-rendered WordPress application with existing jQuery, WooCommerce scripts, and server-side authorization. A full SPA would discard useful server rendering and increase migration risk. Svelte lets us compile a small island to browser JavaScript, while the current PHP markup remains the fallback when JavaScript is unavailable.

The first island is the onboarding progress navigation. It has a small, explicit contract and does not own account, address, courier, or shipping persistence. The existing onboarding controller remains the source of truth for those operations.

## Why not React

React's Virtual DOM is not a blocker for every product, but it is unnecessary here. This plugin needs progressive enhancement inside pages that WordPress and WooCommerce already render. Svelte compiles component updates into direct DOM operations and avoids adding a general-purpose client runtime to every island.

## Candidate comparison

| Option | Fit for this plugin | Main risk | Decision |
| --- | --- | --- | --- |
| Svelte 5 stable | Strong progressive-enhancement story, compiled output, small islands, mature Vite integration | Smaller hiring pool than React or Vue | **Choose** |
| Solid 2 RC | Excellent fine-grained reactivity and no Virtual DOM | Release candidate API and ecosystem risk for a production plugin | Revisit after stable |
| Vue 3.6 RC | Familiar templates and strong ecosystem | RC status, plus Vapor is not the baseline contract for ordinary Vue output | Revisit after stable |
| React | Mature ecosystem and hiring pool | Virtual DOM runtime is not needed for these server-rendered islands | Do not choose for this migration |

## Foundation boundaries

- **PHP owns** authorization, data fetching, WordPress hooks, translations, and AJAX endpoints.
- **Svelte owns** local interaction state and DOM updates inside a marked island.
- **Vite owns** bundling, ES module output, and production asset generation.
- **CSS tokens own** KiriminAja identity inside `.kiriof-onboarding`; WordPress admin selectors stay untouched.
- **Fallback markup stays** in the PHP template so a failed or blocked asset does not remove the setup flow.

## UI library decision

Use Bits UI as the primitive foundation. It is unstyled, built for Svelte 5, and keeps KiriminAja in control of visual design while supplying accessible interaction behavior. Wrap vendor primitives in local components under `src/lib/ui/`; feature components should prefer those wrappers instead of importing Bits UI directly.

Use `@tabler/icons-svelte` as the icon set. Import icons individually so Vite can tree-shake unused icons. Icons are supportive graphics: interactive controls still need an accessible label, and decorative icons must be hidden from assistive technology.

Do not add shadcn, Chakra, or Flowbite on top of this foundation.

- shadcn is a source-code collection, not a runtime library. Bits UI already supplies the accessible headless layer we need, while local wrappers let us own a KiriminAja-specific component system without copied visual defaults.
- Chakra brings a larger runtime and its styling assumptions are not a natural fit for an embedded WordPress admin page.
- Flowbite is tightly coupled to Tailwind conventions and would add a second styling system beside WordPress and the plugin's existing CSS.

Start with a small internal token layer and Bits UI wrappers. Add primitives only when a migrated feature needs them; do not bulk-build a speculative component catalog. Styles must remain scoped under the plugin root and must not reset the WordPress admin globally.

## Design decisions

- **Purple primary:** `#5c2ecb` is already present in the onboarding visual language, so the brand reads as KiriminAja without recoloring WordPress core.
- **Scoped tokens:** variables live under `.kiriof-onboarding`, preventing accidental admin-wide theming.
- **Progressive island:** the progress navigation is the first island because it can communicate with the existing flow through browser events without moving persistence logic into JavaScript.
- **Fallback-first markup:** PHP remains visible until the compiled island mounts, so a missing asset fails soft instead of producing a blank admin page.
- **Calm responsive motion:** no decorative animation is introduced. The foundation prioritizes clear state, keyboard focus, and mobile reflow over visual effects.

## Build

```sh
bun install
bun run format
bun run frontend:check
make frontend
make test
make zip
```

The build writes production assets to `assets/admin/dist/`. Generated bundles are ignored by Git and are not committed; `make zip` runs `make frontend` first and copies the fresh output into the distributable archive. A future CI job should run `bun install --frozen-lockfile`, `make frontend`, and then the PHP validation steps.

Oxfmt and Oxlint are the frontend formatter and linter. `bun install` configures a pre-commit hook through `simple-git-hooks`; the hook runs frontend checks only when frontend-related files are staged. Generated files in `assets/admin/dist/` are produced during packaging because WordPress installs the plugin without Node or Bun.

Use `make zip` for the production/WP.org archive, `make zip dev` for the development archive, and `make zip stg` for the staging archive. Non-production builds include the corresponding `KIRIOF_ENV` marker and use the matching `API_BASE_URL_*` value from `.env` when present.
