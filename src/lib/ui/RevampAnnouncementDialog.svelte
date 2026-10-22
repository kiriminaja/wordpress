<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';
  import { postWordPressRawAction } from '$lib/wordpress/ajax';
  import type { ToolbarAnnouncement } from './toolbar';

  let {
    announcement,
  }: {
    announcement: ToolbarAnnouncement;
  } = $props();

  let open = $state(true);
  let dismissing = $state(false);

  async function persistDismissal(): Promise<void> {
    try {
      await postWordPressRawAction('kiriof_dismiss_revamp_announcement', {});
    } catch {
      // The dialog can still close if WordPress cannot save the preference.
    }
  }

  async function dismiss(): Promise<void> {
    if (dismissing) return;
    dismissing = true;
    open = false;
    await persistDismissal();
  }

</script>

<Dialog.Root
  bind:open
  onOpenChange={(next: boolean) => {
    if (!next) void dismiss();
  }}
>
  <Dialog.Content
    showCloseButton={false}
    class="kiriof-shadcn max-h-[calc(100dvh-2rem)] overflow-y-auto p-0 sm:max-w-md"
  >
    <div class="aspect-[16/8] overflow-hidden bg-muted">
      <img
        class="h-full w-full object-cover"
        src={announcement.imageUrl}
        alt=""
        aria-hidden="true"
        onerror={(event) => {
          (event.currentTarget as HTMLImageElement).style.display = 'none';
        }}
      />
    </div>

    <Dialog.Header class="px-6 pt-2 text-center sm:text-center">
      <Dialog.Title>{announcement.title}</Dialog.Title>
      <Dialog.Description class="mx-auto max-w-sm">
        {announcement.description}
      </Dialog.Description>
    </Dialog.Header>

    <Dialog.Footer class="flex flex-col gap-2 px-6 pb-6 sm:flex-col">
      <Button
        href={announcement.feedbackUrl}
        target="_blank"
        rel="noopener noreferrer"
        size="lg"
        class="min-h-11 w-full"
        onclick={() => void dismiss()}
      >
        {announcement.feedbackLabel}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="lg"
        class="min-h-11 w-full"
        onclick={() => void dismiss()}
      >
        {announcement.continueLabel}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
