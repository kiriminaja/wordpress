interface AjaxResponse<T = any> extends Response {
  success?: boolean;
  data?: T;
  message?: string;
}

/**
 * Composable for making WordPress AJAX requests
 */
export const useAppFetch = async <T = any>(
  endpoint: string,
  options?: {
    method: "GET" | "POST";
    headers?: Record<string, string>;
  },
  body?: Record<string, any>
): Promise<AjaxResponse<T>> => {
  return await fetch("/wp-admin/admin-ajax.php" + endpoint, {
    body: JSON.stringify(body),
    method: options?.method || "GET",
    headers: {
      "Content-Type": "application/json",
      ...(options?.headers || {}),
    },
  });
};
