import { describe, expect, test } from 'bun:test';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dir, '..');
const read = (file: string) => readFileSync(resolve(root, file), 'utf8');

describe('buyer asset ownership and classic-script builds', () => {
  test('keeps buyer artwork and styles canonical outside generated output', () => {
    expect(existsSync(resolve(root, 'assets/buyer/css/kiriof-buyer-checkout.css'))).toBe(true);
    expect(existsSync(resolve(root, 'assets/buyer/img/couriers/jne.png'))).toBe(true);
    for (const file of [
      'inc/Base/Enqueue.php',
      'inc/Controllers/AccountShippingDestinationController.php',
      'inc/Services/CourierLogoAssets.php',
      'templates/front/form-shipping-address.php',
    ]) {
      expect(read(file)).not.toContain('assets/wp/');
    }
  });

  test('builds isolated IIFEs and excludes tooling from release packages', () => {
    const config = read('vite.buyer.config.ts');
    expect(config).toContain("formats: ['iife']");
    expect(config).toContain('emptyOutDir: false');
    expect(config).toContain('inlineDynamicImports: true');
    expect(config).toContain("outDir: 'assets/buyer/dist'");
    expect(read('Makefile')).toContain('--exclude=vite.buyer.config.ts');
    expect(read('Makefile')).toContain('--exclude=assets/wp/');
    expect(read('.gitignore')).toContain('assets/buyer/dist');
    const scripts = JSON.parse(read('package.json')).scripts;
    expect(scripts.build).toContain('build:buyer');
  });

  test('generated bridges never require native ES module script tags', () => {
    const dist = resolve(root, 'assets/buyer/dist');
    for (const entry of ['state', 'blocks', 'classic', 'pin', 'tracking', 'account-shipping']) {
      expect(existsSync(resolve(dist, `kiriminaja-buyer-${entry}.js`))).toBe(true);
    }
    for (const file of readdirSync(dist).filter((file) => file.endsWith('.js'))) {
      const source = readFileSync(resolve(dist, file), 'utf8');
      expect(source).not.toMatch(/^\s*(?:import|export)\s/m);
      expect(source).not.toContain('import.meta');
    }
  });

  test('ships no duplicate state, map, Blocks, Choices or tracking implementations', () => {
    expect(existsSync(resolve(root, 'assets/wp/js'))).toBe(false);
    for (const file of [
      'kiriof-checkout-session.js', 'kiriof-shipping-selection.js',
      'kiriof-map-checkout.js', 'kiriof-address-presentation.js',
      'kiriof-buyer-checkout.js', 'kiriof-block-checkout.js',
      'kiriof-classic-checkout-core.js', 'kiriof-classic-checkout.js',
      'checkout/choices-controls.js', 'kj-tracking.js',
      'kiriof-account-shipping.js',
    ]) {
      expect(existsSync(resolve(root, 'assets/buyer/js', file))).toBe(false);
    }
    const enqueue = read('inc/Base/Enqueue.php');
    expect(enqueue).toContain("'kiriof-buyer-checkout' => array( false, array( 'kiriof-buyer-blocks' ) )");
    expect(enqueue).not.toContain('assets/lib/choices/');
  });
});
