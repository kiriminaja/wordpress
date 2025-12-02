<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useWpAjax } from "@/composables/useWpAjax";

interface ShippingSettings {
  origin_name?: string;
  origin_phone?: string;
  origin_address?: string;
  origin_latitude?: string;
  origin_longitude?: string;
  origin_sub_district_id?: string;
  origin_zip_code?: string;
}

const settings = ref<ShippingSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);
const { post } = useWpAjax();

const isOriginDataComplete = computed(() => {
  const requiredFields = [
    "origin_name",
    "origin_phone",
    "origin_address",
    "origin_latitude",
    "origin_longitude",
    "origin_sub_district_id",
    "origin_zip_code",
  ];
  return requiredFields.every(
    (field) => settings.value[field as keyof ShippingSettings]
  );
});

onMounted(async () => {
  await loadSettings();
});

async function loadSettings() {
  loading.value = true;
  try {
    const result = await post("kiriminaja_get_settings", { tab: "shipping" });
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
      tab: "shipping",
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

    <!-- Shipping Origin Content -->
    <UCard v-else>
      <template #header>
        <h2 class="text-xl font-semibold">Shipping Origin</h2>
      </template>

      <div class="space-y-4">
        <UFormField label="Origin Name" name="origin_name" required>
          <UInput
            id="origin_name"
            v-model="settings.origin_name"
            type="text"
            placeholder="e.g., Main Warehouse"
          />
        </UFormField>

        <UFormField label="Phone Number" name="origin_phone" required>
          <UInput
            id="origin_phone"
            v-model="settings.origin_phone"
            type="tel"
            placeholder="e.g., 081234567890"
          />
        </UFormField>

        <UFormField label="Address" name="origin_address" required>
          <UTextarea
            id="origin_address"
            v-model="settings.origin_address"
            :rows="3"
            placeholder="Complete address of your shipping origin"
          />
        </UFormField>

        <div class="grid grid-cols-2 gap-4">
          <UFormField label="Latitude" name="origin_latitude" required>
            <UInput
              id="origin_latitude"
              v-model="settings.origin_latitude"
              type="text"
              placeholder="-6.200000"
            />
          </UFormField>

          <UFormField label="Longitude" name="origin_longitude" required>
            <UInput
              id="origin_longitude"
              v-model="settings.origin_longitude"
              type="text"
              placeholder="106.816666"
            />
          </UFormField>
        </div>

        <UFormField label="Zip Code" name="origin_zip_code" required>
          <UInput
            id="origin_zip_code"
            v-model="settings.origin_zip_code"
            type="text"
            placeholder="e.g., 12345"
          />
        </UFormField>

        <UFormField
          label="Sub District ID"
          name="origin_sub_district_id"
          required
        >
          <UInput
            id="origin_sub_district_id"
            v-model="settings.origin_sub_district_id"
            type="text"
            placeholder="e.g., 3174010001"
          />
        </UFormField>

        <UAlert
          v-if="isOriginDataComplete"
          title="Origin data is complete!"
          description="Your shipping origin is properly configured."
          color="green"
        />
      </div>

      <template #footer>
        <UButton :loading="saving" :disabled="saving" @click="saveSettings">
          Save Settings
        </UButton>
      </template>
    </UCard>
  </div>
</template>
