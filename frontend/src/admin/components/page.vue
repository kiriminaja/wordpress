<template>
  <div class="sticky top-0 bg-white p-3 flex justify-between gap-3">
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
          <a v-if="value.to" :href="value.to" class="text-inherit! opacity-80">
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
    <slot name="actions" />
  </div>
  <div class="p-3">
    <slot />
    <div class="text-center mt-3">
      Version: | © 2025 PT Selalu Siap Solusi. All rights reserved.
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
  title: string;
  backAction?: {
    label: string;
    to?: string;
  };
}>();

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
      to: props.backAction.to || null,
    });
  }

  breadcrumbItems.push({
    label: props.title,
  });

  return breadcrumbItems;
});
</script>
