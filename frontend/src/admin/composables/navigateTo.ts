import { WP_ADMIN_PAGES } from "@/types/wp";

export const navigateTo = (key: string, value?: string) => {
  const url = new URL(window.location.href);
  if (!value || value.length === 0) {
    url.searchParams.delete(key);
  } else {
    url.searchParams.set(key, value);
  }
  window.history.pushState({}, "", url.toString());
};

export const navigateToPage = (page: string) => {
  if (WP_ADMIN_PAGES.includes(page)) {
    navigateTo("page", page);
  }
};
