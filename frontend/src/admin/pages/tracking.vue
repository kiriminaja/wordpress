<script setup lang="ts">
import { ref } from "vue";
import Page from "../components/page.vue";

// TODO: Implement tracking lookup functionality
const awb = ref("");
</script>
<template>
  <Page title="Tracking">
    <UCard>
      <UForm class="space-y-3">
        <UFormField label="AWB" required>
          <UInput
            id="awb"
            v-model="awb"
            class="w-full"
            placeholder="e.g OID-02223323232"
          />
        </UFormField>
        <UButton
          :disabled="!awb.length"
          icon="lucide:search"
          @click="$router.push(`/tracking/${awb}`)"
        >
          Track Shipment Now
        </UButton>
      </UForm>
    </UCard>
  </Page>
</template>
