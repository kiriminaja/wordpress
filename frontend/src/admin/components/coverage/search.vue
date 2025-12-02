<script setup lang="ts">
import { useAppFetch } from "@/admin/composables/useAppFetch";
import { useDebounceFn } from "@vueuse/core";
import { ref } from "vue";

const props = defineProps<{
  text?: string;
}>();

const emit = defineEmits<{
  (e: "update:text", value: string): void;
}>();

const loading = ref(false);
const results = ref<
  {
    id?: number;
    text?: string;
  }[]
>([]);

const searchAddress = useDebounceFn((term: string) => {
  useAppFetch("kiriminaja_subdistrict_search", {
    term,
  })
    .then(async (res) => {
      const result = await res.json();
      if (result && result.data) {
        results.value = result.data.map((item: any) => ({
          id: item.id,
          text: item.text,
        }));
      }
    })
    .finally(() => {
      loading.value = false;
    });
}, 300);

const handleOnSearch = (term: string) => {
  loading.value = true;
  searchAddress(term);
};

const handleOnChange = (num?: number) => {
  if (!num) {
    emit("update:text", "");
    return;
  }

  const value = results.value.find((item) => item.id === num) || {};
  emit("update:text", value.text || "");
};
</script>

<template>
  <USelectMenu
    :items="results"
    value-key="id"
    label-key="text"
    ignore-filter
    icon="i-lucide-user"
    placeholder="Select user"
    class="w-48"
    @update:searchTerm="handleOnSearch"
    @update:model-value="handleOnChange"
  />
</template>
