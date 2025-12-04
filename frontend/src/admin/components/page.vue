<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, useSlots } from "vue";

const props = defineProps<{
  title: string;
  backAction?: {
    label: string;
    onAction?: () => void;
  };
}>();

const wrapperSize = reactive({
  left: 0,
  top: 0,
});

const items = computed(() => {
  const breadcrumbItems: Record<string, any>[] = [
    {
      icon: "i-lucide-home",
      to: "/docs",
    },
  ];

  if (props.backAction) {
    breadcrumbItems.push({
      label: props.backAction.label,
      to: "#",
    });
  }

  breadcrumbItems.push({
    label: props.title,
  });

  return breadcrumbItems;
});

const slots = useSlots();
const calculateWpContent = () => {
  const wpContent = document.getElementById("wpcontent");
  if (wpContent) {
    // get margin left of wpContent
    const style = window.getComputedStyle(wpContent);
    wrapperSize.left = parseInt(style.marginLeft) || 0;
  }

  const adminBar = document.getElementById("wpadminbar");
  if (adminBar) {
    wrapperSize.top = adminBar.offsetHeight;
  }
};

const resizeObserver = new ResizeObserver(() => {
  calculateWpContent();
});

onMounted(() => {
  calculateWpContent();

  const wpContent = document.getElementById("wpcontent");
  if (wpContent) {
    resizeObserver.observe(wpContent);
  }
});

onUnmounted(() => {
  resizeObserver.disconnect();
});

onMounted(() => {
  calculateWpContent();
});
</script>

<template>
  <div
    class="fixed top-(--top-space) left-(--left-space) shadow z-30 right-0 bg-white p-3 flex justify-between gap-3"
    :style="{
      '--top-space': wrapperSize.top + 'px',
      '--left-space': wrapperSize.left + 'px',
    }"
  >
    <ul class="m-0! p-0! flex items-center">
      <li class="m-0">
        <img src="https://kiriminaja.com/favicon.ico" class="w-6 h-6" alt="" />
      </li>
      <template v-for="(value, key) in items" :key="value.label">
        <li
          :class="{
            'text-base m-0': true,
            'font-semibold': key === items.length - 1,
          }"
        >
          <a
            v-if="value.to"
            href="#"
            class="text-inherit! opacity-80"
            @click.prevent="props.backAction?.onAction"
          >
            {{ value.label }}
          </a>
          <span v-else>
            {{ value.label }}
          </span>
        </li>
        <!-- show separator if not last -->
        <li
          v-if="key < items.length - 1"
          class="inline-block mx-2 text-gray-400 m-0"
        >
          <UIcon name="lucide:chevron-right" class="size-4" />
        </li>
      </template>
    </ul>
    <div v-if="slots.actions" class="flex justify-end gap-1">
      <slot name="actions" />
    </div>
  </div>
  <div class="px-3 pb-3 pt-20">
    <slot />
    <div class="text-center mt-3">
      Version: | © 2025 PT Selalu Siap Solusi. All rights reserved.
    </div>
  </div>
</template>
