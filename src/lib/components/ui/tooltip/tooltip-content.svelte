<script lang="ts">
	import { Tooltip as TooltipPrimitive } from "bits-ui";
	import { cn, type WithoutChildrenOrChild } from "$lib/utils.js";
	import type { Snippet } from "svelte";

	let {
		ref = $bindable(null),
		class: className,
		sideOffset = 5,
		children,
		...restProps
	}: WithoutChildrenOrChild<TooltipPrimitive.ContentProps> & { children?: Snippet } = $props();
</script>

<TooltipPrimitive.Portal>
	<TooltipPrimitive.Content
		bind:ref
		data-slot="tooltip-content"
		{sideOffset}
		class={cn(
			"kiriof-tooltip-content bg-foreground text-background data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 z-[100002] w-max max-w-72 whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium leading-5 shadow-lg",
			className
		)}
		{...restProps}
	>
		{@render children?.()}
		<TooltipPrimitive.Arrow class="kiriof-tooltip-arrow text-foreground" />
	</TooltipPrimitive.Content>
</TooltipPrimitive.Portal>
