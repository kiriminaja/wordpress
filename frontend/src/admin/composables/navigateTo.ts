export const navigateTo = (key: string, value?: string) => {
  const url = new URL(window.location.href);
  if (!value || value.length === 0) {
    url.searchParams.delete(key);
  } else {
    url.searchParams.set(key, value);
  }
  window.history.pushState({}, "", url.toString());
};
