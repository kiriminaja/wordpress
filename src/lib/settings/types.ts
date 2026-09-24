export type SettingsIconName =
  | 'account'
  | 'tracking'
  | 'product'
  | 'shipping'
  | 'courier'
  | 'insurance'
  | 'cod'
  | 'location'
  | 'webhook'
  | 'technical';

export type SettingItem = {
  key: string;
  label: string;
  description: string;
  icon: SettingsIconName;
  href?: string;
  external?: boolean;
  status?: string;
  statusTone?: 'ready' | 'warning';
  toggle?: 'insurance' | 'cod';
};

export type WebhooksBootstrap = {
  view: 'webhooks';
  toolbar: ToolbarConfig;
  callbackUrl: string;
  i18n: {
    callbackUrl: string;
    save: string;
    saving: string;
    saved: string;
    saveFailed: string;
  };
};

export type TechnicalBootstrap = {
  view: 'technical';
  toolbar: ToolbarConfig;
  downloadLogUrl: string;
  region: {
    state: string;
    lastError: string;
    provinceCount: number;
    cityCount: number;
    updated: string;
    validUntil: string;
  };
  couriers: {
    cached: boolean;
    count: number;
    updated: string;
    validUntil: string;
  };
  i18n: {
    regionTitle: string;
    regionDescription: string;
    courierTitle: string;
    courierDescription: string;
    logsTitle: string;
    logsDescription: string;
    logsPrivacy: string;
    status: string;
    provinces: string;
    cities: string;
    couriers: string;
    lastUpdated: string;
    validUntil: string;
    refreshRegion: string;
    scheduling: string;
    refreshing: string;
    cacheUpdated: string;
    refreshFailed: string;
    flushCouriers: string;
    flushing: string;
    cacheRefreshed: string;
    flushFailed: string;
    cached: string;
    notCached: string;
    downloadLog: string;
  };
};

export type TrackingBootstrap = {
  view: 'tracking';
  toolbar: ToolbarConfig;
  pages: Array<{ id: number; title: string; url: string; editUrl: string }>;
  i18n: {
    guideTitle: string;
    guideSteps: string[];
    pagesTitle: string;
    emptyTitle: string;
    emptyDescription: string;
    view: string;
    edit: string;
  };
};

export type AccountBootstrap = {
  view: 'account';
  toolbar: ToolbarConfig;
  connected: boolean;
  profileError: boolean;
  profile: null | {
    name: string;
    email: string;
    status: string;
    paymentMethod: string;
  };
  couriers: Array<{ code: string; name: string }>;
  termsUrl: string;
  privacyUrl: string;
  dashboardUrl: string;
  i18n: {
    enabledCouriers: string;
    connection: string;
    setupKey: string;
    setupKeyPlaceholder: string;
    connect: string;
    updateConnection: string;
    linkedAccount: string;
    credentialsTitle: string;
    privacyTitle: string;
    credentialsSteps: string[];
    connecting: string;
    disconnect: string;
    disconnecting: string;
    disconnectConfirm: string;
    accountUnavailable: string;
    connectedUnavailable: string;
    enterSetupKey: string;
    connectionFailed: string;
    disconnectFailed: string;
    agreementPrefix: string;
    terms: string;
    agreementAnd: string;
    privacy: string;
  };
};

export type CouriersBootstrap = {
  view: 'couriers';
  toolbar: ToolbarConfig;
  i18n: {
    enableAll: string;
    disableAll: string;
    loading: string;
    noCouriers: string;
    loadFailed: string;
    saveFailed: string;
    count: string;
  };
};

export type SettingsAppBootstrap =
  | SettingsBootstrap
  | WebhooksBootstrap
  | TechnicalBootstrap
  | TrackingBootstrap
  | AccountBootstrap
  | CouriersBootstrap;

export type SettingsGroup = {
  label: string;
  items: SettingItem[];
};

export type SettingsBootstrap = {
  view: 'root';
  toolbar: ToolbarConfig;
  mode: 'configured' | 'unconfigured';
  groups?: SettingsGroup[];
  toggles?: {
    insurance: boolean;
    cod: boolean;
  };
  helpUrl: string;
  i18n: {
    setupKey: string;
    setupKeyPlaceholder: string;
    connect: string;
    connecting: string;
    howToConnect: string;
    enterSetupKey: string;
    connectionFailed: string;
    saveFailed: string;
  };
};
import type { ToolbarConfig } from '$lib/ui/toolbar';
