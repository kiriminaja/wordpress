<template>
  <ToastProvider>
    <Toast />
    <RouterView>
      <component :is="computedPage" />
    </RouterView>
  </ToastProvider>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import Config from "./pages/config.vue";
import Transaction from "./pages/transaction.vue";
import Payment from "./pages/payment.vue";
import Tracking from "./pages/tracking.vue";
import Toast from "./components/ui/toast.vue";
import { handlePageClick } from "./composables/navigateTo";
import { ToastProvider } from "reka-ui";
import Welcome from "./pages/welcome.vue";

const routes: Record<string, any> = {
  "kaj-settings": Config,
  "kaj-transactions": Transaction,
  "kaj-payment": Payment,
  "kaj-tracking": Tracking,
  kiriminaja: Config,
  welcome: Welcome,
};

const currentPage = ref<keyof typeof routes>("welcome");
const computedPage = computed(() => {
  // Get the component for the current page, default to Config if not found
  return routes[currentPage.value] || Welcome;
});

const syncPageFromUrl = () => {
  const url = new URL(window.location.href);
  const page = url.searchParams.get("page") as keyof typeof routes;
  if (page && routes[page]) {
    currentPage.value = page;
  }
};

onMounted(() => {
  // Initial sync
  syncPageFromUrl();

  // Handle browser back/forward navigation
  window.addEventListener("popstate", syncPageFromUrl);

  if (currentPage.value !== "welcome") {
    handlePageClick((page) => {
      currentPage.value = page;
    });
  }
});
</script>
