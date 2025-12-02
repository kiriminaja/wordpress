<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useWpAjax } from "../../composables/useWpAjax";

interface IntegrationSettings {
  setup_key?: string;
  oid_prefix?: string;
}

const toast = useToast();
const settings = ref<IntegrationSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);
const show = ref(false);
const { getSettings, saveSettings: saveSettingsAjax } = useWpAjax();

onMounted(async () => {
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const result = await getSettings("integration");
    if (result && result.settings) {
      settings.value = result.settings || {};
    }
  } catch (e) {
    console.error("Failed to load settings:", e);
    toast.add({
      color: "red",
      title: "Failed to load settings",
      description: "An error occurred while fetching integration settings.",
    });
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  message.value = null;
  try {
    const result = await saveSettingsAjax("integration", settings.value);
    toast.add({
      color: "success",
      title: "Settings saved successfully!",
      description: "Your integration settings have been updated.",
    });
  } catch (e) {
    console.error("Failed to save settings:", e);
    toast.add({
      color: "error",
      title: "Failed to save settings",
      description: e instanceof Error ? e.message : "Failed to save settings",
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

    <!-- Integration Content -->
    <UCard
      v-else
      :ui="{
        body: 'space-y-3',
      }"
    >
      <UFormField label="Setup Key" name="setup_key" required>
        <UInput
          id="setup_key"
          v-model="settings.setup_key"
          :type="show ? 'text' : 'password'"
          :ui="{ trailing: 'pe-1' }"
          class="w-full"
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
          class="w-full"
          placeholder="e.g., WC"
        />
      </UFormField>
      <UAlert
        title="Accept Our Privacy & Policy"
        variant="soft"
        icon="lucide:info"
        color="info"
        class="p-3"
      >
        <template #description>
          By clicking Connect, you agree to accept KiriminAja's
          <a
            href="//kiriminaja.com/syarat-ketentuan"
            class="underline"
            target="_blank"
          >
            terms and conditions
          </a>
          and it's
          <a
            href="//kiriminaja.com/privacy-policy"
            class="underline"
            target="_blank"
          >
            privacy policy
          </a>
        </template>
      </UAlert>

      <template #footer>
        <UButton
          :loading="saving"
          icon="lucide:plug-zap"
          :disabled="saving"
          @click="saveSettings"
        >
          Save Settings
        </UButton>
      </template>
    </UCard>
  </div>
</template>
