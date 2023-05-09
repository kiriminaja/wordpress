<h3 class="woocommerce-order-details__title"><?php esc_html_e( 'Tracking', 'kiriminaja' ); ?></h3>

<table class="woocommerce-table kiriminaja-tracking">

	<thead>
	</thead>

	<tbody>
		<tr>
			<th><?php esc_html_e( 'Service', 'kiriminaja' ); ?></th>
			<td><?php echo esc_html( $shipping->get_name() ) ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'AWB', 'kiriminaja' ); ?></th>
			<td><?php echo esc_html( $shipping->get_meta('awb') ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Status', 'kiriminaja' ); ?></th>
			<td><?php echo isset( $tracking['text'] ) ? esc_html( $tracking['text'] ) : '&ndash;'; ?></td>
		</tr>
	</tbody>

	<tfoot>
	</tfoot>
</table>

<?php if ( ! empty( $tracking['histories'] ) ) : ?>
	<table id="ka-shipping-history" class="woocommerce-table kiriminaja-tracking">
		<tbody>
			<?php foreach ( $tracking['histories'] as $history ) : ?>
				<tr>
					<th class="timestamp"><?php echo ! empty( $history->created_at ) ? esc_html( date( 'Y-m-d H:i', strtotime( $history->created_at ) ) ) : '&ndash;'; ?></th>
					<td class="status"><?php echo esc_html( $history->status ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>