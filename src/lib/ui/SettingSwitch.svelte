<script lang="ts">
  import { Switch } from '$lib/components/ui/switch';

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

<Switch
  {checked}
  {disabled}
  aria-label={label}
  {onCheckedChange}
/>
