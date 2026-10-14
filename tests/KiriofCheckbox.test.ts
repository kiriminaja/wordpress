import { describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';

const component = readFileSync(new URL('../src/lib/ui/KiriofCheckbox.svelte', import.meta.url), 'utf8');

describe('KiriofCheckbox component contract', () => {
  test('real Bits UI renders the attributes targeted by checked and mixed theme colors', async () => {
    const root = resolve(import.meta.dir, '..');
    const directory = mkdtempSync(join(root, 'node_modules', '.checkbox-state-test-'));
    try {
      writeFileSync(join(directory, 'entry.ts'), `import { render } from 'svelte/server'; import Checkbox from '${join(root, 'src/lib/ui/KiriofCheckbox.svelte')}'; export function html(props) { return render(Checkbox, { props }).body; }`);
      const built = await Bun.build({
        entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'bun',
        plugins: [{ name: 'checkbox-server-runtime', setup(builder) {
          builder.onResolve({ filter: /^\$lib\/utils(?:\.js)?$/ }, () => ({ path: join(root, 'src/lib/utils.ts') }));
          builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => {
            const input = readFileSync(path, 'utf8');
            const source = path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(input) : input;
            return { contents: compileModule(source, { filename: path, generate: 'server' }).js.code, loader: 'js' };
          });
          builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'server' }).js.code, loader: 'js' }));
        } }],
      });
      if (!built.success) throw new Error(built.logs.join('\n'));
      const runtime = await import(join(directory, 'runtime.js'));
      for (const [props, state, aria] of [
        [{ checked: false }, 'unchecked', 'false'],
        [{ checked: true }, 'checked', 'true'],
        [{ checked: false, indeterminate: true }, 'indeterminate', 'mixed'],
        [{ checked: true, indeterminate: true }, 'indeterminate', 'mixed'],
      ] as const) {
        const html = runtime.html({ ...props, 'aria-label': 'Select order' });
        expect(html).toContain(`data-state="${state}"`);
        expect(html).toContain(`aria-checked="${aria}"`);
        expect(html).toContain('data-[state=checked]:!bg-primary');
        expect(html).toContain('data-[state=indeterminate]:!bg-primary');
        expect(html).not.toContain('data-checked:');
      }
    } finally {
      rmSync(directory, { recursive: true, force: true });
    }
  });

  test('compiles for client and server without accessibility warnings', () => {
    for (const generate of ['client', 'server'] as const) {
      const result = compile(component, { filename: 'KiriofCheckbox.svelte', generate });
      expect(result.warnings).toEqual([]);
      expect(result.js.code).toContain('CheckboxPrimitive.Root');
    }
  });

  test('all three visual states share the same fixed footprint and prefer mixed state', () => {
    expect(component).toContain('!size-4 !min-h-4 !min-w-4');
    expect(component).toContain('data-[state=checked]:!bg-primary');
    expect(component).toContain('data-[state=indeterminate]:!bg-primary');
    expect(component).toContain('!text-primary-foreground');
    for (const [checked, indeterminate, checkVisible, minusVisible] of [
      [false, false, false, false],
      [true, false, true, false],
      [false, true, false, true],
      [true, true, false, true],
    ]) {
      expect(Boolean(checked && !indeterminate)).toBe(checkVisible);
      expect(Boolean(indeterminate)).toBe(minusVisible);
    }
    expect(component).not.toContain('transition-all');
    expect(component).toContain('motion-reduce:transition-none');
  });
});
