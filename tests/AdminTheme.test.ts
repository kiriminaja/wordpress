import { describe, expect, test } from 'bun:test';
import { Window } from 'happy-dom';
import { readFileSync } from 'node:fs';
import { adminThemeTokens, syncAdminTheme } from '../src/lib/ui/admin-theme';

const channels = (value: string) => value.slice(6, -1).split(' ').map(Number);

describe('WordPress admin theme conversion', () => {
  test('converts sRGB to known OKLCH values rather than using HSL lightness', () => {
    const red = channels(adminThemeTokens('#ff0000')!.primary);
    expect(red[0]).toBeCloseTo(0.627955, 5);
    expect(red[1]).toBeCloseTo(0.257683, 5);
    expect(red[2]).toBeCloseTo(29.233885, 3);
    expect(adminThemeTokens('#000')!.primary).toBe('oklch(0.000000 0.000000 0.0000)');
    const white = channels(adminThemeTokens('#ffffff')!.primary);
    expect(white[0]).toBeCloseTo(1, 5);
    expect(white[1]).toBeCloseTo(0, 5);
    expect(white[2]).toBe(0);
    expect(adminThemeTokens('#ad631e')).toEqual(adminThemeTokens('rgb(173,99,30)'));
    expect(adminThemeTokens('#abc')).toEqual(adminThemeTokens('#aabbcc'));
  });

  test('foreground is chosen from measured sRGB contrast for custom and native schemes', () => {
    for (const color of ['#ad631e', '#2271b1', '#0085ba', '#e14d43', '#d64e07', '#04a4cc', '#e5a631', '#3858e9', '#000', '#fff', '#777', '#00ff00']) {
      const tokens = adminThemeTokens(color)!;
      expect(tokens.contrast).toBeGreaterThanOrEqual(4.5);
      expect(['oklch(1 0 0)', 'oklch(0 0 0)']).toContain(tokens.foreground);
    }
    expect(adminThemeTokens('#000')!.foreground).toBe('oklch(1 0 0)');
    expect(adminThemeTokens('#fff')!.foreground).toBe('oklch(0 0 0)');
  });

  test('rejects transparent, malformed, out-of-range and executable input', () => {
    for (const color of ['', 'transparent', '#abcd', '#11223300', '#12345', 'rgb(256,0,0)', 'rgb(-1,0,0)', 'rgba(1,2,3,0)', 'var(--foo)', 'url(x)', '#123; color:red']) {
      expect(adminThemeTokens(color)).toBeNull();
    }
  });

  test('updates inherited plugin tokens on scheme changes, removes invalid values and cleans up', async () => {
    const window = new Window();
    const doc = window.document;
    doc.body.className = 'wp-admin kiriof-admin';
    let color = '#ad631e';
    const original = window.getComputedStyle.bind(window);
    window.getComputedStyle = ((node: Element) => {
      const computed = original(node);
      return new Proxy(computed, { get(target, key) {
        if (key === 'getPropertyValue') return (name: string) => name === '--wp-admin-theme-color' ? color : target.getPropertyValue(name);
        const value = Reflect.get(target, key);
        return typeof value === 'function' ? value.bind(target) : value;
      } });
    }) as typeof window.getComputedStyle;
    const stop = syncAdminTheme(doc as unknown as Document);
    const root = doc.documentElement;
    expect(root.style.getPropertyValue('--kiriof-admin-primary')).toBe(adminThemeTokens(color)!.primary);
    expect(root.style.getPropertyValue('--kiriof-admin-primary-foreground')).toBe(adminThemeTokens(color)!.foreground);
    color = '#2271b1';
    doc.body.classList.add('admin-color-fresh');
    await new Promise((resolve) => setTimeout(resolve, 5));
    expect(root.style.getPropertyValue('--kiriof-admin-primary')).toBe(adminThemeTokens(color)!.primary);
    color = '#fff';
    const link = doc.createElement('link');
    doc.head.append(link);
    link.dispatchEvent(new window.Event('load'));
    expect(root.style.getPropertyValue('--kiriof-admin-primary-foreground')).toBe('oklch(0 0 0)');
    color = '';
    doc.body.setAttribute('style', '--other-color: blue');
    await new Promise((resolve) => setTimeout(resolve, 5));
    expect(root.style.getPropertyValue('--kiriof-admin-primary')).toBe('');
    expect(root.style.getPropertyValue('--kiriof-admin-primary-foreground')).toBe('');
    stop();
    color = '#ad631e';
    doc.body.classList.add('changed-after-stop');
    await new Promise((resolve) => setTimeout(resolve, 5));
    expect(root.style.getPropertyValue('--kiriof-admin-primary')).toBe('');
    await window.happyDOM.close();
  });

  test('storefront is untouched and component scopes reference inherited tokens', async () => {
    const window = new Window();
    syncAdminTheme(window.document as unknown as Document)();
    expect(window.document.documentElement.style.length).toBe(0);
    const css = readFileSync(new URL('../src/styles/kiriof-var.css', import.meta.url), 'utf8');
    expect(css).toContain('--primary: var(--kiriof-admin-primary, oklch(0.48 0.23 292))');
    expect(css).toContain('--primary-foreground: var(--kiriof-admin-primary-foreground, oklch(0.99 0 0))');
    expect(css).toContain('--ring: var(--primary)');
    await window.happyDOM.close();
  });
});
