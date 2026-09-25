export type OnboardingBootstrap = {
  initialStep: OnboardingStep;
  steps: Array<{ key: OnboardingStep; label: string; done: boolean }>;
  ajaxUrl: string;
  nonce: string;
  shippingUrl: string;
  dashboardUrl?: string;
  helpUrl?: string;
  i18n: {
    back: string;
    continue: string;
    finish: string;
    accountRequired: string;
    saveFailed: string;
    networkError: string;
    disconnectConfirm: string;
    disconnectFailed: string;
    subdistrictLoading: string;
    subdistrictNoResults: string;
    subdistrictTypeMore: string;
    subdistrictSearchFailed: string;
    currentLocation: string;
    currentLocationUnavailable: string;
    currentLocationFailed: string;
    shippingAddressRequired: string;
    courierRequired: string;
    shippingPrerequisite: string;
    addressSaved: string;
    accountConnected: string;
    couriersSaved: string;
    shippingLocationsRequired: string;
  };
  account: {
    connected: boolean;
    profileError: boolean;
    profile: null | { name: string; email: string; status: string; paymentMethod: string };
    title: string;
    description: string;
    helpUrl: string;
    i18n: {
      connection: string;
      setupKey: string;
      setupKeyPlaceholder: string;
      findKey: string;
      learnHow: string;
      disconnect: string;
      unavailable: string;
    };
  };
  address: {
    title: string;
    description: string;
    values: Record<string, string>;
    i18n: {
      senderName: string;
      senderPhone: string;
      address: string;
      zipcode: string;
      subdistrict: string;
      searchSubdistrict: string;
      mapHelp: string;
    };
  };
  couriers: {
    title: string;
    description: string;
    i18n: {
      enableAll: string;
      loading: string;
      empty: string;
      enabled: string;
      enable: string;
    };
  };
  shipping: {
    title: string;
    description: string;
    shippingReady: boolean;
    locationsReady: boolean;
    settingsUrl: string;
    i18n: {
      methodTitle: string;
      methodDescription: string;
      locationsTitle: string;
      locationsDescription: string;
      help: string;
      openSettings: string;
    };
  };
};

export type OnboardingStep = 'account' | 'address' | 'couriers' | 'shipping' | 'complete';
export type OnboardingCourier = { code: string; name: string; type?: string };
