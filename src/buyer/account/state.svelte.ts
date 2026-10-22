import type { AccountView, Address } from './types';
export function createAccountState(address: Address): AccountView {
  const view: AccountView = $state({
    address,
    pin: null,
    selection: null,
    loading: false,
    failed: false,
  });
  return view;
}
