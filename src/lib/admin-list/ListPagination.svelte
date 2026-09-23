<script lang="ts">
  import * as Pagination from '$lib/components/ui/pagination';

  let { page, totalPages, label, onChange }: { page: number; totalPages: number; label: string; onChange: (page: number) => void } = $props();
</script>

{#if totalPages > 1}
  <Pagination.Root count={totalPages} perPage={1} {page} onPageChange={onChange} class="w-auto">
    {#snippet children({ pages, currentPage })}
      <Pagination.Content>
        <Pagination.Item><Pagination.Previous /></Pagination.Item>
        {#each pages as paginationPage (paginationPage.key)}
          {#if paginationPage.type === 'ellipsis'}
            <Pagination.Item><Pagination.Ellipsis /></Pagination.Item>
          {:else}
            <Pagination.Item>
              <Pagination.Link page={paginationPage} isActive={currentPage === paginationPage.value} aria-label={`${paginationPage.value} ${label} ${totalPages}`}>
                {paginationPage.value}
              </Pagination.Link>
            </Pagination.Item>
          {/if}
        {/each}
        <Pagination.Item><Pagination.Next /></Pagination.Item>
      </Pagination.Content>
    {/snippet}
  </Pagination.Root>
{/if}
