Object.defineProperty(globalThis, "window", {
  configurable: true,
  value: globalThis,
});

Object.defineProperty(globalThis, "document", {
  configurable: true,
  value: {},
});
