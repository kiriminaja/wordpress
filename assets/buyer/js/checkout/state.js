// Legacy checkout state; globals retained for theme compatibility.
var kiriofBillingAddressConfig = window.kiriofBillingAddressConfig || {};
kiriofBillingAddressConfig.i18n = kiriofBillingAddressConfig.i18n || {};
var kiriofUpdatingCheckoutLock = false;
var kiriofTriggeredInitialShippingUpdate = false;
var kiriofFeeRefreshRequest = null;
var kiriofInFlightFeeRefreshKey = '';
var kiriofPendingFeeRefresh = false;
var kiriofPendingFeeRefreshKey = '';
var kiriofLastCompletedFeeRefreshKey = '';
var kiriofLastCompletedFeeRefreshAt = 0;
var kiriofCodInsuranceTimer = null;
var kiriofBlockRatesRefreshTimer = null;
var kiriofBlockCartRefreshTimer = null;
var kiriofLastBlockCartRefreshKey = '';
var kiriofLastBlockCartRefreshAt = 0;
var kiriofLastBlockCartUpdateKey = '';
var kiriofPendingPaymentMethod = '';
var kiriofPendingPaymentMethodAt = 0;
var kiriofLastRawStoreCustomerUpdateKey = '';
var kiriofLastRawStoreCustomerUpdateAt = 0;
var kiriofLastObservedBlockPostcode = '';
var kiriofSavedDistrictByPostcode = kiriofBillingAddressConfig.savedDistrictByPostcode || {};
var kiriofSavedCheckoutPostcode = kiriofBillingAddressConfig.savedCheckoutPostcode || '';
var kiriofStoreApiNonce = kiriofBillingAddressConfig.storeApiNonce || '';
var kiriofStoreApiUpdateCustomerUrl = kiriofBillingAddressConfig.storeApiUpdateCustomerUrl || '';
var kiriofPendingShippingMethod = '';
var kiriofPendingShippingMethodAt = 0;

// Yield only on an enabled Classic checkout, never on cart/account/Blocks.
function kiriofUsesClassicCheckout() {
    return !!(window.kiriofClassicCheckoutConfig && window.kiriofClassicCheckoutConfig.enabled && window.kiriofClassicCheckoutConfig.ownsDistrict === true && document.querySelector('form.checkout'));
}
