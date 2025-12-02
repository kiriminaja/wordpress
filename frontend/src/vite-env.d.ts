/// <reference types="vite/client" />

declare module "*.vue" {
  import type { DefineComponent } from "vue";
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

// WordPress global types
interface WordPressAjax {
  ajaxurl: string;
  nonce?: string;
}

interface Window {
  myjs?: WordPressAjax;
}
