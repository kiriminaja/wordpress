<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useWpAjax } from "../../composables/useWpAjax";

interface AdvancedSettings {
  callback_url?: string;
  origin_whitelist_expedition_id?: string;
}

const toast = useToast();
const settings = ref<AdvancedSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);
const {
  loading: ajaxLoading,
  error,
  getSettings,
  saveSettings: saveSettingsAjax,
} = useWpAjax();

onMounted(async () => {
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const result = await getSettings("advanced");
    if (result && result.settings) {
      settings.value = result.settings || {};
    }
  } catch (e) {
    console.error("Failed to load settings:", e);
    toast.add({
      color: "red",
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
    const result = await saveSettingsAjax("advanced", settings.value);
    toast.add({
      color: "success",
      title: "Settings saved successfully!",
      description: "Your advanced settings have been updated.",
    });
  } catch (e) {
    console.error("Failed to save settings:", e);
    toast.add({
      color: "red",
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

    <!-- Advanced Settings Content -->
    <UCard v-else>
      <div class="space-y-4">
        <UFormField label="Callback URL" name="callback_url">
          <UInput
            id="callback_url"
            v-model="settings.callback_url"
            type="url"
            placeholder="https://yoursite.com/callback"
          />
          <template #help>
            Webhook URL for status updates from KiriminAja
          </template>
        </UFormField>

        <UFormField
          label="Whitelisted Expeditions"
          name="origin_whitelist_expedition_id"
        >
          <UInput
            id="origin_whitelist_expedition_id"
            v-model="settings.origin_whitelist_expedition_id"
            type="text"
            placeholder="e.g., jne,tiki,pos"
          />
          <template #help>
            Comma-separated expedition IDs to enable (leave empty for all)
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
