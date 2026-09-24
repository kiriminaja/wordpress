export type ToolbarUpdate = {
  label: string;
  title: string;
  version: string;
  description: string;
  details: string[];
  primaryUrl: string;
  primaryLabel: string;
  primaryExternal: boolean;
  secondaryUrl: string;
  secondaryLabel: string;
  secondaryExternal: boolean;
  dismissUrl: string;
  dismissLabel: string;
  closeLabel: string;
};

export type ToolbarMenuItem = {
  label: string;
  href: string;
};

export type ToolbarMenu = {
  label: string;
  items: ToolbarMenuItem[];
};

export type ToolbarConfig = {
  logoUrl: string;
  rootUrl: string;
  rootLabel: string;
  title: string;
  update?: ToolbarUpdate;
  menu?: ToolbarMenu;
};
