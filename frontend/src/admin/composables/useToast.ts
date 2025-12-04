import { storeToRefs } from "pinia";
import { useToastStore } from "../stores/toast-store";
import { toast } from "../stores/toast-store";

export const useToast = () => {
  const toastStore = useToastStore();
  const { fire } = toastStore;
  const { toasts } = storeToRefs(toastStore);

  return {
    toasts,
    add: fire,
    toast,
  };
};
