export type TransactionFiltersBootstrap = {
  filters: {
    key: string;
    month: string;
    status: string;
    cod: string;
    courier: string;
    print_status: string;
    search_by: string;
  };
  statusTabs: Array<{ value: string; label: string; count: number }>;
  monthOptions: Record<string, string>;
  couriers: Array<{ value: string; label: string }>;
  pagination: { page: number; totalPages: number; total: number };
  i18n: Record<string, string>;
};

export type TransactionTableBootstrap = {
  rowsHtml: string[];
  i18n: Record<string, string>;
};
