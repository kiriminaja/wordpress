<script lang="ts">
	import { cn, type WithElementRef } from "$lib/utils.js";
	import type { HTMLAttributes } from "svelte/elements";

	let {
		ref = $bindable(null),
		class: className,
		children,
		...restProps
	}: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();
</script>

<div
	bind:this={ref}
	data-slot="card-title"
	class={cn("!inline-flex min-h-5 !items-center gap-2 text-base leading-[1.25] font-medium group-data-[size=sm]/card:text-sm [&>svg]:size-4 [&>svg]:shrink-0", className)}
	{...restProps}
>
	{@render children?.()}
</div>
