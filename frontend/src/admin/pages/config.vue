<script setup lang="ts">
import { ref, computed } from "vue";
import Integration from "../components/config/integration.vue";
import Shipping from "../components/config/shipping.vue";
import Advanced from "../components/config/advanced.vue";
import Page from "../components/page.vue";
import { navigateTo } from "../composables/navigateTo";
import UiCard from "../components/ui/card.vue";
import { Icon } from "@iconify/vue";

interface MenuItem {
  id: string;
  title: string;
  description: string;
  icon: string;
  page?: any;
}

// Reactive URL query management
const currentPath = ref<string | null>(null);
const isWooCommerceActive = ref(true);
const isLoadingPage = ref(false);

// Initialize from URL params
const initializeFromUrl = () => {
  const urlParams = new URLSearchParams(window.location.search);
  currentPath.value = urlParams.get("path");
};

// Watch for URL changes (browser back/forward)
const handlePopState = () => {
  initializeFromUrl();
};

// Initialize on mount
initializeFromUrl();
window.addEventListener("popstate", handlePopState);

const currentPage = computed(() => {
  return menuItems.find((item) => item.id === currentPath.value) || null;
});

const menuItems: MenuItem[] = [
  {
    id: "integration",
    title: "Account Configuration",
    description:
      "Update your KiriminAja Setup Key to connect a different account",
    icon: "lucide:key-square",
    page: Integration,
  },
  {
    id: "shipping",
    title: "Shipping",
    description:
      "This is where your business is located. Tax rates and shipping rates will use this address.",
    icon: "lucide:store",
    page: Shipping,
  },
  {
    id: "advanced",
    title: "Webhook",
    description:
      "This page is how the wordpress communicate with kiriminaja api.",
    icon: "lucide:tool-case",
    page: Advanced,
  },
];

const navigateToPath = (pathId: string) => {
  navigateTo("path", pathId);
  currentPath.value = pathId;
};
</script>

<template>
  <Page
    :title="currentPage?.title ?? 'Settings'"
    :backAction="
      currentPath
        ? { label: 'Settings', onAction: () => navigateToPath('') }
        : undefined
    "
  >
    <UiAlert
      v-if="!isWooCommerceActive"
      title="WooCommerce Required"
      description="Please install and activate WooCommerce to use KiriminAja shipping integration."
      color="warning"
      class="mb-4"
    />

    <div
      v-if="!currentPath"
      class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
    >
      <UiCard
        v-for="item in menuItems"
        :key="item.id"
        class="cursor-pointer hover:shadow-lg"
        @click="navigateToPath(item.id)"
      >
        <div class="flex items-start gap-4">
          <div
            class="flex items-start justify-center p-2 button rounded-full! shrink-0"
          >
            <Icon :icon="item.icon" class="w-6 h-6" />
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-semibold m-0 text-primary">
              {{ item.title }}
            </h3>
            <div class="text-sm">{{ item.description }}</div>
          </div>
          <Icon
            icon="lucide:chevron-right"
            class="size-5 shrink-0 text-gray-400 group-hover:text-primary transition-colors duration-200"
          />
        </div>
      </UiCard>
    </div>

    <div v-else>
      <div v-if="isLoadingPage" class="text-center py-12">
        <div
          class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
        ></div>
        <p class="mt-4 text-gray-600">Loading page...</p>
      </div>

      <component v-else-if="currentPage?.page" :is="currentPage.page" />

      <UiAlert
        v-else
        title="Page Not Found"
        description="The requested page could not be loaded."
        color="error"
      />
    </div>
  </Page>
</template>
