import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { compile } from '@tailwindcss/node';
import { cn } from 'cnfast';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');
const componentClasses = (path: string) => {
  const source = read(`src/lib/components/ui/${path}.svelte`);
  // The installed primitives keep their base class lists in cn("...").
  return [...source.matchAll(/"([^"\n]* [^"\n]*)"/g)].map((match) => match[1]).join(' ');
};

// Deliberately later than the compiled theme. These are the relevant native
// WordPress forms/common rules, including the mobile control minimum height.
const wordpress = `
.wp-core-ui .button{display:inline-block;box-sizing:border-box;min-height:30px;margin:0;padding:0 10px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;line-height:2.15384615;text-decoration:none;cursor:pointer;box-shadow:none}
.wp-core-ui .button:hover{background:#f0f0f1;border-color:#0a4b78;color:#0a4b78}
.wp-core-ui .button:focus{border-color:#3582c4;box-shadow:0 0 0 1px #3582c4;outline:2px solid transparent;outline-offset:0}
body{font:13px/1.4 sans-serif;color:#3c434a;background:#f0f0f1}
h2{font-size:23px;font-weight:400;margin:1em 0}p{margin:1em 0}
label{cursor:pointer;vertical-align:middle}fieldset{padding:0;border:0}
input[type=text],input[type=email],textarea,select{box-shadow:0 0 0 transparent;border-radius:4px;border:1px solid #8c8f94;background:#fff;color:#2c3338;min-height:30px;line-height:2;padding:0 8px}
input[type=text]:focus,textarea:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:2px solid transparent}
input:disabled{background:#f0f0f1;color:#a7aaad}input[readonly]{background:#f0f0f1}
@media screen and (max-width:782px){input[type=text],input[type=email],textarea,select{min-height:40px;font-size:16px}}
#wpadminbar{position:fixed;top:0;left:0;right:0;height:32px;z-index:99999;background:#1d2327}
.leaflet-pane{position:absolute;z-index:700}.leaflet-popup{padding:13px;position:absolute}
`;

test('compiled admin theme preserves primitive, settings, and native WordPress behavior', async ({
  app,
  browser,
}) => {
  // Extract only the actual base and outline variant: collecting every quoted
  // string here would mix destructive/ghost/size utilities into one Button.
  const buttonSource = read('src/lib/components/ui/button/button.svelte');
  const buttonBase = buttonSource.match(/base: "([^"\n]+)"/)?.[1];
  const buttonOutline = buttonSource.match(/outline: "([^"\n]+)"/)?.[1];
  expect(Boolean(buttonBase && buttonOutline)).toBe(true);
  // Mirror the primitive's cn() merge so outline replaces base border-transparent.
  const sharedButton = cn('kiriof-button', buttonBase, buttonOutline, 'h-8');
  const input = componentClasses('input/input');
  const label = componentClasses('label/label') + ' ' + componentClasses('field/field-label');
  const card = componentClasses('card/card');
  const header = componentClasses('card/card-header');
  const title = componentClasses('card/card-title');
  const description = componentClasses('card/card-description');
  const alert = componentClasses('alert-dialog/alert-dialog-content');
  const overlay = componentClasses('alert-dialog/alert-dialog-overlay');
  const select = componentClasses('select/select-content');
  const popover = componentClasses('popover/popover-content');
  const candidates = [
    input,
    sharedButton,
    'border border-destructive ring-1',
    label,
    card,
    header,
    title,
    description,
    alert,
    overlay,
    select,
    popover,
    'flex flex-col gap-4 h-8 w-full fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 p-4 rounded-lg bg-background',
  ]
    .join(' ')
    .split(/\s+/);
  const compileFile = async (path: string) => {
    const compiled = await compile(read(path), {
      base: resolve(root, 'src/styles'),
      onDependency() {},
    });
    return compiled.build(candidates);
  };
  // Compile the production theme and plain settings stylesheet without a
  // test-only preflight that could hide a missing semantic border baseline.
  const css = (
    await Promise.all(
      [
        'src/styles/kiriof-var.css',
        'src/styles/kiriof-component.css',
        'src/styles/settings-root.css',
      ].map(compileFile),
    )
  ).join('\n');
  const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><style>${wordpress}${read('assets/admin/css/kj-admin-style.css')}</style></head>
      <body class="wp-admin wp-core-ui kiriof-admin"><div id="wpadminbar"></div>
      <input id="native" type="text" value="Native WordPress"><button id="native-button" class="button">Native button</button><a id="native-anchor" class="button" href="#native">Native link</a>
      <h2 id="native-heading">Native heading</h2><aside id="toolbar" class="kiriof-shadcn"><button id="toolbar-button" data-slot="button" class="${sharedButton}">Toolbar</button><a id="toolbar-anchor" data-slot="button" class="${sharedButton}" href="#tracking">Tracking</a></aside>
      <main class="kj-wrap"><section class="kiriof-shadcn">
      <div id="card" data-slot="card" data-size="default" class="${card} ring-1"><div id="header" data-slot="card-header" class="${header}"><div data-slot="card-title" class="${title}">Warehouse</div><p data-slot="card-description" class="${description}">Warehouse description</p></div></div>
      <label id="label" data-slot="field-label" class="${label}" for="input">Warehouse name</label>
      <input id="input" data-slot="input" type="text" class="${input}">
      <input id="readonly" aria-label="Read only warehouse" data-slot="input" type="text" readonly value="Default warehouse" class="${input}">
      <input id="disabled" aria-label="Disabled warehouse" data-slot="input" type="text" disabled class="${input}">
      <input id="invalid" aria-label="Invalid warehouse" data-slot="input" type="text" aria-invalid="true" class="${input}">
      <div id="bare-border" class="border">Semantic default</div><div id="destructive-border" class="border border-destructive">Explicit border</div>
      <button id="page-button" data-slot="button" class="${sharedButton}">Shared button</button><a id="page-anchor" data-slot="button" class="${sharedButton}" href="#tracking">Tracking</a>
      <button id="button" data-slot="button" class="${cn(sharedButton, 'w-full')}">Save</button>
      <div class="leaflet-container"><div class="leaflet-pane"><div id="leaflet" class="leaflet-popup p-4">Map popup</div></div></div>
      </section>
      <section class="kiriof-shadcn kiriof-settings-root" data-kiriof-settings-root>
        <div class="kiriof-settings-group"><h2 id="settings-heading" class="kiriof-settings-group__title">Settings</h2>
          <div id="settings-items" class="kiriof-settings-group__items">
            <a id="settings-first" class="kiriof-setting-item" href="#account">Account</a>
            <a id="settings-last" class="kiriof-setting-item" href="#shipping">Shipping</a>
          </div>
        </div>
        <article id="settings-card" class="kiriof-section-card"><h2 id="settings-card-heading">Diagnostics</h2>
          <dl id="settings-diagnostics" class="kiriof-diagnostics"><div id="settings-diagnostic-row"><dt>Connection</dt><dd>Active</dd></div></dl>
        </article>
        <div id="settings-profile" class="kiriof-linked-profile"><div>Linked account</div></div>
        <div class="kiriof-connect"><h2 id="settings-connect-heading">Connect account</h2></div>
      </section></main>
      <div data-slot="alert-dialog-overlay" data-state="open" class="${overlay}"></div>
      <div id="alert" data-slot="alert-dialog-content" data-size="default" data-state="open" class="${alert}"><label class="${label}" for="portal-input">Portal label</label><input id="portal-input" type="text" data-slot="input" class="${input}"><button id="portal-button" data-slot="button" class="${sharedButton}">Portal button</button><a id="portal-anchor" data-slot="button" class="${sharedButton}" href="#tracking">Tracking</a></div>
      <div id="select" data-slot="select-content" class="${select}">Option</div>
      <div id="popover" data-slot="popover-content" class="${popover}">Popover</div>
      </body></html>`;
  const unexpected: string[] = [];
  await browser.route('**/*', async (route) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test' && url.pathname === '/admin-theme') {
      await route.fulfill({ contentType: 'text/html', body: html });
      return;
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    await route.fulfill({ status: 404, body: 'Unexpected fixture request' });
  });
  await app.open('/admin-theme');
  const cascadeFailures: {
    width: number;
    id: string;
    property: string;
    actual: string;
    expected: string;
  }[] = [];
  for (const width of [1440, 390]) {
    await browser.setViewport({ width, height: 900 });
    const state = await browser.evaluate(async () => {
      (document.activeElement as HTMLElement)?.blur();
      await new Promise((resolve) => setTimeout(resolve, 250));
      const style = (id: string) => getComputedStyle(document.getElementById(id)!);
      const height = (id: string) => document.getElementById(id)!.getBoundingClientRect().height;
      const probe = document.createElement('div');
      document.getElementById('bare-border')!.append(probe);
      const color = (value: string) => {
        probe.style.setProperty('border-color', value, 'important');
        return getComputedStyle(probe).borderColor;
      };
      const semanticBorder = color('var(--border)');
      const destructiveBorder = color('var(--destructive)');
      const softCardRing = color('color-mix(in oklab, var(--foreground) 10%, transparent)');
      const foreground = color('var(--foreground)');
      const ringColor = (id: string) => color(style(id).getPropertyValue('--tw-ring-color'));
      const buttons = [
        'page-button',
        'toolbar-button',
        'portal-button',
        'page-anchor',
        'toolbar-anchor',
        'portal-anchor',
      ].map((id) => ({
        id,
        radius: style(id).borderRadius,
        border: style(id).borderColor,
        shadow: style(id).boxShadow,
      }));
      const cardRing = ringColor('card');
      probe.remove();
      return {
        buttons,
        semanticBorder,
        destructiveBorder,
        softCardRing,
        foreground,
        cardRing,
        cardShadow: style('card').boxShadow,
        settingsBorders: [
          ['settings-items', 'borderTopWidth', 'borderTopStyle', 'borderTopColor'],
          ['settings-first', 'borderBottomWidth', 'borderBottomStyle', 'borderBottomColor'],
          ['settings-card', 'borderTopWidth', 'borderTopStyle', 'borderTopColor'],
          ['settings-diagnostics', 'borderTopWidth', 'borderTopStyle', 'borderTopColor'],
          [
            'settings-diagnostic-row',
            'borderBottomWidth',
            'borderBottomStyle',
            'borderBottomColor',
          ],
          ['settings-profile', 'borderTopWidth', 'borderTopStyle', 'borderTopColor'],
        ].map(([id, width, lineStyle, borderColor]) => ({
          id,
          width: style(id).getPropertyValue(
            width.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`),
          ),
          lineStyle: style(id).getPropertyValue(
            lineStyle.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`),
          ),
          color: style(id).getPropertyValue(
            borderColor.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`),
          ),
        })),
        settingsLastBorder: style('settings-last').borderBottomWidth,
        settingsHeadingMargin: style('settings-heading').marginBottom,
        settingsCardHeadingMargin: style('settings-card-heading').marginBottom,
        settingsConnectHeadingMargin: style('settings-connect-heading').marginBottom,
        settingsDiagnosticsMargin: style('settings-diagnostics').marginTop,
        bareBorder: style('bare-border').borderColor,
        bareWidth: style('bare-border').borderTopWidth,
        explicitBorder: style('destructive-border').borderColor,
        nativeRadius: style('native-button').borderRadius,
        nativeHeadingMargin: style('native-heading').marginBottom,
        nativeHeadingFont: style('native-heading').fontSize,
        nativeBorder: style('native-button').borderColor,
        nativeShadow: style('native-button').boxShadow,
        nativeAnchorRadius: style('native-anchor').borderRadius,
        height: height('input'),
        portalHeight: height('portal-input'),
        readonlyHeight: height('readonly'),
        disabledHeight: height('disabled'),
        disabledOpacity: style('disabled').opacity,
        disabledPointer: style('disabled').pointerEvents,
        invalidBorder: style('invalid').borderColor,
        invalidRing: style('invalid').boxShadow,
        disabledBackground: style('disabled').backgroundColor,
        labelDisplay: style('label').display,
        labelFont: style('label').fontWeight,
        nativeHeight: height('native'),
        nativeBackground: style('native').backgroundColor,
        buttonDisplay: style('button').display,
        buttonWidth: document.getElementById('button')!.getBoundingClientRect().width,
        parentWidth: document.querySelector('main section')!.getBoundingClientRect().width,
        cardDirection: style('card').flexDirection,
        headerDisplay: style('header').display,
        headerTitleBottom: document
          .querySelector('[data-slot="card-title"]')!
          .getBoundingClientRect().bottom,
        headerDescriptionTop: document
          .querySelector('[data-slot="card-description"]')!
          .getBoundingClientRect().top,
        leafletPadding: style('leaflet').padding,
        alertZ: Number(style('alert').zIndex),
        overlayZ: Number(
          getComputedStyle(document.querySelector('[data-slot=alert-dialog-overlay]')!).zIndex,
        ),
        selectZ: Number(style('select').zIndex),
        popoverZ: Number(style('popover').zIndex),
        portalBackground: style('alert').backgroundColor,
        tokens: style('alert').getPropertyValue('--popover'),
      };
    });
    for (const button of state.buttons) {
      expect(parseFloat(button.radius)).toBeGreaterThan(0);
      expect(button.radius).toBe(state.buttons[0].radius);
      expect(button.border).toBe(state.semanticBorder);
      expect(button.border).not.toBe(state.foreground);
      expect(button.border).not.toBe(state.nativeBorder);
      if (button.shadow !== 'none') {
        cascadeFailures.push({
          width,
          id: button.id,
          property: 'box-shadow (unfocused)',
          actual: button.shadow,
          expected: 'none',
        });
      }
    }
    expect(state.nativeRadius).toBe('3px');
    expect(state.nativeHeadingMargin).toBe('23px');
    expect(state.nativeHeadingFont).toBe('23px');
    expect(state.nativeAnchorRadius).toBe('3px');
    expect(state.nativeBorder).toBe('rgb(34, 113, 177)');
    expect(state.nativeShadow).toBe('none');
    for (const border of state.settingsBorders) {
      for (const [property, actual, expected] of [
        ['border-width', border.width, '1px'],
        ['border-style', border.lineStyle, 'solid'],
        ['border-color', border.color, state.semanticBorder],
      ]) {
        if (actual !== expected)
          cascadeFailures.push({ width, id: border.id, property, actual, expected });
      }
    }
    expect(state.settingsLastBorder).toBe('0px');
    for (const [id, property, actual, expected] of [
      ['settings-heading', 'margin-bottom', state.settingsHeadingMargin, '8px'],
      ['settings-card-heading', 'margin-bottom', state.settingsCardHeadingMargin, '8px'],
      ['settings-connect-heading', 'margin-bottom', state.settingsConnectHeadingMargin, '24px'],
      ['settings-diagnostics', 'margin-top', state.settingsDiagnosticsMargin, '18px'],
    ]) {
      if (actual !== expected) cascadeFailures.push({ width, id, property, actual, expected });
    }
    expect(state.bareWidth).toBe('1px');
    expect(state.bareBorder).toBe(state.semanticBorder);
    expect(state.explicitBorder).toBe(state.destructiveBorder);
    expect(state.explicitBorder).not.toBe(state.semanticBorder);
    expect(state.cardRing).toBe(state.softCardRing);
    expect(state.cardRing).not.toBe(state.foreground);
    expect(state.cardShadow).toContain(state.softCardRing);
    expect(state.height).toBe(32);
    expect(state.portalHeight).toBe(32);
    expect(state.readonlyHeight).toBe(32);
    expect(state.disabledHeight).toBe(32);
    expect(state.disabledOpacity).toBe('0.5');
    expect(state.disabledPointer).toBe('none');
    expect(state.invalidBorder).toBe(state.destructiveBorder);
    expect(state.invalidRing).toContain('3px');
    expect(state.disabledBackground).not.toBe('rgba(0, 0, 0, 0)');
    expect(state.labelDisplay).toBe('flex');
    expect(state.labelFont).toBe('500');
    expect(state.nativeHeight).toBeGreaterThanOrEqual(width === 390 ? 40 : 30);
    expect(state.nativeBackground).toBe('rgb(255, 255, 255)');
    expect(state.buttonDisplay).toBe('inline-flex');
    expect(state.buttonWidth).toBe(state.parentWidth);
    expect(state.cardDirection).toBe('column');
    expect(state.leafletPadding).toBe('13px');
    expect(state.headerDisplay).toBe('grid');
    expect(state.headerDescriptionTop).toBeGreaterThanOrEqual(state.headerTitleBottom);
    expect(state.overlayZ).toBeGreaterThan(99999);
    expect(state.alertZ).toBeGreaterThan(state.overlayZ);
    expect(state.selectZ).toBeGreaterThan(state.alertZ);
    expect(state.popoverZ).toBeGreaterThan(state.alertZ);
    expect(state.portalBackground).not.toBe('rgba(0, 0, 0, 0)');
    expect(state.tokens.trim()).not.toBe('');
    for (const id of [
      'page-button',
      'toolbar-button',
      'portal-button',
      'page-anchor',
      'toolbar-anchor',
      'portal-anchor',
    ]) {
      const buttonFocus = await browser.evaluate(async (buttonId: string) => {
        const element = document.getElementById(buttonId)!;
        element.focus();
        // Actual Button transition-all must settle before reading its ring.
        await new Promise((resolve) => setTimeout(resolve, 250));
        const style = getComputedStyle(element);
        const probe = document.createElement('div');
        element.append(probe);
        probe.style.setProperty(
          'border-color',
          'color-mix(in oklab, var(--ring) 50%, transparent)',
          'important',
        );
        const expectedRing = getComputedStyle(probe).borderColor;
        probe.style.setProperty('border-color', 'var(--ring)', 'important');
        const expectedBorder = getComputedStyle(probe).borderColor;
        const result = {
          visible: element.matches(':focus-visible'),
          shadow: style.boxShadow,
          border: style.borderColor,
          expectedRing,
          expectedBorder,
        };
        probe.remove();
        element.blur();
        return result;
      }, id);
      expect(buttonFocus.visible).toBe(true);
      expect(buttonFocus.border).toBe(buttonFocus.expectedBorder);
      expect(buttonFocus.shadow).toContain(`${buttonFocus.expectedRing} 0px 0px 0px 3px`);
      expect(buttonFocus.shadow).not.toContain('rgb(34, 113, 177)');
    }
    const focused = await browser.evaluate(async () => {
      const element = document.querySelector<HTMLInputElement>('#input')!;
      element.focus();
      await new Promise((resolve) => setTimeout(resolve, 250));
      return {
        border: getComputedStyle(element).borderColor,
        ring: getComputedStyle(element).boxShadow,
      };
    });
    expect(focused.border).not.toBe('rgb(34, 113, 177)');
    expect(focused.ring).not.toBe('none');
    expect(
      await browser.evaluate(
        () =>
          document.querySelector('#input')!.id ===
          document.querySelector('#label')!.getAttribute('for'),
      ),
    ).toBe(true);
  }
  expect(unexpected).toEqual([]);
  // Collect these independently so one cascade regression does not hide the
  // other managed roots or the mobile/focus-visible contracts.
  expect(cascadeFailures).toEqual([]);
});
