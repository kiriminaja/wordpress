import { bootAccountShipping } from '../account/bridge';
import type { AccountRoot } from '../account/types';
import '../styles/selectors.css';
if (typeof window !== 'undefined') bootAccountShipping(window as AccountRoot);
