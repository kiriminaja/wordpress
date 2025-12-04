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

const routes: Record<string, any> = {
  "kaj-settings": Config,
  "kaj-transactions": Transaction,
  "kaj-payment": Payment,
  "kaj-tracking": Tracking,
  kiriminaja: Config,
};

const currentPage = ref<keyof typeof routes>("kiriminaja");
const computedPage = computed(() => {
  return routes[currentPage.value];
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

  handlePageClick((page) => {
    currentPage.value = page;
  });
});

const open = ref(false);
const eventDateRef = ref(new Date());
const timerRef = ref(0);

function oneWeekAway() {
  const now = new Date();
  const inOneWeek = now.setDate(now.getDate() + 7);
  return new Date(inOneWeek);
}

function prettyDate(date: Date) {
  return new Intl.DateTimeFormat("en-US", {
    dateStyle: "full",
    timeStyle: "short",
  }).format(date);
}

function handleClick() {
  open.value = false;
  window.clearTimeout(timerRef.value);
  timerRef.value = window.setTimeout(() => {
    eventDateRef.value = oneWeekAway();
    open.value = true;
  }, 100);
}
</script>
