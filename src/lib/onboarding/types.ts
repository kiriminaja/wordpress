export type OnboardingBootstrap = {
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
      disableAll: string;
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

export type OnboardingCourier = { code: string; name: string; type?: string };
