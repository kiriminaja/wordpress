import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../../', import.meta.url));
/** Read deployed browser entries, never evaluate the archived handwritten duplicates. */
export function script(path: string): string {
  const name = path.replace(/^assets\/buyer\/js\//, '');
  const entry = ({
    'checkout/choices-controls.js': 'classic',
    'kiriof-classic-checkout.js': 'pin',
    'kiriof-checkout-session.js': 'state',
    'kiriof-map-checkout.js': 'state',
    'kiriof-shipping-selection.js': 'state',
    'kiriof-buyer-checkout.js': 'blocks',
    'kiriof-address-presentation.js': 'blocks',
    'kiriof-block-checkout.js': 'blocks',
  } as Record<string, string>)[name];
  return readFileSync(root + (entry ? `assets/buyer/dist/kiriminaja-buyer-${entry}.js` : `assets/buyer/js/${name}`), 'utf8');
}
export function classicCss(): string {
  return readFileSync(root + 'assets/buyer/dist/kiriminaja-buyer-classic.css', 'utf8');
}
