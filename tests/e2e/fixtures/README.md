# Offline Woo country integration

`country-select.js` is the unmodified WooCommerce 9.9.5 legacy frontend script:
https://raw.githubusercontent.com/woocommerce/woocommerce/9.9.5/plugins/woocommerce/client/legacy/js/frontend/country-select.js

GPL-3.0-or-later, copyright WooCommerce contributors. Test-only; never enqueued or distributed as a production dependency. The HTML injects synthetic ID/US states, GB text state and AQ hidden state. SelectWoo is represented by its compatible Select2 API. All browser requests are intercepted.

Buyer browser fixtures read generated IIFE entry points through `buyer-source.ts`; run `node scripts/build-buyer.mjs` from the repository root first. No Choices runtime is loaded. Select2 remains a test dependency only for Woo country-select integration. The real Svelte selector keeps the native field authoritative and renders Bits UI popup content in a body portal.
