import { ref, computed, type Ref } from "vue";

interface AjaxResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
}

interface UseWpAjaxReturn {
  loading: Ref<boolean>;
  error: Ref<string | null>;
  post: <T = any>(
    action: string,
    data?: Record<string, any>
  ) => Promise<AjaxResponse<T>>;
}

/**
 * Composable for making WordPress AJAX requests
 */
export function useWpAjax(): UseWpAjaxReturn {
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const ajaxUrl = computed(
    () => window.myjs?.ajaxurl || "/wp-admin/admin-ajax.php"
  );

  async function post<T = any>(
    action: string,
    data: Record<string, any> = {}
  ): Promise<AjaxResponse<T>> {
    loading.value = true;
    error.value = null;

    try {
      const formData = new URLSearchParams({
        action,
        ...data,
      });

      const response = await fetch(ajaxUrl.value, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: formData,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = (await response.json()) as AjaxResponse<T>;
      return result;
    } catch (e) {
      const errorMessage = e instanceof Error ? e.message : "Unknown error";
      error.value = errorMessage;
      throw e;
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    error,
    post,
  };
}
