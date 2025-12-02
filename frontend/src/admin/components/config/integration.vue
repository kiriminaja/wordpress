<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useAppFetch } from "@/admin/composables/useAppFetch";

interface IntegrationSettings {
  setup_key?: string;
  oid_prefix?: string;
}

const toast = useToast();
const configured = ref(false);
const settings = ref<IntegrationSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);
const show = ref(false);

onMounted(async () => {
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const res = await useAppFetch("kj_get_integration_data");
    const result = await res.json();
    if (result && result.data.data.setup_key) {
      configured.value = true;
      settings.value = result.data.data || {};
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
    const res = await useAppFetch("kj_store_integration_data", settings.value);
    const result = await res.json();
    if (result && !result.success) {
      throw new Error(result?.data?.message || "Failed to save settings");
    }
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

    <UCard v-else>
      <div class="grid lg:grid-cols-2 gap-5">
        <UForm class="space-y-3">
          <div class="font-semibold text-base">Connection</div>
          <template v-if="!configured">
            <UFormField label="Setup Key" name="setup_key" required>
              <UInput
                id="setup_key"
                v-model="settings.setup_key"
                :type="show || configured ? 'text' : 'password'"
                :ui="{ trailing: 'pe-1' }"
                :disabled="configured"
                class="w-full"
                placeholder="Input your setup key for KiriminAja"
              >
                <template v-if="!configured" #trailing>
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
            <UAlert
              title="Accept Our Privacy & Policy"
              variant="soft"
              icon="lucide:info"
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

            <UButton
              v-if="!configured"
              :loading="saving"
              icon="lucide:plug-zap"
              :disabled="saving || !settings.setup_key?.length"
              @click="saveSettings"
            >
              Connect
            </UButton>
          </template>

          <template v-else>
            <UFormField label="Order Prefix">
              <UInput
                id="oid_prefix"
                v-model="settings.oid_prefix"
                type="text"
                class="w-full"
                placeholder="e.g., KJ-"
                disabled
              />
            </UFormField>
            <UAlert
              title="Order Prefix Means"
              variant="soft"
              icon="lucide:info"
              class="p-3"
              description="Order Prefix are unique identifier to package order_id, it's unique based on each integration. One app represents single prefix."
            />

            <UButton icon="lucide:unplug" color="error"> Disconnect </UButton>
          </template>
        </UForm>
        <div class="space-y-3">
          <div class="font-semibold text-base">
            How to Obtain Your Kiriminaja Credentials
          </div>
          <ol>
            <li>
              Log in to your Kiriminaja dashboard: https://app.kiriminaja.com
            </li>
            <li>Go to the Settings menu and select App Integrations</li>
            <li>Click Add Integration and choose Shopify</li>
            <li>Enter your domain: https://your-shop.com</li>
            <li>Please allow up to 2 business days for API generation.</li>
            <li>Setup Key will appear on the App Integrations page.</li>
            <li>Copy and paste the Setup Key above.</li>
            <li>Start using KiriminAja in your store.</li>
          </ol>
        </div>
      </div>
    </UCard>
  </div>
</template>
