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
		"peer inline-flex shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input",
		size === "sm" ? "h-4 w-7" : "h-5 w-9",
		className
	)}
	{...restProps}
>
	<SwitchPrimitive.Thumb
		data-slot="switch-thumb"
		class={cn(
			"pointer-events-none block rounded-full bg-background shadow-md ring-0 transition-transform data-[state=unchecked]:translate-x-0",
			size === "sm"
				? "size-3 data-[state=checked]:translate-x-3"
				: "size-4 data-[state=checked]:translate-x-4",
			"rtl:data-[state=checked]:-translate-x-4"
		)}
	/>
</SwitchPrimitive.Root>
