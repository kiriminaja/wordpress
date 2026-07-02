import type { KiriofAjaxConfig } from "../types/wp-dom";

export function getAjaxUrl(config?: KiriofAjaxConfig): string {
  if (config?.ajaxurl) {
    return config.ajaxurl;
  }

  return "";
}

export function exposeAjaxRoute(): void {
  window.kiriofAjaxRoute = function kiriofAjaxRoute(): string {
    return getAjaxUrl(window.kiriofAjax);
  };
}
