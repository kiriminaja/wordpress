import { startClassicPin } from '../classic/pin';

if (document.readyState === 'loading')
  document.addEventListener('DOMContentLoaded', () => startClassicPin(), { once: true });
else startClassicPin();
