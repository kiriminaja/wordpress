<script lang="ts">
  import type { TrackingLabels, TrackingState } from '../tracking/types';
  let { state, labels }: { state: TrackingState; labels: TrackingLabels } = $props();
</script>

<div class="tracking-gorup">
  <div class="tracking-header">
    <p>{labels.orderNumber} : #{state.data.number_order}</p>
    <p>{labels.awbNumber} : {state.data.details.awb}</p>
  </div>
  <div class="tracking-address">
    <div class="track-inline">
      <p class="textprimary">{state.data.details.destination.name}</p>
      <p class="textseccond">{state.data.details.destination.city}</p>
      <p class="textseccond textbold">{state.data.details.destination.province}</p>
    </div>
  </div>
  <div class="tracking-courier">
    <div class="borderdashed"></div>
    <div class="textseccond">
      <p>{labels.courier}</p>
      <p class="fontbold">{state.data.details.service}</p>
    </div>
    <div class="borderdashed"></div>
  </div>
</div>
