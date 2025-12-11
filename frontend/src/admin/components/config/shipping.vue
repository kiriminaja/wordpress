<script setup lang="ts">
import { useAppFetch } from "@/admin/composables/useAppFetch";
import { ref, onMounted, computed } from "vue";
import Search from "../coverage/search.vue";
import { useToast } from "@/admin/composables/useToast";

interface ShippingSettings {
  origin_name?: string;
  origin_phone?: string;
  origin_address?: string;
  origin_latitude?: string;
  origin_longitude?: string;
  origin_sub_district_id?: string;
  origin_sub_district_name?: string;
  origin_zip_code?: string;
}

const toast = useToast();
const settings = ref<ShippingSettings>({});
const loading = ref(true);
const saving = ref(false);
const message = ref<{ type: "success" | "error"; text: string } | null>(null);

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
    const res = await useAppFetch("kj_get_origin_data");
    const result = await res.json();
    if (result && result.data?.data) {
      settings.value = result.data.data || {};
    }
  } catch (e) {
    console.error("Failed to load settings:", e);
    toast.add({
      color: "error",
      title: "Failed to load settings",
      description: "An error occurred while fetching shipping origin settings.",
    });
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  message.value = null;
  try {
    const res = await useAppFetch("kj_store_origin_data", settings.value);
    toast.add({
      color: "success",
      title: "Settings saved successfully!",
      description: "Your shipping origin settings have been updated.",
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

    <!-- Shipping Origin Content -->
    <UiCard v-else>
      <UiForm class="space-y-4">
        <UiFormField label="Origin Name" name="origin_name" required>
          <UiInput
            id="origin_name"
            v-model="settings.origin_name"
            type="text"
            class="w-full"
            placeholder="e.g., Main Warehouse"
          />
        </UiFormField>

        <UiFormField label="Phone Number" name="origin_phone" required>
          <UiInput
            id="origin_phone"
            v-model="settings.origin_phone"
            type="tel"
            class="w-full"
            placeholder="e.g., 081234567890"
          />
        </UiFormField>

        <UiFormField label="Address" name="origin_address" required>
          <UiTextarea
            id="origin_address"
            v-model="settings.origin_address"
            :rows="3"
            class="w-full"
            placeholder="Complete address of your shipping origin"
          />
        </UiFormField>

        <div class="grid grid-cols-2 gap-4">
          <UiFormField label="Latitude" name="origin_latitude" required>
            <UiInput
              id="origin_latitude"
              v-model="settings.origin_latitude"
              type="text"
              class="w-full"
              placeholder="-6.200000"
            />
          </UiFormField>

          <UiFormField label="Longitude" name="origin_longitude" required>
            <UiInput
              id="origin_longitude"
              v-model="settings.origin_longitude"
              type="text"
              class="w-full"
              placeholder="106.816666"
            />
          </UiFormField>
        </div>

        <UiFormField label="Zip Code" name="origin_zip_code" required>
          <UiInput
            id="origin_zip_code"
            v-model="settings.origin_zip_code"
            type="text"
            class="w-full"
            placeholder="e.g., 12345"
          />
        </UiFormField>

        <UiFormField label="Area" name="origin_sub_district_id" required>
          <Search
            id="origin_sub_district_id"
            v-model="settings.origin_sub_district_id"
            v-model:text="settings.origin_sub_district_name"
            type="text"
            class="w-full"
            placeholder="e.g., 3174010001"
          />
        </UiFormField>
        <UiButton
          :loading="saving"
          :disabled="saving || !isOriginDataComplete"
          @click="saveSettings"
        >
          Save Settings
        </UiButton>
      </UiForm>
    </UiCard>
  </div>
</template>
