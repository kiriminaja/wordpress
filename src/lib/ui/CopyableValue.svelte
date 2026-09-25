<script lang="ts">
  import { IconCheck, IconCopy } from '@tabler/icons-svelte';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';

  let {
    label,
    value,
    copyLabel,
    copiedLabel,
    copyable = true,
  }: {
    label: string;
    value: string;
    copyLabel: string;
    copiedLabel: string;
    copyable?: boolean;
  } = $props();

  let copied = $state(false);
  let copyTimer: number | undefined;

  async function copy(): Promise<void> {
    if (!value) return;

    try {
      await navigator.clipboard.writeText(value);
    } catch {
      const input = document.createElement('textarea');
      input.value = value;
      input.className = 'fixed opacity-0';
      document.body.append(input);
      input.select();
      document.execCommand('copy');
      input.remove();
    }

    copied = true;
    if (copyTimer) window.clearTimeout(copyTimer);
    copyTimer = window.setTimeout(() => {
      copied = false;
    }, 1600);
  }
</script>

<div class="kiriof-copy-field kiriof-copy-field--inline">
  <span class="kiriof-row-label">{label}</span>
  <span class="kiriof-copy-field__value">
    <code>{value || '—'}</code>
    {#if copyable && value}
      <ActionTooltip label={copied ? copiedLabel : copyLabel}>
        <button type="button" class="kiriof-copy-button" onclick={() => void copy()} aria-label={copied ? copiedLabel : copyLabel}>
          {#if copied}<IconCheck />{:else}<IconCopy />{/if}
        </button>
      </ActionTooltip>
    {/if}
  </span>
</div>
