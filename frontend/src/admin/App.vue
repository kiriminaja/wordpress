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
import { navigateToPage } from "./composables/navigateTo";

const routes = {
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

const handlePageClick = () => {
  const parent = document.querySelector(".toplevel_page_kiriminaja");

  if (!parent) return;

  const subMenus = parent.getElementsByTagName("a");

  // prevent default click behavior
  for (let i = 0; i < subMenus.length; i++) {
    subMenus[i].addEventListener("click", (e) => {
      const page = subMenus[i].getAttribute("href")?.split("page=")[1];
      if (page) {
        e.preventDefault();
        currentPage.value = page as keyof typeof routes;
        navigateToPage(page);
      }
    });
  }
};

onMounted(() => {
  const page = window.location.href
    .split("page=")[1]
    ?.split("&")[0] as keyof typeof routes;
  currentPage.value = page;
  handlePageClick();
});
</script>
