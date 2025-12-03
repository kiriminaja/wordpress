<script setup lang="ts">
import Table from "../components/ui/table.vue";
import Page from "../components/page.vue";
</script>
<template>
  <Page title="Transactions">
    <template #actions>
      <UButton color="neutral" variant="outline">Print Labels</UButton>
      <UModal
        :ui="{
          overlay: 'z-[9999] bg-black/80',
          content: 'z-[10000]',
        }"
        title="Process Shipment"
      >
        <UButton>Process Shipment</UButton>
      </UModal>
    </template>
    <Table />
  </Page>
</template>
