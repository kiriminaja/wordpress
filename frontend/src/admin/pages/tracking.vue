<script setup lang="ts">
import { ref } from "vue";

// TODO: Implement tracking lookup functionality
const awb = ref("");
</script>
<template>
  <Page title="Tracking">
    <UiCard>
      <UiForm class="space-y-3">
        <UiFormField label="AWB" required>
          <UiInput
            id="awb"
            type="text"
            class="w-full"
            placeholder="e.g OID-02223323232"
          />
        </UiFormField>
        <UiButton
          :disabled="!awb.length"
          icon="lucide:search"
          @click="$router.push(`/tracking/${awb}`)"
        >
          Track Shipment Now
        </UiButton>
      </UiForm>
    </UiCard>
  </Page>
</template>
