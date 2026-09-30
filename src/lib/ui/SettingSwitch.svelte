<script lang="ts">
  import { Switch } from '$lib/components/ui/switch';

  let {
    checked,
    disabled = false,
    label,
    id,
    onCheckedChange,
  }: {
    checked: boolean;
    disabled?: boolean;
    label: string;
    id?: string;
    onCheckedChange: (checked: boolean) => void;
  } = $props();
</script>

<Switch
  {id}
  {checked}
  {disabled}
  aria-label={label}
  {onCheckedChange}
/>
