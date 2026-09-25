type AjaxConfig = {
  ajaxurl?: string;
  nonce?: string;
};

type RequestOptions = {
  method?: 'GET' | 'POST';
  nested?: boolean;
};

type ServiceResponse<T> = {
  status?: number | string;
  message?: string;
  data?: T;
};

declare global {
  interface Window {
    ajaxurl?: string;
    kiriofAjax?: AjaxConfig;
    kiriofAjaxRoute?: () => string;
    kiriofSettings?: AjaxConfig;
  }
}

async function requestWordPressAction<T>(
  action: string,
  values: Record<string, string>,
  options: RequestOptions = {},
): Promise<T> {
  const method = options.method ?? 'POST';
  const nested = options.nested ?? false;
  const params = new URLSearchParams({ action });
  params.set(nested ? 'data[nonce]' : 'nonce', nonce());

  for (const [key, value] of Object.entries(values)) {
    params.set(nested ? `data[${key}]` : key, value);
  }

  const response = await fetch(method === 'GET' ? `${ajaxUrl()}?${params}` : ajaxUrl(), {
    method,
    credentials: 'same-origin',
    headers:
      method === 'POST'
        ? { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
        : undefined,
    body: method === 'POST' ? params : undefined,
  });
  const payload = (await response.json()) as Record<string, unknown>;
  const result = payload.data as T | undefined;

  if (!response.ok || payload.success === false || result === undefined) {
    const error = result as { message?: string } | undefined;
    throw new Error(error?.message ?? 'Request failed.');
  }

  return result;
}

export function postWordPressRawAction<T>(
  action: string,
  values: Record<string, string> = {},
): Promise<T> {
  return requestWordPressAction<T>(action, values);
}

export function getWordPressAction<T>(
  action: string,
  values: Record<string, string> = {},
): Promise<T> {
  return requestWordPressAction<T>(action, values, { method: 'GET' });
}

function ajaxUrl(): string {
  if (typeof window.kiriofAjaxRoute === 'function') {
    return window.kiriofAjaxRoute();
  }

  return window.kiriofSettings?.ajaxurl ?? window.kiriofAjax?.ajaxurl ?? window.ajaxurl ?? '';
}

function nonce(): string {
  return window.kiriofSettings?.nonce ?? window.kiriofAjax?.nonce ?? '';
}

export async function postWordPressAction<T>(
  action: string,
  values: Record<string, string>,
): Promise<ServiceResponse<T>> {
  const body = new URLSearchParams({ action });
  body.set('data[nonce]', nonce());

  for (const [key, value] of Object.entries(values)) {
    body.set(`data[${key}]`, value);
  }

  const response = await fetch(ajaxUrl(), {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body,
  });

  const payload = (await response.json()) as Record<string, unknown>;
  const wrapped = typeof payload.success === 'boolean';
  const result = (wrapped ? payload.data : payload) as ServiceResponse<T> | undefined;

  if (!response.ok || !result || (wrapped && payload.success === false)) {
    throw new Error(result?.message ?? 'Request failed.');
  }

  if (Number(result.status ?? 0) !== 200) {
    throw new Error(result.message ?? 'Request failed.');
  }

  return result;
}
