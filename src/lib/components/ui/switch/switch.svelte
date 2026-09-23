<script lang="ts">
	import { Switch as SwitchPrimitive } from "bits-ui";
	import { cn, type WithoutChildrenOrChild } from "$lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		checked = $bindable(false),
		onCheckedChange,
		size = "default",
		...restProps
	}: WithoutChildrenOrChild<SwitchPrimitive.RootProps> & {
		size?: "sm" | "default";
	} = $props();

	function handleCheckedChange(value: boolean) {
		checked = value;
		onCheckedChange?.(value);
	}
</script>

<SwitchPrimitive.Root
	bind:ref
	{checked}
	onCheckedChange={handleCheckedChange}
	data-slot="switch"
	data-size={size}
	class={cn(
		"peer relative inline-flex shrink-0 cursor-pointer items-center overflow-hidden rounded-full border-0 p-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input",
		size === "sm" ? "h-4 w-7" : "h-5 w-9",
		className
	)}
	{...restProps}
>
	<SwitchPrimitive.Thumb
		data-slot="switch-thumb"
		class={cn(
			"pointer-events-none absolute top-0.5 block rounded-full bg-background shadow-sm ring-0 transition-[left]",
			size === "sm"
				? "left-0.5 size-3 data-[state=checked]:left-3.5"
				: "left-0.5 size-4 data-[state=checked]:left-[18px]"
		)}
	/>
</SwitchPrimitive.Root>
