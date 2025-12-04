import { cva, VariantProps } from "class-variance-authority";
import { acceptHMRUpdate, defineStore } from "pinia";
import { ref } from "vue";

export const toast = cva("notice", {
  variants: {
    color: {
      success: "notice-success notice-alt",
      info: "notice-info notice-alt",
      error: "notice-error notice-alt",
      warning: "notice-warning notice-alt",
    },
  },
});

type ToastColor = VariantProps<typeof toast>["color"];

export interface Toast {
  id: number;
  title: string;
  color?: ToastColor;
  description?: string;
  timeout?: number;
  callback?: () => void;
}

export const useToastStore = defineStore("toast", () => {
  const toasts = ref<Toast[]>([]);

  const fire = (toast: Omit<Toast, "id">) => {
    const id = Date.now();
    toasts.value.push({ id, ...toast });
    removeOnTimeout(id, toast.timeout);
    return id;
  };

  const removeOnTimeout = (id: number, timeout = 2500) => {
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id);
    }, timeout);
  };

  return {
    toasts,
    fire,
    removeOnTimeout,
  };
});

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useToastStore, import.meta.hot));
}
