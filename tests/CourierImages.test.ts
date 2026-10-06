import { describe, expect, test } from 'bun:test';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { courierAssetUrl, courierFiles, courierImage } from '../src/lib/transactions/courier-images';

const root = resolve(import.meta.dir, '..');

describe('shared packaged courier artwork', () => {
  test('resolves plugin-local URLs from entry modules and shared chunks', () => {
    const plugin = 'https://shop.example/wp-content/plugins/kiriminaja/';
    for (const module of ['kiriminaja-admin-workspace.js', 'assets/shared-AbCd.js']) {
      expect(courierAssetUrl('ninja-inter.png', `${plugin}assets/admin/dist/${module}`)).toBe(
        `${plugin}assets/wp/img/couriers/ninja-inter.png`,
      );
    }
  });

  test('keeps all existing courier codes and display aliases', () => {
    for (const [code, file] of Object.entries(courierFiles)) {
      expect(courierImage(code)?.endsWith(`/couriers/${file}`)).toBe(true);
      expect(existsSync(resolve(root, 'assets/wp/img/couriers', file))).toBe(true);
    }
    expect(new Set(Object.values(courierFiles)).size).toBe(21);
    for (const [alias, code] of [
      ['J&T Cargo', 'jnt_cargo'],
      ['Ninja International', 'ninja_inter'],
      ['ID Express', 'idx'],
      ['Shopee Express', 'spx'],
      ['POS Indonesia', 'pos'],
      ['Grab Express', 'grab_express'],
    ]) {
      expect(courierImage(alias)).toBe(courierImage(code));
      expect(courierImage('unknown', alias)).toBe(courierImage(code));
    }
    expect(courierImage('unknown')).toBeUndefined();
    expect(courierImage('../../jne.png')).toBeUndefined();
  });

  test('Classic PHP and Svelte maps reference the same filenames', () => {
    const php = readFileSync(resolve(root, 'inc/Services/CourierLogoAssets.php'), 'utf8');
    const files = [...php.matchAll(/'([a-z_]+)'\s*=>\s*'([a-z-]+\.png)'/g)];
    expect(files.length).toBe(21);
    for (const [, code, file] of files) expect(courierFiles[code]).toBe(file);
  });

  test('does not import artwork into the Vite pipeline or retain source copies', () => {
    const source = readFileSync(resolve(root, 'src/lib/transactions/courier-images.ts'), 'utf8');
    expect(source).not.toMatch(/import\s+.*\.png/);
    expect(existsSync(resolve(root, 'src/assets/images/kiriminaja-kurir'))).toBe(false);
  });

  test('production output does not contain duplicated courier PNGs', () => {
    const dist = resolve(root, 'assets/admin/dist');
    if (!existsSync(dist)) return;
    const originals = new Set(
      Object.values(courierFiles).map(file =>
        readFileSync(resolve(root, 'assets/wp/img/couriers', file)).toString('base64'),
      ),
    );
    for (const entry of readdirSync(dist, { recursive: true, withFileTypes: true })) {
      if (!entry.isFile() || !entry.name.endsWith('.png')) continue;
      const bytes = readFileSync(resolve(entry.parentPath, entry.name));
      expect(originals.has(bytes.toString('base64'))).toBe(false);
    }
  });
});
