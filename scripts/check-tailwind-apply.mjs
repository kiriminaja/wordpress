import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';

const stylesDir = 'src/styles';
const exemptFiles = new Set(['kiriof-var.css']);
const allowedProperties = new Set(['content', 'transform']);

function isInKeyframes(node) {
  let parent = node.parent;
  while (parent) {
    if (parent.type === 'atrule' && /keyframes$/i.test(parent.name)) return true;
    parent = parent.parent;
  }
  return false;
}

const violations = [];
for (const name of readdirSync(stylesDir).filter((file) => file.endsWith('.css'))) {
  if (exemptFiles.has(name)) continue;
  const file = path.join(stylesDir, name);
  const root = postcss.parse(readFileSync(file, 'utf8'), { from: file });

  root.walkDecls((decl) => {
    if (
      decl.prop.startsWith('--') ||
      decl.prop.startsWith('-webkit-') ||
      decl.prop.startsWith('-moz-') ||
      decl.prop.includes('scrollbar') ||
      allowedProperties.has(decl.prop) ||
      isInKeyframes(decl)
    ) {
      return;
    }
    violations.push(
      `${file}:${decl.source.start.line} raw declaration "${decl.prop}" must use @apply.`,
    );
  });
}

if (violations.length > 0) {
  throw new Error(`Tailwind utility-first violations:\n${violations.join('\n')}`);
}
