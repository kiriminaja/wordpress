import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';

const sourceRoots = ['src/lib', 'src/styles'];
const files = [];

function collect(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const entryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) collect(entryPath);
    else if (/\.(css|svelte|ts)$/.test(entry.name)) files.push(entryPath);
  }
}

for (const root of sourceRoots) collect(root);

const forbidden = [
  { pattern: /#[0-9a-f]{3,8}\b/i, label: 'raw hex color' },
  { pattern: /color-mix\(/i, label: 'color-mix expression' },
  {
    pattern: /\[(?:background|border-color|color|opacity|outline|outline-offset):[^\]]+\]/i,
    label: 'arbitrary color, opacity, or outline utility',
  },
];
const violations = [];

for (const file of files) {
  const lines = readFileSync(file, 'utf8').split('\n');
  lines.forEach((line, index) => {
    for (const { pattern, label } of forbidden) {
      if (pattern.test(line)) violations.push(`${file}:${index + 1} ${label}`);
    }
  });
}

if (violations.length > 0) {
  throw new Error(
    `Use semantic Tailwind tokens instead of raw style values:\n${violations.join('\n')}`,
  );
}
