<script lang="ts" module>
  /**
   * Badge tone + color maps, mirroring the Shopify admin `s-badge` contract
   * and the kaj-shopify-plugin `getLabelProps` status mapping.
   *
   * tone: auto | neutral | info | success | caution | warning | critical
   *   | primary (New) | teal (In Transit)
   * color: base (default) | strong
   */

  export type StatusTone = 'auto' | 'neutral' | 'info' | 'success' | 'caution' | 'warning' | 'critical' | 'primary' | 'teal';
  export type BadgeColor = 'base' | 'strong';

  /** Package status → badge tone. Every package status owns its color: New = primary, In Transit = teal. */
  export const packageStatusToneMap: Record<string, StatusTone> = {
    new: 'primary',
    request_pickup: 'info',
    pending: 'caution',
    finished: 'success',
    shipped: 'teal',
    return: 'warning',
    returned: 'warning',
    rejected: 'critical',
    canceled: 'critical',
  };

  /** WC order status → badge tone. */
  export const wcStatusToneMap: Record<string, StatusTone> = {
    'wc-processing': 'info',
    'wc-on-hold': 'caution',
    'wc-pending': 'caution',
    'wc-completed': 'success',
    'wc-cancelled': 'critical',
    'wc-refunded': 'critical',
    'wc-failed': 'critical',
  };

  /** COD settlement badges. */
  export const codToneMap: Record<string, StatusTone> = {
    COD: 'info',
    'NON COD': 'neutral',
  };

  /** Payment badges. */
  export const paymentStatusToneMap: Record<string, StatusTone> = {
    paid: 'success',
    unpaid: 'warning',
    QRIS: 'info',
  };

  export function packageStatusTone(status: string): StatusTone {
    return packageStatusToneMap[status] ?? 'neutral';
  }

  export function wcStatusTone(postStatus: string): StatusTone {
    return wcStatusToneMap[postStatus] ?? 'neutral';
  }
</script>

<script lang="ts">
  import { Badge } from '$lib/components/ui/badge';

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  type IconComponent = any;

  let {
    label,
    tone = 'neutral',
    color = 'base',
    icon,
  }: {
    label: string;
    tone?: StatusTone | 'danger' | 'success' | 'warning' | 'info' | 'neutral';
    color?: BadgeColor;
    icon?: IconComponent;
  } = $props();

  /** Legacy tone aliases → unified tones. */
  const legacyToneMap: Record<string, StatusTone> = {
    danger: 'critical',
    success: 'success',
    warning: 'warning',
    info: 'info',
    neutral: 'neutral',
  };
  const resolvedTone = $derived(legacyToneMap[tone] ?? (tone as StatusTone));
  const Icon = $derived(icon);
</script>

<Badge tone={resolvedTone} {color}>
  {#if Icon}<Icon data-icon="inline-start" />{/if}{label}
</Badge>
