<script
  setup
  lang="ts"
  generic="
    T extends Record<string, AcceptableValue | boolean | undefined>,
    U extends keyof T
  "
>
import { useElementVisibility, useVModel } from "@vueuse/core";
import { Icon } from "@iconify/vue";
import { cn } from "@/utils/tailwind";
import {
  ComboboxAnchor,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxRoot,
  ComboboxTrigger,
  ComboboxViewport,
  type AcceptableValue,
} from "reka-ui";
import { type Ref, ref, watch, type HTMLAttributes, useTemplateRef } from "vue";

const props = withDefaults(
  defineProps<{
    placeholder?: string;
    icon?: string;
    modelValue?: AcceptableValue;
    nullable?: boolean;
    loadingIcon?: string;
    valueKey?: U;
    title?: string;
    invalid?: boolean;
    class?: HTMLAttributes["class"];
    rootClass?: HTMLAttributes["class"];
    labelKey?: U;
    options?: T[];
    search?: (
      query: string,
      nextPage?: number
    ) => Promise<{
      data: T[] | undefined;
      meta?: {
        nextPage?: number;
      };
    }>;
    isLoading?: boolean;
    minLength?: number;
    immediateSearch?: boolean;
  }>(),
  {
    placeholder: "Cari di sini...",
    modelValue: undefined,
    icon: "material-symbols:keyboard-arrow-down",
    title: undefined,
    options: undefined,
    loadingIcon: "svg-spinners:90-ring-with-bg",
    valueKey: () => "id" as U,
    labelKey: () => "name" as U,
    search: undefined,
    class: undefined,
    rootClass: undefined,
    minLength: 1,
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: number): void;
  (e: "update:isLoading", value?: boolean): void;
  (e: "select", payload: T): void;
}>();

const setQueryMatchModel = () => {
  if (!props.title && props.modelValue) {
    if (list.value.length > 0) {
      query.value =
        list.value
          .find((item) => item[props.valueKey] === props.modelValue)
          ?.[props.labelKey]?.toString() ?? "";
    }
  }
};
const list = ref<T[]>(props.options ?? []) as Ref<T[]>;
const content = useVModel(props, "modelValue", emit);
const query = ref(props.title ?? "");
const nextPage = ref<number>();
const loadingNextPage = ref(false);
const loading = useVModel(props, "isLoading", emit, {
  defaultValue: false,
  passive: true,
}) as Ref<boolean>;

const debounce = ref<NodeJS.Timeout | null>(null);
const onSearch = async (searchQuery: string, isNextPage = false) => {
  if (!props.search) return;

  if (!isNextPage) list.value = [];

  if (searchQuery.length < props.minLength && !props.immediateSearch) {
    list.value = [];
    nextPage.value = undefined;
    loading.value = false;
    loadingNextPage.value = false;
    return;
  }

  if (isNextPage) loadingNextPage.value = true;
  else loading.value = true;

  if (debounce.value) {
    clearTimeout(debounce.value);
  }
  debounce.value = setTimeout(async () => {
    if (!props.search) return;

    try {
      const fetchList = await props.search(searchQuery, nextPage.value);
      nextPage.value = fetchList?.meta?.nextPage || undefined;
      list.value = isNextPage
        ? ([...list.value, ...(fetchList?.data ?? [])] as T[])
        : ((fetchList?.data ?? []) as T[]);
    } catch (error) {
      console.error("Search error:", error);
      nextPage.value = undefined;
      list.value = [];
    } finally {
      if (isNextPage) loadingNextPage.value = false;
      else loading.value = false;
    }
  }, 500);
};

const displayValue = (value: number) => {
  const selected = list.value.find((item) => item[props.valueKey] === value);
  return selected
    ? selected[props.labelKey]?.toString() ?? props.title ?? ""
    : props.title ?? "";
};

watch(
  () => props.options,
  (newOptions) => {
    if (props.search) return onSearch(query.value);

    list.value = newOptions ?? [];
    setQueryMatchModel();
  },
  { immediate: !!props.search }
);

const bottomOfListRef = useTemplateRef("bottomOfListRef");
const bottomOfListVisible = useElementVisibility(bottomOfListRef);

watch(
  bottomOfListVisible,
  (visible) => {
    if (visible) onSearch(query.value, true);
  },
  { immediate: true }
);

const clearList = () => {
  list.value = [];
  query.value = props.title ?? "";
};

defineExpose({
  clearList,
});
</script>

<template>
  <ComboboxRoot
    v-model="content"
    :ignore-filter="!!props.search"
    :class="cn('relative', props.rootClass)"
    @update:model-value="
      (evt) => {
        if (evt === '-') {
          content = undefined;
        }
      }
    "
  >
    <ComboboxAnchor
      class="w-full inline-flex items-center justify-between text-sm relative"
    >
      <ComboboxInput
        v-model="query"
        :display-value="displayValue"
        :class="
          cn(
            {
              'pr-8': props.icon,
            },
            props.class
          )
        "
        :placeholder="props.placeholder"
        @input="onSearch(query)"
      />
      <ComboboxTrigger
        class="absolute text-flux-primary right-2 top-1/2 -translate-y-1/2"
      >
        <Icon :icon="loading ? loadingIcon : props.icon" size="24px" />
      </ComboboxTrigger>
    </ComboboxAnchor>

    <ComboboxContent
      class="absolute z-30 w-full max-h-60 transition-all shadow overflow-auto mt-1 bg-background rounded-lg border border-outline-variant"
    >
      <ComboboxViewport class="p-1 space-y-1">
        <ComboboxEmpty
          class="text-sm font-medium flex gap-3 p-0.5 items-center"
          :class="{
            'text-flux-primary animate-pulse': loading,
            'text-flux-error': !loading,
            'text-flux-info': query == '' || query.length < props.minLength,
          }"
        >
          <Icon
            size="20px"
            :icon="
              loading
                ? loadingIcon
                : query === '' || query.length < props.minLength
                ? 'material-symbols:info'
                : 'material-symbols:close'
            "
          />
          {{
            loading
              ? "Mencari data..."
              : query === "" || query.length < minLength
              ? `Min. ${minLength} karakter untuk melakukan pencarian`
              : "Tidak ada hasil ditemukan"
          }}
        </ComboboxEmpty>

        <ComboboxItem
          v-for="(option, index) in props.nullable
            ? [
                {
                  [props.valueKey]: null,
                  [props.labelKey]: 'Semua Pilihan'
                } as T,
                ...list
              ]
            : list"
          :key="`option-${index}`"
          :value="option[props.valueKey] || '-'"
          class="rounded data-[highlighted]:bg-primary-container data-[state='checked']:bg-primary-container data-[state='checked']:text-flux-primary flex items-center py-2 text-sm hover:bg-surface-container-low pl-8 cursor-pointer pr-2.5 relative select-none"
          @select="emit('select', option)"
        >
          <ComboboxItemIndicator class="absolute left-1.5">
            <Icon icon="material-symbols:check" size="20px" />
          </ComboboxItemIndicator>
          <slot :item="option">
            <span>
              {{ option[props.labelKey] }}
            </span>
          </slot>
        </ComboboxItem>

        <div
          v-if="nextPage && !loading"
          ref="bottomOfListRef"
          class="w-full h-2"
        ></div>
        <div
          v-if="loadingNextPage"
          class="flex justify-center items-center text-outline pb-2"
        >
          <Icon icon="svg-spinners:90-ring-with-bg" size="24px" />
        </div>
      </ComboboxViewport>
    </ComboboxContent>
  </ComboboxRoot>
</template>
