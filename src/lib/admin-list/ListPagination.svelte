<script lang="ts">
  import { IconChevronLeft, IconChevronRight } from '@tabler/icons-svelte';
  import * as ButtonGroup from '$lib/components/ui/button-group';
  import { Button } from '$lib/components/ui/button';

  let { page, totalPages, label, onChange }: { page: number; totalPages: number; label: string; onChange: (page: number) => void } = $props();
</script>

{#if totalPages > 1}
  <ButtonGroup.Root aria-label="Pagination">
    <Button variant="outline" size="icon" disabled={page <= 1} onclick={() => onChange(page - 1)} aria-label="Previous page">
      <IconChevronLeft data-icon="inline-start" />
    </Button>
    <ButtonGroup.Text>{page} {label} {totalPages}</ButtonGroup.Text>
    <Button variant="outline" size="icon" disabled={page >= totalPages} onclick={() => onChange(page + 1)} aria-label="Next page">
      <IconChevronRight data-icon="inline-end" />
    </Button>
  </ButtonGroup.Root>
{/if}
