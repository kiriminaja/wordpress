<script lang="ts">
	import { Pagination as PaginationPrimitive } from "bits-ui";
	import { IconChevronLeft } from '@tabler/icons-svelte';
	import { buttonVariants } from "$lib/components/ui/button/index.js";
	import { cn } from "$lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		...restProps
	}: PaginationPrimitive.PrevButtonProps = $props();
</script>

<PaginationPrimitive.PrevButton
	bind:ref
	aria-label="Go to previous page"
	class={cn(
		buttonVariants({ variant: "ghost", size: "default" }),
		"pl-1.5!",
		className
	)}
	{...restProps}
>
	<IconChevronLeft data-icon="inline-start" class="cn-rtl-flip" />
	<span class="sr-only">Previous</span>
</PaginationPrimitive.PrevButton>
