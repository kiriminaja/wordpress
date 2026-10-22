<script lang="ts">
  import ListPagination from './ListPagination.svelte';

  let {
    count,
    countLabel,
    page,
    totalPages,
    pageLabel,
    onPageChange,
  }: {
    count: number;
    countLabel: string;
    page: number;
    totalPages: number;
    pageLabel: string;
    onPageChange: (page: number) => void;
  } = $props();
</script>

<footer class="kiriof-data-table-footer">
  <span class="kiriof-data-table-footer__count">{count} {countLabel}</span>
  <ListPagination {page} {totalPages} label={pageLabel} onChange={onPageChange} />
</footer>
