<template>
  <button
    :class="[
      'kj-button',
      `kj-button--${variant}`,
      { 'kj-button--loading': loading },
    ]"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="kj-button__spinner"></span>
    <span class="kj-button__content">
      <slot />
    </span>
  </button>
</template>

<script setup lang="ts">
defineProps<{
  variant?: "primary" | "secondary" | "danger" | "success";
  loading?: boolean;
  disabled?: boolean;
}>();

defineEmits<{
  click: [event: MouseEvent];
}>();
</script>

<style scoped>
.kj-button {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.kj-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.kj-button--primary {
  background-color: #0073aa;
  color: white;
}

.kj-button--primary:hover:not(:disabled) {
  background-color: #005a87;
}

.kj-button--secondary {
  background-color: #f0f0f1;
  color: #2c3338;
}

.kj-button--secondary:hover:not(:disabled) {
  background-color: #dcdcde;
}

.kj-button--danger {
  background-color: #d63638;
  color: white;
}

.kj-button--danger:hover:not(:disabled) {
  background-color: #b32d2e;
}

.kj-button--success {
  background-color: #00a32a;
  color: white;
}

.kj-button--success:hover:not(:disabled) {
  background-color: #008a20;
}

.kj-button__spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.kj-button--loading .kj-button__content {
  opacity: 0.7;
}
</style>
