<script setup lang="ts">
import { computed, nextTick, useTemplateRef, type HTMLAttributes } from "vue";
import { useVModel } from "@vueuse/core";
import { inputMaskingConfiguration } from "./variants";
import { cn } from "@/utils/tailwind";
import { emojiRegex } from "@/utils/emojiRegex";

const props = withDefaults(
  defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes["class"];
    invalid?: boolean;
    allowEmoji?: boolean;
    inputmode?: HTMLAttributes["inputmode"];
    maxLength?: HTMLInputElement["maxLength"];
    inputMask?: keyof typeof inputMaskingConfiguration;
    localTel?: boolean;
  }>(),
  {
    defaultValue: undefined,
    class: undefined,
    inputmode: undefined,
    modelValue: undefined,
    inputMask: "default",
    maxLength: undefined,
  }
);

const emits = defineEmits<{
  (e: "update:modelValue", payload?: string | number): void;
  (e: "paste", event: ClipboardEvent): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
  defaultValue: props.defaultValue,
});

const textInputRef = useTemplateRef("textInputRef");

const getInputMask = computed(() =>
  props.localTel ? "number" : props.inputMask
);

const maskedValue = computed({
  get: () =>
    inputMaskingConfiguration[getInputMask.value].get(modelValue.value),
  set: (value) =>
    (modelValue.value =
      inputMaskingConfiguration[getInputMask.value].set(value)),
});

const checkTelephone = (inputValue: string) => {
  if (props.inputmode !== "tel") return;
  if (props.localTel) return;
  if (!inputValue.startsWith("0")) return;
  modelValue.value = inputValue = inputValue.slice(1);
};

const onInput = (e: Event) => {
  const inputEl = e.target as HTMLInputElement;

  if (!props.allowEmoji && emojiRegex.test(inputEl.value)) {
    modelValue.value = inputEl.value = inputEl.value.replace(emojiRegex, "");
  }

  checkTelephone(inputEl.value);
};

const onKeyPress = (event: KeyboardEvent) => {
  if (["tel", "numeric"].includes(props.inputmode || "")) {
    if (event.key.match(/[0-9]/) || event.metaKey || event.key === "Enter")
      return true;
    return event.preventDefault();
  }
};

const onPaste = (event: ClipboardEvent) => {
  event.preventDefault();

  const clipboardData = (
    event.clipboardData?.getData("text/plain") || ""
  ).trim();

  const start = textInputRef.value?.selectionStart ?? 0;
  const end = textInputRef.value?.selectionEnd ?? 0;
  const currentValue = modelValue.value?.toString() ?? "";

  // Compose new value with pasted data
  let newValue: string | number | undefined =
    currentValue.slice(0, start) + clipboardData + currentValue.slice(end);

  newValue = inputMaskingConfiguration[getInputMask.value].set(newValue);

  if (!props.allowEmoji && emojiRegex.test(newValue?.toString() || "")) {
    newValue = newValue?.toString().replace(emojiRegex, "");
  }

  if (props.localTel) {
    newValue = newValue?.toString().replace(/^(?:\+62|62)/, "0");
  }

  if (props.maxLength) {
    newValue = newValue
      ?.toString()
      .slice(0, parseInt(props.maxLength.toString()));
  }

  modelValue.value = newValue;

  // Move cursor after pasted text
  nextTick(() => {
    const pos = start + clipboardData.length;
    textInputRef.value?.setSelectionRange(pos, pos);
  });

  emits("paste", event);
};

defineExpose({
  textInputRef,
  focus: () => textInputRef.value?.focus(),
  blur: () => textInputRef.value?.blur(),
});
</script>

<template>
  <input
    ref="textInputRef"
    v-model="maskedValue"
    :inputmode="props.inputmode"
    :class="cn(props.class)"
    :data-invalid="props.invalid"
    :maxlength="props.maxLength"
    @keypress="onKeyPress"
    @input="onInput"
    @paste="onPaste"
  />
</template>
