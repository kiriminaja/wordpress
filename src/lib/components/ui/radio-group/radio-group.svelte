<script lang="ts">
	import { RadioGroup as RadioGroupPrimitive } from "bits-ui";
	import { cn, type WithoutChildrenOrChild } from "$lib/utils.js";
	import type { Snippet } from "svelte";

	let {
		ref = $bindable(null),
		value = $bindable(""),
		onValueChange,
		class: className,
		children,
		...restProps
	}: WithoutChildrenOrChild<RadioGroupPrimitive.RootProps> & { children?: Snippet } = $props();
</script>

<RadioGroupPrimitive.Root
	bind:ref
	bind:value
	data-slot="radio-group"
	class={cn("grid gap-2", className)}
	onValueChange={(nextValue) => {
		value = nextValue;
		onValueChange?.(nextValue);
	}}
	{...restProps}
>
	{@render children?.()}
</RadioGroupPrimitive.Root>
