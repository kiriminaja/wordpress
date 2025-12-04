
// AUTO-GENERATED FILE — DO NOT EDIT - Run "bun run generate:ajax" to regenerate, need to commit this file
export type WpAjaxAction =
  | "getDestinationArea"
  | "kaj_transactions"
  | "kiriminaja_get_settings"
  | "kiriminaja_save_settings"
  | "kiriminaja_search_expedition"
  | "kiriminaja_subdistrict_search"
  | "kj-get-expedition-ajax"
  | "kj-tracking-ajax"
  | "kj_disconnect_integration"
  | "kj_get_call_back_data"
  | "kj_get_data_after_update_checkout"
  | "kj_get_integration_data"
  | "kj_get_origin_data"
  | "kj_get_payment_form"
  | "kj_get_shipping_process_detail"
  | "kj_get_shipping_reschedule_pickup"
  | "kj_request_pickup_schedule"
  | "kj_request_pickup_transaction"
  | "kj_store_call_back_data"
  | "kj_store_integration_data"
  | "kj_store_origin_data"
  | "kj_transaction-detail-summary"
  | "nopriv_getDestinationArea"
  | "nopriv_kiriminaja_subdistrict_search"
  | "nopriv_kj-get-expedition-ajax"
  | "nopriv_kj-tracking-ajax"
  | "nopriv_kj_disconnect_integration"
  | "nopriv_kj_get_call_back_data"
  | "nopriv_kj_get_data_after_update_checkout"
  | "nopriv_kj_get_integration_data"
  | "nopriv_kj_get_origin_data"
  | "nopriv_kj_store_call_back_data"
  | "nopriv_kj_store_integration_data"
  | "nopriv_kj_store_origin_data";

export type WpAdminPage =
  | "kaj-payment"
  | "kaj-settings"
  | "kaj-tracking"
  | "kaj-transactions"
  | "kiriminaja";

export const WP_ADMIN_PAGES: string[] = ["kaj-payment", "kaj-settings", "kaj-tracking", "kaj-transactions", "kiriminaja"] as const;
