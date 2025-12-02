interface AjaxResponse<T = any> extends Response {
  success?: boolean;
  data?: T;
  message?: string;
}

/**
 * Composable for making WordPress AJAX requests
 */
export const useAppFetch = async <T = any>(
  action: string,
  body?: Record<string, string>
): Promise<AjaxResponse<T>> => {
  // Create URLSearchParams for application/x-www-form-urlencoded
  const params = new URLSearchParams();
  params.append("action", action);
  params.append("nonce", window.nonce || "");
  params.append("data[nonce]", window.nonce || "");

  params.append("data[_type]", "query");

  if (body) {
    Object.entries(body).forEach(([key, value]) => {
      params.append(`data[${key}]`, value);
    });
  }

  return await fetch("/wp-admin/admin-ajax.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      "X-Requested-With": "XMLHttpRequest",
      Accept: "application/json, text/javascript, */*; q=0.01",
    },
    body: params,
  });
};
