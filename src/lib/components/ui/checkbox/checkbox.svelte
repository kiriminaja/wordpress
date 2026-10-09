<script lang="ts">
  import type { Checkbox as CheckboxPrimitive } from 'bits-ui';
  import type { WithoutChildrenOrChild } from '$lib/utils';
  import KiriofCheckbox from '$lib/ui/KiriofCheckbox.svelte';

  let {
    ref = $bindable(null),
    checked = $bindable(false),
    indeterminate = $bindable(false),
    ...restProps
  }: WithoutChildrenOrChild<CheckboxPrimitive.RootProps> = $props();
</script>

<KiriofCheckbox bind:ref bind:checked bind:indeterminate {...restProps} />
