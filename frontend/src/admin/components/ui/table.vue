<script setup lang="ts">
import UiCard from "./card.vue";
</script>
<template>
  <UiCard body-class="p-0">
    <template #header> Row Header </template>
    <div class="divide-y divide-outlineVariant">
      <div class="flex gap-1 py-2 px-2.5 font-semibold items-center">
        <div class="flex-1 flex gap-1.5 items-center">
          <UiCheckbox />
          Order / Transaction
        </div>
        <div class="flex-1">Expedition & Service</div>
        <div class="flex-1">Airwaybill / Order ID</div>
        <div class="flex-1">Packages & Fee</div>
        <div class="flex-1 text-end">Action</div>
      </div>
      <div
        v-for="value in 10"
        :key="value"
        class="flex gap-1 py-2 px-2.5 items-center"
      >
        <div class="flex-1 flex gap-1.5 items-center">
          <UiCheckbox />
          <div class="grow">
            <a href="#">#11100</a>
            <div>Yanuar Yanuar</div>
            <div>082243374043</div>
            <div>03 Des 2025, 05:50</div>
          </div>
        </div>
        <div class="flex-1">
          <UBadge>Waiting for Shipment</UBadge>
        </div>
        <div class="flex-1">
          <div>AWB: 30000</div>
          <div>KA Order ID: 30000</div>
        </div>
        <div class="flex-1">
          <div>Nama Produk</div>
          <div>Rp130.000</div>
          <div>Shipping Cost: 30000</div>
          <div>Insurance: 30000</div>
        </div>
        <div class="flex-1 text-end">
          <UiButton color="secondary" icon="lucide:eye" />
        </div>
      </div>
    </div>
  </UiCard>
</template>
