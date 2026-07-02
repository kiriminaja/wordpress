import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    include: ['client/tests/**/*.spec.ts'],
    setupFiles: ['client/tests/setup.ts'],
  },
});
