<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useWpAjax } from "@/composables/useWpAjax";

interface IntegrationSettings {
  setup_key?: string;
  oid_prefix?: string;
}

const settings = ref<IntegrationSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);
const show = ref(false);
const { post } = useWpAjax();

onMounted(async () => {
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const result = await post("kiriminaja_get_settings", {
      tab: "integration",
    });
    if (result.success && result.data) {
      settings.value = result.data.settings || {};
    }
  } catch (e) {
    console.error("Failed to load settings:", e);
    message.value = { type: "error", text: "Failed to load settings" };
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  message.value = null;
  try {
    const result = await post("kiriminaja_save_settings", {
      tab: "integration",
      settings: JSON.stringify(settings.value),
    });

    if (result.success) {
      message.value = { type: "success", text: "Settings saved successfully!" };
    } else {
      message.value = {
        type: "error",
        text: result.data?.message || "Failed to save settings",
      };
    }
  } catch (e) {
    console.error("Failed to save settings:", e);
    message.value = { type: "error", text: "Failed to save settings" };
  } finally {
    saving.value = false;
  }
}
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

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div
        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
      ></div>
      <p class="mt-4 text-gray-600">Loading settings...</p>
    </div>

    <!-- Integration Content -->
    <UCard v-else>
      <template #header>
        <h2 class="text-xl font-semibold">Account Configuration</h2>
      </template>

      <div class="space-y-4">
        <UFormField label="Setup Key" name="setup_key" required>
          <UInput
            id="setup_key"
            v-model="settings.setup_key"
            :type="show ? 'text' : 'password'"
            :ui="{ trailing: 'pe-1' }"
            placeholder="Input your setup key for KiriminAja"
          >
            <template #trailing>
              <UButton
                color="neutral"
                variant="link"
                size="sm"
                :icon="show ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                @click="show = !show"
              />
            </template>
          </UInput>
        </UFormField>

        <UFormField label="Order ID Prefix" name="oid_prefix">
          <UInput
            id="oid_prefix"
            v-model="settings.oid_prefix"
            type="text"
            placeholder="e.g., WC"
          />
          <template #help>
            Optional prefix for order IDs sent to KiriminAja
          </template>
        </UFormField>
      </div>

      <template #footer>
        <UButton :loading="saving" :disabled="saving" @click="saveSettings">
          Save Settings
        </UButton>
      </template>
    </UCard>
  </div>
</template>
