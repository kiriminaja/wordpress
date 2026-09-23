<script lang="ts">
	import { Pagination as PaginationPrimitive } from "bits-ui";
	import { IconChevronRight } from '@tabler/icons-svelte';
	import { buttonVariants } from "$lib/components/ui/button/index.js";
	import { cn } from "$lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		...restProps
	}: PaginationPrimitive.NextButtonProps = $props();
</script>

<PaginationPrimitive.NextButton
	bind:ref
	aria-label="Go to next page"
	class={cn(buttonVariants({ variant: "ghost", size: "default" }), "pr-1.5!", className)}
	{...restProps}
>
	<span class="sr-only">Next</span>
	<IconChevronRight data-icon="inline-end" class="cn-rtl-flip" />
</PaginationPrimitive.NextButton>
