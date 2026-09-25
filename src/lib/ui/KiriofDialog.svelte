<script lang="ts">
  import { Button, type ButtonVariant } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';

  let {
    open = $bindable(false),
    title,
    description,
    primaryLabel,
    primaryVariant = 'default',
    primaryDisabled = false,
    secondaryLabel,
    secondaryDisabled = false,
    class: className = '',
    children,
    onPrimary,
    onSecondary,
    onOpenChange,
  }: {
    open?: boolean;
    title: string;
    description?: string;
    primaryLabel: string;
    primaryVariant?: ButtonVariant;
    primaryDisabled?: boolean;
    secondaryLabel?: string;
    secondaryDisabled?: boolean;
    class?: string;
    children?: import('svelte').Snippet;
    onPrimary: () => void | Promise<void>;
    onSecondary?: () => void;
    onOpenChange?: (open: boolean) => void;
  } = $props();

  function close(): void {
    if (secondaryDisabled) return;
    onOpenChange?.(false);
    open = false;
  }

  function secondary(): void {
    if (secondaryDisabled) return;
    if (onSecondary) onSecondary();
    else close();
  }
</script>

<Dialog.Root {open} onOpenChange={(nextOpen) => { if (!nextOpen) close(); else onOpenChange?.(nextOpen); }}>
  <Dialog.Content class={`kiriof-shadcn kiriof-dialog ${className}`}>
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
      {#if description}<Dialog.Description>{description}</Dialog.Description>{/if}
    </Dialog.Header>

    {#if children}<div class="grid gap-3">{@render children()}</div>{/if}

    <Dialog.Footer>
      {#if secondaryLabel}
        <Button variant="ghost" onclick={secondary} disabled={secondaryDisabled}>{secondaryLabel}</Button>
      {/if}
      <Button variant={primaryVariant} onclick={() => void onPrimary()} disabled={primaryDisabled}>{primaryLabel}</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
