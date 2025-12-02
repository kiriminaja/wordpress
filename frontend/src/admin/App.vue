<template>
  <UApp id="kiriminaja-admin-app">
    <Config v-if="currentPage === 'configuration'" />

    <!-- Fallback for other pages -->
    <div v-else class="kj-admin-container">
      <h2>KiriminAja Admin Dashboard</h2>
      <p>{{ message }}</p>
    </div>
  </UApp>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import Config from "./pages/config.vue";

const message = ref("Vue 3 Admin App is running!");
const currentPage = ref<string>("configuration");

onMounted(() => {
  console.log("KiriminAja Admin App mounted");

  // Detect current WordPress admin page
  const urlParams = new URLSearchParams(window.location.search);
  const page = urlParams.get("page");

  if (page === "kiriminaja-konfigurasi") {
    currentPage.value = "configuration";
  } else if (page === "kiriminaja-transaction-process") {
    currentPage.value = "transaction";
  } else if (page === "kiriminaja-request-pickup") {
    currentPage.value = "pickup";
  }

  // Access WordPress AJAX URL
  if (window.myjs && window.myjs.ajaxurl) {
    console.log("WordPress AJAX URL:", window.myjs.ajaxurl);
  }
});
</script>

<style scoped>
#kiriminaja-admin-app {
  margin: -20px -20px 0 0;
}

.kj-admin-container {
  padding: 20px;
}
</style>
