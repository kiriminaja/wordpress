import { ref } from 'vue'

interface AjaxResponse<T = any> {
  success: boolean
  data: T
  message?: string
}

export function useWpAjax() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  const makeRequest = async <T = any>(
    action: string,
    data: Record<string, any> = {},
    method: 'GET' | 'POST' = 'POST'
  ): Promise<T> => {
    loading.value = true
    error.value = null

    try {
      const formData = new FormData()
      formData.append('action', action)
      
      // Add nonce for security
      const adminData = (window as any).kiriminaja_admin
      if (adminData && adminData.wp_ajax_nonce) {
        formData.append('_ajax_nonce', adminData.wp_ajax_nonce)
      }

      // Add data
      Object.entries(data).forEach(([key, value]) => {
        if (typeof value === 'object') {
          formData.append(key, JSON.stringify(value))
        } else {
          formData.append(key, String(value))
        }
      })

      const url = adminData?.ajaxurl || (window as any).ajaxurl || '/wp-admin/admin-ajax.php'
      
      const response = await fetch(url, {
        method,
        body: formData,
        credentials: 'same-origin'
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const result: AjaxResponse<T> = await response.json()

      if (!result.success) {
        throw new Error(result.data?.message || 'Request failed')
      }

      return result.data
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred'
      error.value = errorMessage
      throw new Error(errorMessage)
    } finally {
      loading.value = false
    }
  }

  const getSettings = async (tab: string) => {
    return makeRequest('kiriminaja_get_settings', { tab })
  }

  const saveSettings = async (tab: string, settings: Record<string, any>) => {
    return makeRequest('kiriminaja_save_settings', { 
      tab, 
      settings: JSON.stringify(settings) 
    })
  }

  return {
    loading,
    error,
    makeRequest,
    getSettings,
    saveSettings
  }
}