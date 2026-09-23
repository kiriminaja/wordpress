<script lang="ts">
  import { Badge, type BadgeVariant } from '$lib/components/ui/badge';

  let { label, tone = 'neutral' }: { label: string; tone?: 'success' | 'warning' | 'info' | 'neutral' } = $props();

  const variantByTone: Record<NonNullable<typeof tone>, BadgeVariant> = {
    success: 'default',
    warning: 'destructive',
    info: 'secondary',
    neutral: 'outline',
  };

  const variant = $derived(variantByTone[tone]);
</script>

<Badge {variant}>{label}</Badge>
