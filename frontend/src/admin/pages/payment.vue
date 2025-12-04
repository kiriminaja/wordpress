<script setup lang="ts">
import Table from "../components/ui/table.vue";
import Page from "../components/page.vue";
import { useAppFetch } from "../composables/useAppFetch";
import { onMounted } from "vue";

const loadData = () => {
  useAppFetch("kaj_transactions");
};

onMounted(() => {
  loadData();
});
</script>
<template>
  <Page title="Payments"> </Page>
</template>
