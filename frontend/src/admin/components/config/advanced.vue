<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useAppFetch } from "@/admin/composables/useAppFetch";
import Card from "@/admin/components/ui/card.vue";
import { useToast } from "@/admin/composables/useToast";

interface AdvancedSettings {
  callback_url?: string;
}

const toast = useToast();
const settings = ref<AdvancedSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);

const searchAddress = () => {
  useAppFetch("kiriminaja_subdistrict_search", {
    term: "sariharjo",
  });
};
onMounted(async () => {
  searchAddress();
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const res = await useAppFetch("kj_get_call_back_data");
    const result = await res.json();
    if (result && result.data?.data) {
      settings.value = result.data.data || {};
    }
  } catch (e) {
    console.error("Failed to load settings:", e);
    toast.add({
      color: "error",
      title: "Failed to load settings",
      description: "An error occurred while fetching advanced settings.",
    });
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  message.value = null;
  try {
    const result = await useAppFetch("advanced", settings.value);
    toast.add({
      color: "success",
      title: "Settings saved successfully!",
      description: "Your advanced settings have been updated.",
    });
  } catch (e) {
    console.error("Failed to save settings:", e);
    toast.add({
      color: "error",
      title: "Failed to save settings",
      description:
        e instanceof Error
          ? e.message
          : "An error occurred while saving settings.",
    });
  } finally {
    saving.value = false;
  }
}

const simulateToast = () => {
  toast.add({
    title: "This is a simulated toast" + new Date().toLocaleTimeString(),
    description: "Hello from the advanced settings page!",
    color: "info",
  });
};
</script>

<template>
  <div>
    <!-- Success/Error Messages -->
    <UAlert
      v-if="message"
      :title="message.type === 'success' ? 'Success' : 'Error'"
      :description="message.text"
      :color="message.type === 'success' ? 'green' : 'red'"
      class="mb-4"
    />

    <button
      @click="simulateToast"
      class="mb-4 px-4 py-2 bg-blue-500 text-white rounded"
    >
      SIMULATE TOAST
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div
        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
      ></div>
      <p class="mt-4 text-gray-600">Loading settings...</p>
    </div>

    <!-- Advanced Settings Content -->
    <Card v-else>
      CONFIG
      <UForm class="space-y-4">
        <UFormField label="Callback URL" name="callback_url">
          <UInput
            id="callback_url"
            v-model="settings.callback_url"
            type="url"
            class="w-full"
            placeholder="https://yoursite.com/callback"
          />
          <template #help>
            Webhook URL for status updates from KiriminAja
          </template>
        </UFormField>

        <UButton :loading="saving" :disabled="saving" @click="saveSettings">
          Save Settings
        </UButton>
      </UForm>
    </Card>
  </div>
</template>
