<script setup lang="ts">
import { cva, VariantProps } from "class-variance-authority";
import { cn } from "@/utils/tailwind";

const button = cva("button", {
  variants: {
    color: {
      primary: "button-primary",
      secondary: "button-secondary",
    },
  },
});

type ButtonProps = VariantProps<typeof button>;

withDefaults(
  defineProps<{
    color?: ButtonProps["color"];
  }>(),
  {
    color: "primary",
    disabled: false,
  }
);
</script>
<template>
  <button :class="cn([button({ color })])">
    <slot></slot>
  </button>
</template>
