<template>
  <div id="kiriminaja-wp-app">
    <h2>KiriminAja Tracking</h2>
    <p>{{ message }}</p>

    <!-- Your frontend components will go here -->
    <div class="kj-wp-container">
      <!-- Add your frontend UI components -->
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

const message = ref("Vue 3 Frontend App is running!");

onMounted(() => {
  console.log("KiriminAja Frontend App mounted");

  // Access WordPress AJAX URL
  if (window.myjs && window.myjs.ajaxurl) {
    console.log("WordPress AJAX URL:", window.myjs.ajaxurl);
  }
});
</script>

<style scoped>
#kiriminaja-wp-app {
  padding: 20px;
}

.kj-wp-container {
  margin-top: 20px;
}
</style>
