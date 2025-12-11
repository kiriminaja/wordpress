<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useAppFetch } from "@/admin/composables/useAppFetch";
import { useToast } from "@/admin/composables/useToast";

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
      color: "error",
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
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div
        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
      ></div>
      <p class="mt-4 text-gray-600">Loading settings...</p>
    </div>

    <UiCard v-else>
      <div class="grid lg:grid-cols-2 gap-5">
        <UiForm class="space-y-3">
          <div class="font-semibold text-base">Connection</div>
          <template v-if="!configured">
            <UiFormField label="Setup Key" name="setup_key" required>
              <UiInput
                id="setup_key"
                v-model="settings.setup_key"
                :type="show || configured ? 'text' : 'password'"
                :ui="{ trailing: 'pe-1' }"
                :disabled="configured"
                class="w-full"
                placeholder="Input your setup key for KiriminAja"
              >
                <template v-if="!configured" #trailing>
                  <UiButton
                    variant="link"
                    size="sm"
                    :icon="show ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                    @click="show = !show"
                  />
                </template>
              </UiInput>
            </UiFormField>
            <UiAlert
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
            </UiAlert>

            <UiButton
              v-if="!configured"
              :loading="saving"
              icon="lucide:plug-zap"
              :disabled="saving || !settings.setup_key?.length"
              @click="saveSettings"
            >
              Connect
            </UiButton>
          </template>

          <template v-else>
            <UiFormField label="Order Prefix">
              <UiInput
                id="oid_prefix"
                v-model="settings.oid_prefix"
                type="text"
                class="w-full"
                placeholder="e.g., KJ-"
                disabled
              />
            </UiFormField>
            <UiAlert
              title="Order Prefix Means"
              variant="soft"
              icon="lucide:info"
              class="p-3 mb-3"
              description="Order Prefix are unique identifier to package order_id, it's unique based on each integration. One app represents single prefix."
            />

            <UiButton icon="lucide:unplug" color="error"> Disconnect </UiButton>
          </template>
        </UiForm>
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
    </UiCard>
  </div>
</template>
