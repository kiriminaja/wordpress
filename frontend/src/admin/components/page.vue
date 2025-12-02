<template>
  <UDashboardNavbar>
    <template #left>
      <UBreadcrumb :items="items" />
    </template>
    <template #right>
      <slot name="actions" />
    </template>
  </UDashboardNavbar>
  <div class="p-3">
    <slot />
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
