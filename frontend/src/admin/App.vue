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
import Tracking from "./pages/tracking.vue";

const currentPage = ref<
  "configuration" | "transaction" | "pickup" | "tracking"
>("configuration");

const routes = {
  configuration: Config,
  transaction: Transaction,
  pickup: Payment,
  tracking: Tracking,
};

const computedPage = computed(() => {
  return routes[currentPage.value] || Config;
});

onMounted(() => {
  // Detect current WordPress admin page
  const urlParams = new URLSearchParams(window.location.search);
  const page = urlParams.get("page");

  if (page === "settings") {
    currentPage.value = "configuration";
  } else if (page === "transactions") {
    currentPage.value = "transaction";
  } else if (page === "payment") {
    currentPage.value = "pickup";
  } else {
    currentPage.value = page as
      | "configuration"
      | "transaction"
      | "pickup"
      | "tracking";
  }
});
</script>
