import { bootAddressPresentation } from '../blocks/presentation';
import { bootBuyerCheckout } from '../blocks/checkout';
import { bootBlocksMap } from '../blocks/map';
import { bootCouponNotice, bootCheckoutFieldCleanup } from '../blocks/coupon-notice';
import type { BlocksRoot } from '../blocks/types';

/** Presentation must be available before either React component captures its hook.
 * Woo registration stays React-native. There is no legacy asset import/evaluation. */
if (typeof window !== 'undefined') {
  const root = window as BlocksRoot;
  bootAddressPresentation(root);
  bootBuyerCheckout(root);
  bootBlocksMap(root);
  bootCouponNotice(root);
  bootCheckoutFieldCleanup(root);
}
