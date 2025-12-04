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

export const navigateToPage = (page: string, preserveParams = true) => {
  if (WP_ADMIN_PAGES.includes(page)) {
    if (preserveParams) {
      // Preserve all existing parameters and just update the page
      navigateTo("page", page);
    } else {
      // Navigate to page with only the page parameter
      const url = new URL(window.location.href);
      url.search = `?page=${page}`;
      window.history.pushState({}, "", url.toString());
    }
  }
};

/**
 * Prevent default click behavior on admin menu items and
 * use callback to handle page navigation.
 * @param callback
 * @returns
 */
export const handlePageClick = (callback?: (page: string) => void) => {
  const parent = document.querySelector(".toplevel_page_kiriminaja");

  if (!parent) return;

  const subMenus = parent.getElementsByTagName("a");

  // prevent default click behavior
  for (let i = 0; i < subMenus.length; i++) {
    subMenus[i].addEventListener("click", (e) => {
      const href = subMenus[i].getAttribute("href");
      if (href) {
        e.preventDefault();
        const url = new URL(href, window.location.origin);
        const page = url.searchParams.get("page");
        if (page && WP_ADMIN_PAGES.includes(page)) {
          window.history.pushState({}, "", href);
          callback?.(page);
        }
      }
    });
  }
};
