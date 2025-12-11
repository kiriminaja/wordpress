<script setup lang="ts">
import { cva, VariantProps } from "class-variance-authority";
import { cn } from "@/utils/tailwind";
import { Icon } from "@iconify/vue";

const alert = cva("notice notice-alt gap-3 m-0 flex", {
  variants: {
    color: {
      info: "notice-info",
      success: "notice-success",
      warning: "notice-warning",
      error: "notice-error",
    },
  },
});

type AlertProps = VariantProps<typeof alert>;

withDefaults(
  defineProps<{
    color?: AlertProps["color"];
    title?: string;
    icon?: string;
    description?: string;
  }>(),
  {
    color: "info",
    disabled: false,
  }
);
</script>
<template>
  <div :class="cn([alert({ color })])">
    <div v-if="icon" class="shrink-0">
      <Icon :icon="icon" class="size-4" />
    </div>
    <div class="grow">
      <h4 class="m-0">
        <slot name="title">{{ title }}</slot>
      </h4>
      <p class="m-0">
        <slot name="description">{{ description }}</slot>
      </p>
    </div>
  </div>
</template>
