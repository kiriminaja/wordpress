import { describe, expect, test } from 'bun:test';
import { readFileSync, readdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { parse } from 'svelte/compiler';
import ts from 'typescript';
import { tv as standardTv } from 'tailwind-variants';
import { tv } from 'tailwind-variants/lite';
import { cn } from '../src/lib/utils';

const root = resolve(import.meta.dir, '..');
const recipes = [
  'button/button',
  'badge/badge',
  'alert/alert',
  'empty/empty-media',
  'field/field',
  'input-group/input-group-button',
  'input-group/input-group-addon',
  'tabs/tabs-list',
  'button-group/button-group',
];

function classOverrides(): string[] {
  const values = new Set([
    '',
    'p-0 px-4',
    '!flex inline-grid!',
    'hover:bg-red-500 dark:text-white',
  ]);

  function visit(node: unknown): void {
    if (!node || typeof node !== 'object') return;
    const current = node as Record<string, unknown>;
    if (
      current.type === 'Literal' &&
      typeof current.value === 'string' &&
      /\b(?:flex|grid|text-|bg-|border|gap-|p[xytrblse]?-|m[xytrblse]?-|h-|w-|size-|rounded|items-|justify-|shadow|ring|overflow|font-)/.test(
        current.value,
      )
    ) {
      values.add(current.value);
    }
    for (const value of Object.values(current)) {
      if (Array.isArray(value)) value.forEach(visit);
      else if (value && typeof value === 'object') visit(value);
    }
  }

  function scan(directory: string): void {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const path = join(directory, entry.name);
      if (entry.isDirectory()) scan(path);
      else if (entry.name.endsWith('.svelte'))
        visit(parse(readFileSync(path, 'utf8'), { modern: true }));
    }
  }

  scan(join(root, 'src'));
  return [...values];
}

function recipeOptions(source: string): Parameters<typeof tv>[0] {
  const module = parse(source, { modern: true }).module;
  if (!module) throw new Error('Recipe module script is missing');
  const script = ts.createSourceFile(
    'recipe.ts',
    source.slice(module.content.start, module.content.end),
    ts.ScriptTarget.Latest,
    true,
  );
  for (const statement of script.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const declaration of statement.declarationList.declarations) {
      const call = declaration.initializer;
      if (!call || !ts.isCallExpression(call) || call.expression.getText(script) !== 'tv') continue;
      const options = call.arguments[0];
      if (!options || !ts.isObjectLiteralExpression(options))
        throw new Error('Expected a recipe object');
      return Function(`return (${options.getText(script)})`)();
    }
  }
  throw new Error('Recipe definition is missing');
}

const classTokens = (value: string) => value.trim().split(/\s+/).sort().join(' ');

describe('Shared class merging', () => {
  test('preserves conditional inputs and the empty-string contract', () => {
    expect(cn()).toBe('');
    expect(cn('', false, null, undefined, 0)).toBe('');
    expect(cn('p-2', ['px-4', ['p-0']], { 'p-6': true, hidden: false })).toBe('p-6');
  });

  test('resolves important classes and scoped overrides without dropping unrelated classes', () => {
    expect(cn('!p-2 p-4 hover:bg-red-500 dark:text-white', '!p-0 hover:bg-blue-500')).toBe(
      'p-4 dark:text-white !p-0 hover:bg-blue-500',
    );
    expect(cn('[&>svg]:size-4 text-sm', '[&>svg]:size-8 text-lg')).toBe('[&>svg]:size-8 text-lg');
  });
});

const overrides = classOverrides();

describe('Lite recipe compatibility', () => {
  for (const recipe of recipes) {
    test(`${recipe} preserves merged variant and override classes`, () => {
      const source = readFileSync(join(root, 'src/lib/components/ui', `${recipe}.svelte`), 'utf8');
      expect(source).toContain('from "tailwind-variants/lite"');
      expect(source).not.toContain('from "tailwind-variants"');
      const options = recipeOptions(source);
      const previous = standardTv(options);
      const current = tv(options);
      let combinations: Record<string, unknown>[] = [{}];
      for (const [key, variants] of Object.entries(options.variants ?? {})) {
        combinations = combinations.flatMap((props) =>
          [undefined, null, ...Object.keys(variants)].map((value) => ({ ...props, [key]: value })),
        );
      }
      for (const props of combinations) {
        for (const override of overrides) {
          expect(classTokens(cn(current(props), override))).toBe(
            classTokens(cn(previous(props), override)),
          );
        }
      }
    });
  }
});
