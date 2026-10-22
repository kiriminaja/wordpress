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
				info: "border-info-border bg-info-background text-info-foreground",
				success: "border-success-border bg-success-background text-success-foreground",
				caution: "border-caution-border bg-caution-background text-caution-foreground",
				warning: "border-warning-border bg-warning-background text-warning-foreground",
				critical: "border-critical-border bg-critical-background text-critical-foreground",
				primary: "border-primary/25 bg-primary/10 text-primary",
				teal: "border-teal-border bg-teal-background text-teal-foreground",
				// Legacy aliases.
				default: "border-info-border bg-info-background text-info-foreground",
				secondary: "border-border bg-muted text-muted-foreground",
				destructive: "border-critical-border bg-critical-background text-critical-foreground",
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
			{ tone: ["auto", "neutral"], color: "strong", class: "border-neutral bg-neutral text-white" },
			{ tone: ["info", "default"], color: "strong", class: "border-info bg-info text-white" },
			{ tone: "success", color: "strong", class: "border-success bg-success text-white" },
			{ tone: "caution", color: "strong", class: "border-caution bg-caution text-white" },
			{ tone: "warning", color: "strong", class: "border-warning bg-warning text-white" },
			{ tone: ["critical", "destructive"], color: "strong", class: "border-critical bg-critical text-white" },
			{ tone: "primary", color: "strong", class: "border-primary bg-primary text-primary-foreground" },
			{ tone: "teal", color: "strong", class: "border-teal bg-teal text-white" },
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
