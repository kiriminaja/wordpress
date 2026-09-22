<script lang="ts">
  import { Switch } from 'bits-ui';

  let {
    checked,
    disabled = false,
    label,
    onCheckedChange,
  }: {
    checked: boolean;
    disabled?: boolean;
    label: string;
    onCheckedChange: (checked: boolean) => void;
  } = $props();
</script>

<Switch.Root
  class="kiriof-switch"
  {checked}
  {disabled}
  aria-label={label}
  {onCheckedChange}
>
  <Switch.Thumb class="kiriof-switch__thumb" />
</Switch.Root>
