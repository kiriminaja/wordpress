<script setup lang="ts">
import { useSlots } from "vue";

const slots = useSlots();
</script>
<template>
  <div class="postbox">
    <div v-if="slots.header" class="postbox-header">
      <slot name="header" />
    </div>
    <div v-if="slots.default" class="inside">
      <slot />
    </div>
  </div>
</template>
