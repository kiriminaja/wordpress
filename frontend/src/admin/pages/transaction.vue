<script setup lang="ts">
import Table from "../components/ui/table.vue";
import Page from "../components/page.vue";
import { useAppFetch } from "../composables/useAppFetch";
import { onMounted } from "vue";
import Button from "../components/ui/button.vue";

const loadData = () => {
  useAppFetch("kaj_transactions");
};

onMounted(() => {
  loadData();
});
</script>
<template>
  <Page title="Transactions">
    <template #actions>
      <Button color="secondary" variant="outline">Print Labels</Button>
      <Button>Process Shipment</Button>
    </template>
    <Table />
  </Page>
</template>
