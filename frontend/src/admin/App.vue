<template>
  <UApp>
    <RouterView>
      <component :is="computedPage" />
    </RouterView>
  </UApp>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import Config from "./pages/config.vue";
import Transaction from "./pages/transaction.vue";
import Payment from "./pages/payment.vue";

const currentPage = ref<"configuration" | "transaction" | "pickup">(
  "configuration"
);

const routes = {
  configuration: Config,
  transaction: Transaction,
  pickup: Payment,
};

const computedPage = computed(() => {
  return routes[currentPage.value] || Config;
});

onMounted(() => {
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
});
</script>
