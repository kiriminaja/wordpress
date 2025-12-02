<template>
  <div class="kj-tracking-widget">
    <h3>Track Your Shipment</h3>

    <div class="kj-form">
      <input
        v-model="trackingNumber"
        type="text"
        placeholder="Enter tracking number"
        class="kj-input"
        @keyup.enter="trackShipment"
      />
      <KjButton :loading="loading" @click="trackShipment" variant="primary">
        Track
      </KjButton>
    </div>

    <div v-if="error" class="kj-error">
      {{ error }}
    </div>

    <div v-if="trackingData" class="kj-tracking-result">
      <h4>Shipment Status</h4>
      <div class="kj-status">
        <span class="kj-status-badge">{{ trackingData.status }}</span>
      </div>
      <div class="kj-details">
        <p><strong>From:</strong> {{ trackingData.origin }}</p>
        <p><strong>To:</strong> {{ trackingData.destination }}</p>
        <p><strong>Last Update:</strong> {{ trackingData.last_update }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useWpAjax } from "@/composables/useWpAjax";
import KjButton from "./KjButton.vue";

interface TrackingData {
  status: string;
  origin: string;
  destination: string;
  last_update: string;
}

const trackingNumber = ref<string>("");
const trackingData = ref<TrackingData | null>(null);
const { loading, error, post } = useWpAjax();

async function trackShipment() {
  if (!trackingNumber.value) {
    error.value = "Please enter a tracking number";
    return;
  }

  try {
    // Call WordPress AJAX action
    const result = await post<TrackingData>("kiriminaja_track_shipment", {
      tracking_number: trackingNumber.value,
    });

    if (result.success && result.data) {
      trackingData.value = result.data;
    } else {
      error.value = result.message || "Failed to track shipment";
    }
  } catch (e) {
    console.error("Tracking error:", e);
  }
}
</script>

<style scoped>
.kj-tracking-widget {
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.kj-form {
  display: flex;
  gap: 10px;
  margin: 15px 0;
}

.kj-input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.kj-error {
  padding: 10px;
  background-color: #fee;
  color: #c00;
  border-radius: 4px;
  margin: 10px 0;
}

.kj-tracking-result {
  margin-top: 20px;
  padding: 15px;
  background-color: #f9f9f9;
  border-radius: 4px;
}

.kj-status {
  margin: 10px 0;
}

.kj-status-badge {
  display: inline-block;
  padding: 5px 15px;
  background-color: #00a32a;
  color: white;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.kj-details {
  margin-top: 15px;
}

.kj-details p {
  margin: 8px 0;
  font-size: 14px;
}
</style>
