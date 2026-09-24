<script lang="ts" module>
	import { type VariantProps, tv } from "tailwind-variants";

	/**
	 * Unified badge, mirroring the Shopify admin `s-badge` contract:
	 * `tone` carries semantic intent, `color` (base|strong) controls weight.
	 * Compact rounded-square shape (rounded-md), never a full pill.
	 */
	export const badgeVariants = tv({
		base: "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap rounded-md border border-transparent px-1.5 py-px text-[11px] leading-[1.45] font-semibold transition-all group/badge [&>svg]:size-3! [&>svg]:pointer-events-none has-data-[icon=inline-end]:pr-1 has-data-[icon=inline-start]:pl-1 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
		variants: {
			tone: {
				auto: "border-border bg-muted text-muted-foreground",
				neutral: "border-border bg-muted text-muted-foreground",
				info: "border-[#b6d3ea] bg-[#e5f0f9] text-[#0f4c81]",
				success: "border-[#a9dcb9] bg-[#e6f4ea] text-[#1a6b35]",
				caution: "border-[#f0cf62] bg-[#fef3d8] text-[#7a5900]",
				warning: "border-[#f0bd76] bg-[#fdf0dc] text-[#8a4d0a]",
				critical: "border-[#f1a9a9] bg-[#fde7e7] text-[#a02323]",
				// Legacy aliases.
				default: "border-[#b6d3ea] bg-[#e5f0f9] text-[#0f4c81]",
				secondary: "border-border bg-muted text-muted-foreground",
				destructive: "border-[#f1a9a9] bg-[#fde7e7] text-[#a02323]",
				outline: "border-border text-foreground",
				ghost: "hover:bg-muted hover:text-muted-foreground dark:hover:bg-muted/50",
				link: "text-primary underline-offset-4 hover:underline",
			},
			color: {
				base: "",
				strong: "",
			},
		},
		compoundVariants: [
			{ tone: ["auto", "neutral"], color: "strong", class: "border-[#3c434a] bg-[#3c434a] text-white" },
			{ tone: ["info", "default"], color: "strong", class: "border-[#2271b1] bg-[#2271b1] text-white" },
			{ tone: "success", color: "strong", class: "border-[#008112] bg-[#008112] text-white" },
			{ tone: "caution", color: "strong", class: "border-[#9a6700] bg-[#9a6700] text-white" },
			{ tone: "warning", color: "strong", class: "border-[#b45309] bg-[#b45309] text-white" },
			{ tone: ["critical", "destructive"], color: "strong", class: "border-[#d63638] bg-[#d63638] text-white" },
		],
		defaultVariants: {
			tone: "neutral",
			color: "base",
		},
	});

	export type BadgeTone = VariantProps<typeof badgeVariants>["tone"];
	export type BadgeColor = VariantProps<typeof badgeVariants>["color"];
	/** @deprecated Use BadgeTone. Kept for backwards compatibility. */
	export type BadgeVariant = BadgeTone;
</script>

<script lang="ts">
	import { cn, type WithElementRef } from "$lib/utils.js";
	import type { HTMLAnchorAttributes } from "svelte/elements";

	let {
		ref = $bindable(null),
		href,
		class: className,
		tone = "neutral",
		color = "base",
		/** @deprecated Use `tone`. */
		variant,
		children,
		...restProps
	}: WithElementRef<HTMLAnchorAttributes> & {
		tone?: BadgeTone;
		color?: BadgeColor;
		/** @deprecated Use `tone`. */
		variant?: BadgeVariant;
	} = $props();
</script>

<svelte:element
	this={href ? "a" : "span"}
	bind:this={ref}
	data-slot="badge"
	{href}
	class={cn(badgeVariants({ tone: variant ?? tone, color }), className)}
	{...restProps}
>
	{@render children?.()}
</svelte:element>
