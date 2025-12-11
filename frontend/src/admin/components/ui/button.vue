<script setup lang="ts">
import { cva, VariantProps } from "class-variance-authority";
import { cn } from "@/utils/tailwind";
import { Icon } from "@iconify/vue";

const button = cva("button inline-flex! items-center gap-2", {
  variants: {
    color: {
      primary: "button-primary",
      secondary: "button-secondary",
      error: "button-primary bg-error! border-error! text-onError",
    },
  },
});

type ButtonProps = VariantProps<typeof button>;

withDefaults(
  defineProps<{
    color?: ButtonProps["color"];
    icon?: string;
    href?: string;
  }>(),
  {
    color: "primary",
    disabled: false,
  }
);
</script>
<template>
  <component
    :is="href ? 'a' : 'button'"
    :href="href"
    :class="cn([button({ color })])"
  >
    <Icon v-if="icon" class="size-4 shrink-0" :icon="icon" />
    <slot />
  </component>
</template>
