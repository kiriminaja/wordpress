<script setup lang="ts">
import { ref, onMounted } from "vue";

interface MenuItem {
  id: string;
  title: string;
  description: string;
  icon: string;
}

const currentPath = ref<string | null>(null);
const isWooCommerceActive = ref(true);
const PageComponent = ref<any>(null);
const isLoadingPage = ref(false);

const menuItems: MenuItem[] = [
  {
    id: "integration",
    title: "Change Setup Key",
    description:
      "Configure your KiriminAja API credentials and connection settings",
    icon: "i-lucide-key",
  },
  {
    id: "shipping",
    title: "Shipping Origin",
    description: "Set up your warehouse or store location information",
    icon: "i-lucide-map-pin",
  },
  {
    id: "advanced",
    title: "Advanced Settings",
    description: "Configure webhooks and expedition whitelist",
    icon: "i-lucide-settings",
  },
];

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  const path = urlParams.get("path");
  if (path) {
    loadPage(path);
  }
});

async function loadPage(pathId: string) {
  isLoadingPage.value = true;
  currentPath.value = pathId;

  try {
    const module = await import(`../pages/${pathId}.vue`);
    PageComponent.value = module.default;
  } catch (error) {
    console.error(`Failed to load page: ${pathId}`, error);
    PageComponent.value = null;
  } finally {
    isLoadingPage.value = false;
  }
}

function navigateToPath(pathId: string) {
  const url = new URL(window.location.href);
  url.searchParams.set("path", pathId);
  window.history.pushState({}, "", url.toString());
  loadPage(pathId);
}

function goBack() {
  const url = new URL(window.location.href);
  url.searchParams.delete("path");
  window.history.pushState({}, "", url.toString());
  currentPath.value = null;
  PageComponent.value = null;
}
</script>

<template>
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar title="Settings" />
    </template>
    <template #body>
      <UAlert
        v-if="!isWooCommerceActive"
        title="WooCommerce Required"
        description="Please install and activate WooCommerce to use KiriminAja shipping integration."
        color="amber"
        class="mb-4"
      />

      <div
        v-if="!currentPath"
        class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
      >
        <UCard
          v-for="item in menuItems"
          :key="item.id"
          class="cursor-pointer hover:shadow-lg transition-shadow"
          :ui="{ body: 'hover:bg-gray-50 transition-colors' }"
          @click="navigateToPath(item.id)"
        >
          <div class="flex items-start gap-4">
            <div
              class="flex items-center justify-center p-3 bg-gray-100 rounded-xl shrink-0"
            >
              <UIcon :name="item.icon" class="w-6 h-6" />
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 mb-1">
                {{ item.title }}
              </h3>
              <p class="text-sm text-gray-600">{{ item.description }}</p>
            </div>
          </div>
        </UCard>
      </div>

      <div v-else>
        <div class="mb-4">
          <UButton
            icon="i-lucide-arrow-left"
            variant="ghost"
            color="gray"
            @click="goBack"
          >
            Back to Menu
          </UButton>
        </div>

        <div v-if="isLoadingPage" class="text-center py-12">
          <div
            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
          ></div>
          <p class="mt-4 text-gray-600">Loading page...</p>
        </div>

        <component v-else-if="PageComponent" :is="PageComponent" />

        <UAlert
          v-else
          title="Page Not Found"
          description="The requested page could not be loaded."
          color="red"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>
