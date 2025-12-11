<script setup lang="ts">
import { type HTMLAttributes, useSlots } from "vue";
import { cn } from "@/utils/tailwind";

const slots = useSlots();

const props = defineProps<{
  bodyClass?: HTMLAttributes["class"];
}>();
</script>
<template>
  <div class="postbox rounded-lg divide-y divide-outlineVariant">
    <div v-if="slots.header" class="p-3">
      <slot name="header" />
    </div>
    <div v-if="slots.default" :class="cn(['inside p-3 m-0', props.bodyClass])">
      <slot />
    </div>
  </div>
</template>
