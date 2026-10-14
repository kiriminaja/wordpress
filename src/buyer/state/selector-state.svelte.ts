import type { SelectorOption } from '../types/selector';

/** One presentation state per native control; native values remain authoritative. */
export class SelectorState {
  value = $state('');
  options = $state<SelectorOption[]>([]);
  disabled = $state(false);
  required = $state<string | undefined>();
  invalid = $state<string | undefined>();
  describedBy = $state<string | undefined>();
  labelId = $state<string | undefined>();
  label = $state('');
  term = $state('');
  status = $state('');
  loading = $state(false);
  error = $state(false);
}
