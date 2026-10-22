import { createRequire } from 'node:module';

// Required locked dependencies must fail, never skip or borrow another project.
export const runtimeRequire = createRequire(import.meta.url);
export const React = runtimeRequire('react');
const dom = runtimeRequire('happy-dom');
// Resolve beside React so hooks and the renderer share the same instance.
export const rendererRequire = createRequire(runtimeRequire.resolve('react/package.json'));
rendererRequire.resolve('react-dom/client');
export const loadRenderer = () => rendererRequire('react-dom/client');

// Svelte checks this Safari global. A window-scoped shim is not browser E2E.
export const happy = { ...dom, Window: class extends dom.Window {
	constructor(...args: any[]) {
		super(...args);
		if (!('WebKitCSSMatrix' in this)) (this as any).WebKitCSSMatrix = class {};
	}
} };
