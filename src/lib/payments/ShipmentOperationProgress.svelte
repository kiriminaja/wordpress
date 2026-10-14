<script lang="ts">
  import * as Alert from '$lib/components/ui/alert';
  import { Spinner } from '$lib/components/ui/spinner';

  let { label }: { label: string } = $props();
</script>

<Alert.Root class="kiriof-shipment-operation-progress" role="status" aria-live="polite" aria-busy="true">
  <Spinner aria-hidden="true" role="presentation" />
  <Alert.Description>{label}</Alert.Description>
</Alert.Root>
