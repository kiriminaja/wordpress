<script setup lang="ts">
const props = defineProps<{
  label: string;
  name?: string;
  required?: boolean;
}>();
</script>
<template>
  <div>
    <label :for="name" class="block mb-1 font-medium">
      {{ label }}
      <span v-if="required" class="text-red-600">*</span>
    </label>
    <slot />
  </div>
</template>
