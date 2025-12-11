<script setup lang="ts">
import { useVModel } from "@vueuse/core";

const props = withDefaults(
  defineProps<{
    name?: string;
    label?: string;
    modelValue?: boolean;
  }>(),
  {
    modelValue: false,
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
}>();

const modelValue = useVModel(props, "modelValue", emit);
</script>
<template>
  <label :for="props.name">
    <input
      v-model="modelValue"
      name="admin_bar_front"
      type="checkbox"
      :id="props.name"
      value="1"
    />
    {{ props.label }}
  </label>
</template>
