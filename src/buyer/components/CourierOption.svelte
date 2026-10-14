<script lang="ts">
  import type { SelectorOption } from '../types/selector';
  let { option, logos = {} }: { option: SelectorOption; logos?: Record<string, string> } = $props();
  let failed = $state('');
  const source = $derived.by(() => {
    const candidate = option.courier && logos[option.courier];
    if (!candidate) return '';
    try {
      const url = new URL(candidate, window.location.href);
      return url.origin === window.location.origin && /\/assets\/(?:wp|buyer)\/img\/couriers\/[a-z0-9_-]+\.png$/.test(url.pathname) ? url.href : '';
    } catch { return ''; }
  });
</script>

<span class="kiriof-buyer-courier" class:is-logo-unavailable={!source || failed === source}>
  {#if source && failed !== source}
    <span class="kiriof-buyer-courier-logo" aria-hidden="true"><img src={source} alt="" onerror={() => failed = source} /></span>
  {/if}
  <span class="kiriof-buyer-courier-copy">
    <span class="kiriof-buyer-courier-label">{option.label}</span>
    {#if option.price}
      <span class="kiriof-buyer-courier-prices">
        {#if option.originalPrice}<del>{option.originalPrice}</del>{/if}
        <span class="kiriof-buyer-courier-current">{option.price}</span>
        {#if option.savings}<span class="kiriof-buyer-courier-savings">{option.savings}</span>{/if}
      </span>
    {/if}
    {#if option.note}<span class="kiriof-buyer-courier-note">{option.note}</span>{/if}
  </span>
</span>
