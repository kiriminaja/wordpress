<script lang="ts">
	import { cn, type WithElementRef } from "$lib/utils.js";
	import type { HTMLLiAttributes } from "svelte/elements";

	let {
		ref = $bindable(null),
		class: className,
		children,
		...restProps
	}: WithElementRef<HTMLLiAttributes> = $props();
</script>

<li bind:this={ref} data-slot="pagination-item" class={cn("mb-0", className)} {...restProps}>
	{@render children?.()}
</li>
