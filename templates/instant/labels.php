<?php
/** Standalone local Instant labels. $labels must be validated by InstantLabelService. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $labels ) || ! is_array( $labels ) || empty( $labels ) ) {
	return;
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html__( 'Local shipment label', 'kiriminaja-official' ); ?></title>
	<style>
		@page { size: A6 portrait; margin: 6mm; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #111; background: #eee; font: 12px/1.35 sans-serif; }
		.toolbar { padding: 16px; text-align: center; }
		.label { width: 93mm; min-height: 136mm; margin: 12px auto; padding: 6mm; background: #fff; overflow-wrap: anywhere; }
		h1 { font-size: 17px; margin: 0 0 4px; }
		h2 { font-size: 12px; margin: 10px 0 3px; }
		p { margin: 3px 0; }
		.awb { border: 2px solid #111; padding: 8px; font-size: 18px; font-weight: bold; margin: 10px 0; }
		.disclaimer { font-size: 10px; border-top: 1px solid #111; padding-top: 6px; }
		ul { margin: 4px 0; padding-left: 16px; }
		@media print {
			body { background: #fff; }
			.toolbar { display: none; }
			.label { width: auto; min-height: 0; margin: 0; padding: 0; break-after: page; }
			.label:last-child { break-after: auto; }
		}
	</style>
</head>
<body>
	<div class="toolbar"><button type="button" id="print-labels"><?php echo esc_html__( 'Print', 'kiriminaja-official' ); ?></button></div>
	<?php foreach ( $labels as $kiriof_label ) : ?>
	<article class="label">
		<h1><?php echo esc_html__( 'Local shipment label', 'kiriminaja-official' ); ?></h1>
		<p><?php echo esc_html( $kiriof_label['courier'] . ' — ' . $kiriof_label['service'] . ' — ' . $kiriof_label['vehicle'] ); ?></p>
		<p><?php echo esc_html__( 'Order ID', 'kiriminaja-official' ); ?>: <?php echo esc_html( $kiriof_label['order_id'] ); ?></p>
		<p><?php echo esc_html__( 'WooCommerce reference', 'kiriminaja-official' ); ?>: <?php echo esc_html( $kiriof_label['wc_reference'] ); ?></p>
		<div class="awb"><?php echo esc_html__( 'AWB', 'kiriminaja-official' ); ?>: <?php echo esc_html( $kiriof_label['awb'] ); ?></div>
		<?php foreach ( array( 'sender' => __( 'Sender', 'kiriminaja-official' ), 'recipient' => __( 'Recipient', 'kiriminaja-official' ) ) as $kiriof_key => $kiriof_heading ) : ?>
		<section>
			<h2><?php echo esc_html( $kiriof_heading ); ?></h2>
			<p><?php echo esc_html( $kiriof_label[ $kiriof_key ]['name'] ); ?></p>
			<p><?php echo esc_html( $kiriof_label[ $kiriof_key ]['phone'] ); ?></p>
			<?php foreach ( $kiriof_label[ $kiriof_key ]['address'] as $kiriof_line ) : ?>
			<p><?php echo esc_html( $kiriof_line ); ?></p>
			<?php endforeach; ?>
		</section>
		<?php endforeach; ?>
		<h2><?php echo esc_html__( 'Booked physical items', 'kiriminaja-official' ); ?></h2>
		<ul><?php foreach ( $kiriof_label['items'] as $kiriof_item ) : ?><li><?php echo esc_html( $kiriof_item['name'] . ' × ' . $kiriof_item['quantity'] ); ?></li><?php endforeach; ?></ul>
		<p><?php echo esc_html__( 'Weight (g)', 'kiriminaja-official' ); ?>: <?php echo esc_html( $kiriof_label['weight'] ); ?></p>
		<p><?php echo esc_html__( 'Payment method / status', 'kiriminaja-official' ); ?>: <?php echo esc_html( $kiriof_label['payment_method'] . ' / ' . $kiriof_label['payment_status'] ); ?></p>
		<p><?php echo esc_html__( 'Shipping fee (IDR)', 'kiriminaja-official' ); ?>: <?php echo esc_html( number_format_i18n( $kiriof_label['fee'], 0 ) ); ?></p>
		<p class="disclaimer"><?php echo esc_html__( 'Not a courier-issued shipping label. Carrier-issued label availability remains unconfirmed. The AWB is displayed as text only; this local label does not confirm carrier acceptance.', 'kiriminaja-official' ); ?></p>
	</article>
	<?php endforeach; ?>
	<script>if (window.self === window.top) { document.getElementById('print-labels').addEventListener('click', function () { window.print(); }); } else { document.querySelector('.toolbar').remove(); }</script>
</body>
</html>
