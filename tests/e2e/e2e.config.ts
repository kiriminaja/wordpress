import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';
export default { projectId: 'kiriof-business-poc', targets: [{ name: 'isolated-chromium', engine: web(), app: { url: 'https://fixture.test' } }], workers: 1, retries: 0, timeout: 30000, trace: 'on' } satisfies E2EConfig;
