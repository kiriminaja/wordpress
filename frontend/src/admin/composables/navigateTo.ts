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
  // Try multiple possible selectors for the KiriminAja menu
  const possibleSelectors = [
    ".toplevel_page_kiriminaja",
    "#toplevel_page_kiriminaja",
    ".menu-top[id*='kiriminaja']",
    "li[id*='kiriminaja']",
  ];

  let parent: Element | null = null;

  for (const selector of possibleSelectors) {
    parent = document.querySelector(selector);
    if (parent) break;
  }

  if (!parent) {
    console.warn(
      "KiriminAja menu parent element not found. Checked selectors:",
      possibleSelectors
    );
    return;
  }

  const subMenus = parent.getElementsByTagName("a");

  // Add click handlers to menu links
  for (let i = 0; i < subMenus.length; i++) {
    subMenus[i].addEventListener("click", (e) => {
      const href = subMenus[i].getAttribute("href");
      if (href) {
        const url = new URL(href, window.location.origin);
        const page = url.searchParams.get("page");

        // Only prevent default and handle navigation for our known pages
        if (page && WP_ADMIN_PAGES.includes(page)) {
          e.preventDefault();
          window.history.pushState({}, "", href);
          callback?.(page);
        }
        // For unknown pages, let the browser handle navigation normally
      }
    });
  }
};
