<script setup lang="ts">
import { cn } from "@/utils/tailwind";
import { useToast } from "@/admin/composables/useToast";
import {
  ToastDescription,
  ToastRoot,
  ToastTitle,
  ToastViewport,
} from "reka-ui";

const { toasts, toast } = useToast();
</script>
<template>
  <template v-for="toastItem in toasts" :key="toastItem.id">
    <ToastRoot
      as="div"
      :open="true"
      :class="
        cn([
          'shadow-lg m-0 rounded-lg p-3 data-[state=open]:animate-slideIn data-[state=closed]:animate-hide data-[swipe=move]:translate-x-(--reka-toast-swipe-move-x) data-[swipe=cancel]:translate-x-0 data-[swipe=cancel]:transition-[transform_200ms_ease-out] data-[swipe=end]:animate-swipeOut',
          toast({
            color: toastItem.color || 'info',
          }),
        ])
      "
    >
      <ToastTitle as="h4" class="m-0">
        {{ toastItem.title }}
      </ToastTitle>
      <ToastDescription v-if="toastItem.description" as="p" class="m-0">
        {{ toastItem.description }}
      </ToastDescription>
    </ToastRoot>
  </template>
  <ToastViewport
    class="[--viewport-padding:25px] fixed bottom-2.5 right-2.5 flex flex-col p-(--viewport-padding) gap-2.5 w-[390px] max-w-[100vw] m-0 list-none z-2147483647 outline-none"
  />
</template>
