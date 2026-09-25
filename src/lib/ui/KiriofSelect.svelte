<script lang="ts">
  import * as Select from '$lib/components/ui/select';

  export type KiriofSelectOption = { value: string; label: string };

  let {
    value = $bindable(''),
    id,
    options,
    placeholder,
    disabled = false,
    prefix,
    suffix,
    onChange,
  }: {
    value?: string;
    id?: string;
    options: KiriofSelectOption[];
    placeholder: string;
    disabled?: boolean;
    prefix?: import('svelte').Snippet;
    suffix?: import('svelte').Snippet;
    onChange?: (value: string) => void;
  } = $props();

  function change(nextValue: string): void {
    value = nextValue;
    onChange?.(nextValue);
  }
</script>

<Select.Root type="single" {value} onValueChange={change} {disabled}>
  <Select.Trigger {id} class="kiriof-select !w-full !bg-background !border-input">
    {#if prefix}<span class="flex shrink-0 items-center text-muted-foreground">{@render prefix()}</span>{/if}
    <Select.Value {placeholder} />
    {#if suffix}<span class="flex shrink-0 items-center text-muted-foreground">{@render suffix()}</span>{/if}
  </Select.Trigger>
  <Select.Content class="kiriof-shadcn">
    <Select.Group>
      {#each options as option (option.value)}
        <Select.Item value={option.value}>{option.label}</Select.Item>
      {/each}
    </Select.Group>
  </Select.Content>
</Select.Root>
