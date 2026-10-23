# Google Maps browser-origin check

The Google Maps card in **KiriminAja → Settings → Technical** automatically checks a configured saved browser key when mounted. Saving/replacing the key starts a new check; removing it stops the preview. **Check again** creates a fresh isolated frame, including after a failed Google runtime load.

## What the check proves

The check loads Google's Maps JavaScript API directly from the merchant's browser, creates a sample Jakarta map, and waits for map tiles plus a short authentication-error grace period. Loading the SDK callback alone is not success. A later Google authentication failure revokes an earlier successful result.

A server-side HTTP request or a fabricated `Referer` header would not reproduce browser restrictions. The diagnostic frame loads from `home_url('/')`, honoring the WordPress home setting and the `WP_HOME` override in `wp-config.php`. Its actual storefront origin and a strict-origin cross-origin referrer policy supply Google's referrer. The parent admin hostname never replaces this URL. It does not spoof another site or inspect Google Cloud credentials/restriction rules.

The result applies to the configured storefront-origin preview only. Checkout may have a different path restriction, content security policy, extension behavior, or referrer policy. A successful preview is not a guarantee that every storefront URL is allowed. Authentication failures instruct the merchant to review HTTP referrers, Maps JavaScript API enablement/restrictions, billing, and quota; they do not claim which Google Cloud setting is wrong.

The preview requires the same authenticated WordPress session used to generate its nonce. Different admin/storefront hosts can share a session only when the deployment's cookie configuration permits it. Independent logins can create different session tokens, and browsers may block cross-site cookies. In those cases the protected frame fails or times out; the plugin never bypasses authorization or falls back to testing the admin host. HTTPS admin pages also cannot embed an HTTP storefront. The timeout guidance includes URL, session, cookie, and iframe restrictions. Browser fixtures verify cross-origin referrers with simulated authorization, not real WordPress cross-site cookies.

Google SDK/map requests may count toward Google Maps usage. The check uses a public sample location, never warehouse/buyer coordinates or browser geolocation. It does not create shipments, call the KiriminAja pricing API, change saved coordinates, or fall back to another map provider after a failed Google check.

## Credential and frame boundaries

- The settings form/bootstrap and save response retain only `configured` and `maskedKey`; the replacement input is cleared after submission and is hidden by default.
- The browser key is public when maps load. The complete saved key is supplied only to a protected storefront-origin diagnostic document, then to Google's SDK request, not to the parent bootstrap or frame endpoint URL.
- The `?kiriof_google_maps_browser_check=1` route runs before canonical redirects and requires GET, `manage_woocommerce`, and the dedicated Maps settings nonce before reading the key. Unrelated storefront requests are untouched. There is no public credential-reader AJAX action.
- Responses are non-cacheable and noindex. A `frame-ancestors` CSP permits only the configured admin origin; the diagnostic removes `X-Frame-Options` so a separately hosted admin can embed it. Other CSP policies remain intact. `strict-origin-when-cross-origin` prevents nonce-bearing URLs from being sent to Google as full referrers.
- The probe refuses to load Google if its actual browser origin differs from the configured storefront origin, including after a redirect. Neither the HTTP Host header nor a caller-supplied message destination controls the configured origins.
- Every frame has a random channel. Parent message validation checks source window, exact origin, channel, message shape, and allowed status/code combinations. Old key/frame messages cannot revive a removed or superseded check.
- Provider errors are represented by fixed `auth_failed`, `network_error`, `timeout`, `not_configured`, or `render_failed` codes. The plugin does not record or reflect raw Google errors or keys in logs.
- Frame work is bounded to 15 seconds; the parent has a 20-second deadline for blocked/nonresponsive frames. Frame disposal clears timers, callback hooks, listeners, and the diagnostic SDK element.

## UI and verification

The key form composes the existing shadcn-svelte `Card`, `Field`, `InputGroup`, `Button`, `Alert`, and `Spinner` components. Show/hide uses a labelled InputGroup button and never submits the form. Errors are announced, inputs have associated labels/help, and the preview has an accessible title.

Offline PHP tests exercise authorization, nonce/method/channel validation, configured origins, no-cache headers, safe JSON escaping, and masked summaries. Runtime/Chromium tests execute the production component, PHP-rendered frame, and probe script with an intercepted synthetic Google SDK to test storefront referrers across different admin/storefront hosts, subdirectory URLs, redirects, tile readiness, early/late auth failures, network failures, timeouts, retry, forged/stale messages, removal, keyboard controls, and mobile geometry. These tests make no live/billable Google requests; real deployment/key verification happens when the merchant opens Technical settings with the saved key.
