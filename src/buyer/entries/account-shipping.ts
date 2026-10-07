import { bootAccountShipping } from '../account/bridge';
import type { AccountRoot } from '../account/types';
if (typeof window !== 'undefined') bootAccountShipping(window as AccountRoot);
